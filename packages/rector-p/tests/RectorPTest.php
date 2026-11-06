<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP\Tests;

use PHPUnit\Framework\TestCase;

/**
 * runs the rector-p binary against a small project with real rector runs
 */
final class RectorPTest extends TestCase
{
    /**
     * the default config of the project: replaces isset() ternaries with ??
     */
    private const DIST_CONFIG = <<<'PHP'
        <?php
        use Rector\Config\RectorConfig;
        use Rector\Php70\Rector\Ternary\TernaryToNullCoalescingRector;
        return RectorConfig::configure()->withPaths([__DIR__ . '/src'])->withRules([TernaryToNullCoalescingRector::class]);
        PHP;

    /**
     * a second config: replaces list() with []
     */
    private const CUSTOM_CONFIG = <<<'PHP'
        <?php
        use Rector\Config\RectorConfig;
        use Rector\Php71\Rector\List_\ListToArrayDestructRector;
        return RectorConfig::configure()->withPaths([__DIR__ . '/src'])->withRules([ListToArrayDestructRector::class]);
        PHP;

    private const SOURCE = <<<'PHP'
        <?php
        function %s(array $arr): int
        {
            list($a, $b) = $arr;
            $c = isset($arr['c']) ? $arr['c'] : 0;
            return $a + $b + $c;
        }
        PHP;

    private string $projectDirectory;

    /**
     * @var list<string>
     */
    private array $cacheFilesBefore = [];

    protected function setUp(): void
    {
        // the space in the path makes sure paths are escaped for the shell
        $directory = sys_get_temp_dir() . '/rector-p-test-' . bin2hex(random_bytes(8)) . '/my project';
        mkdir($directory . '/src', 0777, true);
        $realPath = realpath($directory);
        self::assertIsString($realPath);
        $this->projectDirectory = $realPath;

        file_put_contents($this->projectDirectory . '/rector.dist.php', self::DIST_CONFIG);
        file_put_contents($this->projectDirectory . '/custom.php', self::CUSTOM_CONFIG);
        file_put_contents($this->projectDirectory . '/src/A Space.php', sprintf(self::SOURCE, 'fA'));
        file_put_contents($this->projectDirectory . '/src/B.php', sprintf(self::SOURCE, 'fB'));

        $this->cacheFilesBefore = $this->cacheFiles();
    }

    protected function tearDown(): void
    {
        // rector-p stores its progress in the package directory
        foreach (array_diff($this->cacheFiles(), $this->cacheFilesBefore) as $cacheFile) {
            unlink($cacheFile);
        }

        $this->removeDirectory(dirname($this->projectDirectory));
    }

    public function testUsesRectorDistPhpWhenThereIsNoRectorPhp(): void
    {
        $result = $this->runRectorP(['-vv']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString("--config='" . $this->projectDirectory . "/rector.dist.php'", $result['output']);
        self::assertStringContainsString("\$c = \$arr['c'] ?? 0;", $this->source('A Space'));
        self::assertStringContainsString('list($a, $b) = $arr;', $this->source('A Space'), 'the rules of custom.php must not run');
    }

    public function testConfigOptionIsUsedForEveryRectorRun(): void
    {
        $result = $this->runRectorP(['-c', 'custom.php']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        foreach (['A Space', 'B'] as $fileName) {
            self::assertStringContainsString('[$a, $b] = $arr;', $this->source($fileName));
            self::assertStringContainsString("isset(\$arr['c'])", $this->source($fileName), 'the rules of rector.dist.php must not run');
        }
    }

    public function testStopsWhenRectorFails(): void
    {
        file_put_contents($this->projectDirectory . '/src/0Broken.php', "<?php\nfunction broken( {\n");

        $result = $this->runRectorP([]);

        self::assertSame(1, $result['exitCode'], $result['output']);
        self::assertStringContainsString('Rector failed for ' . $this->projectDirectory . '/src/0Broken.php with exit code 1', $result['output']);
        self::assertStringContainsString("isset(\$arr['c'])", $this->source('A Space'), 'rector-p must stop at the broken file');
    }

    public function testNextChunkCommandKeepsConfigAndPaths(): void
    {
        $firstChunk = $this->runRectorP(['-c', 'custom.php', '--chunk', '1/2', 'src']);

        self::assertSame(0, $firstChunk['exitCode'], $firstChunk['output']);
        self::assertStringContainsString('[$a, $b] = $arr;', $this->source('A Space'));
        self::assertStringContainsString('list($a, $b) = $arr;', $this->source('B'));

        $nextCommand = $this->nextChunkCommand($firstChunk['output']);
        self::assertStringEndsWith("--config='" . $this->projectDirectory . "/custom.php' --chunk 2/2 -- 'src'", $nextCommand);

        $arguments = $this->splitShellArguments($nextCommand);
        $secondChunk = $this->runRectorP(array_slice($arguments, 1));

        self::assertSame(0, $secondChunk['exitCode'], $secondChunk['output']);
        self::assertStringContainsString('All chunks done', $secondChunk['output']);
        self::assertStringContainsString('[$a, $b] = $arr;', $this->source('B'));
    }

    public function testChunksCoverAllFilesAfterRectorCachedTheFirstChunk(): void
    {
        // the first chunk has nothing to change, so rector caches its files as unchanged
        foreach (['A Space' => 'fA', 'B' => 'fB'] as $fileName => $functionName) {
            $source = str_replace('list($a, $b) = $arr;', '[$a, $b] = $arr;', sprintf(self::SOURCE, $functionName));
            file_put_contents($this->projectDirectory . '/src/' . $fileName . '.php', $source);
        }

        file_put_contents($this->projectDirectory . '/src/C.php', sprintf(self::SOURCE, 'fC'));
        file_put_contents($this->projectDirectory . '/src/D.php', sprintf(self::SOURCE, 'fD'));

        // without paths on the command line rector-p uses the paths of the config
        $firstChunk = $this->runRectorP(['-c', 'custom.php', '--chunk', '1/2']);
        self::assertSame(0, $firstChunk['exitCode'], $firstChunk['output']);

        $arguments = $this->splitShellArguments($this->nextChunkCommand($firstChunk['output']));
        $secondChunk = $this->runRectorP(array_slice($arguments, 1));
        self::assertSame(0, $secondChunk['exitCode'], $secondChunk['output']);

        foreach (['A Space', 'B', 'C', 'D'] as $fileName) {
            self::assertStringContainsString('[$a, $b] = $arr;', $this->source($fileName), $fileName . ' was skipped');
        }
    }

    public function testEmptyFileListIsNoError(): void
    {
        mkdir($this->projectDirectory . '/empty');

        $result = $this->runRectorP(['empty']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('No files found', $result['output']);
    }

    public function testProgressIsKeptPerConfig(): void
    {
        self::assertSame(2, $this->checkedFiles(['-c', 'custom.php']));
        self::assertSame(0, $this->checkedFiles(['-c', 'custom.php']), 'all files are done for custom.php');
        self::assertSame(2, $this->checkedFiles([]), 'rector.dist.php has its own progress');
        self::assertSame(2, $this->checkedFiles(['-c', 'custom.php', '--startOver']));
        self::assertSame(0, $this->checkedFiles([]), '--startOver must only clear the progress of custom.php');
    }

    /**
     * @param list<string> $arguments
     */
    private function checkedFiles(array $arguments): int
    {
        $result = $this->runRectorP($arguments);
        self::assertSame(0, $result['exitCode'], $result['output']);

        return substr_count($result['output'], 'Checking file ');
    }

    /**
     * @param list<string> $arguments
     * @return array{exitCode: int, output: string}
     */
    private function runRectorP(array $arguments): array
    {
        $packageDirectory = dirname(__DIR__);
        $environment = getenv();
        // rector-p calls rector from the PATH if it is not started from vendor/bin
        $environment['PATH'] = $packageDirectory . '/vendor/bin' . PATH_SEPARATOR . ($environment['PATH'] ?? '');

        // stdout and stderr go to the same file, so the output keeps its order
        $outputFile = dirname($this->projectDirectory) . '/output.txt';
        $process = proc_open(
            [PHP_BINARY, $packageDirectory . '/rector-p', '--no-interaction', '--no-ansi', ...$arguments],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $outputFile, 'a'], 2 => ['file', $outputFile, 'a']],
            $pipes,
            $this->projectDirectory,
            $environment,
        );
        self::assertIsResource($process);
        $exitCode = proc_close($process);
        $output = (string)file_get_contents($outputFile);
        unlink($outputFile);

        return ['exitCode' => $exitCode, 'output' => $output];
    }

    private function nextChunkCommand(string $output): string
    {
        $lines = explode(PHP_EOL, $output);
        $index = array_search('you can now start the next chunk:', $lines, true);
        self::assertIsInt($index, $output);

        return trim($lines[$index + 1] ?? '');
    }

    /**
     * lets the shell split the printed command, like it does when a user copies it
     *
     * @return list<string>
     */
    private function splitShellArguments(string $command): array
    {
        $process = proc_open(['sh', '-c', 'printf "%s\0" ' . $command], [1 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = (string)stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);

        return explode("\0", rtrim($output, "\0"));
    }

    private function source(string $fileName): string
    {
        return (string)file_get_contents($this->projectDirectory . '/src/' . $fileName . '.php');
    }

    /**
     * @return list<string>
     */
    private function cacheFiles(): array
    {
        return glob(__DIR__ . '/../cache*.json') ?: [];
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
