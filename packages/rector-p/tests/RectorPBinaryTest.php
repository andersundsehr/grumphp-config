<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * runs the rector-p binary in its own process, like a user in a shell
 */
#[CoversNothing]
final class RectorPBinaryTest extends TestCase
{
    private ProjectFixture $project;

    /**
     * @var list<string>
     */
    private array $cacheFilesBefore = [];

    protected function setUp(): void
    {
        $this->project = new ProjectFixture();
        $this->cacheFilesBefore = $this->cacheFiles();
    }

    protected function tearDown(): void
    {
        // the binary stores its progress in the package directory
        foreach (array_diff($this->cacheFiles(), $this->cacheFilesBefore) as $cacheFile) {
            unlink($cacheFile);
        }

        $this->project->remove();
    }

    public function testPrintedNextChunkCommandWorksInTheShell(): void
    {
        // the path of the binary has a space as well
        $binary = $this->project->progressDirectory . '/my bin/rector-p';
        mkdir(dirname($binary));
        symlink((string)realpath(__DIR__ . '/../rector-p'), $binary);

        $firstChunk = $this->runBinary(['-c', 'custom.php', '--chunk', '1/2', 'src'], $binary);

        self::assertSame(0, $firstChunk['exitCode'], $firstChunk['output']);
        self::assertTrue($this->project->isListConverted('A Space'));
        self::assertFalse($this->project->isListConverted('B'));

        $nextCommand = $this->nextChunkCommand($firstChunk['output']);
        $arguments = $this->splitShellArguments($nextCommand);
        self::assertSame($binary, $arguments[0]);

        $secondChunk = $this->runBinary(array_slice($arguments, 1), $arguments[0]);

        self::assertSame(0, $secondChunk['exitCode'], $secondChunk['output']);
        self::assertStringContainsString('All chunks done', $secondChunk['output']);
        self::assertTrue($this->project->isListConverted('B'));
        self::assertFalse($this->project->isTernaryConverted('B'), 'the next chunk must use custom.php as well');
    }

    /**
     * rector's result cache must not shorten the file list of the next chunk.
     * This only shows with separate processes, so it is tested with the binary.
     */
    public function testChunksCoverAllFilesAfterRectorCachedTheFirstChunk(): void
    {
        // the first chunk has nothing to change, so rector caches its files as unchanged
        $this->project->writeSource('A Space', true);
        $this->project->writeSource('B', true);
        $this->project->writeSource('C');
        $this->project->writeSource('D');

        $firstChunk = $this->runBinary(['-c', 'custom.php', '--chunk', '1/2']);
        self::assertSame(0, $firstChunk['exitCode'], $firstChunk['output']);

        $secondChunk = $this->runBinary(['-c', 'custom.php', '--chunk', '2/2']);
        self::assertSame(0, $secondChunk['exitCode'], $secondChunk['output']);
        self::assertStringContainsString('Files 2 of 4', $secondChunk['output']);
        self::assertTrue($this->project->isListConverted('C'), 'C was skipped');
        self::assertTrue($this->project->isListConverted('D'));
    }

    /**
     * @param list<string> $arguments
     * @return array{exitCode: int, output: string}
     */
    private function runBinary(array $arguments, string $binary = __DIR__ . '/../rector-p'): array
    {
        $environment = getenv();
        // rector-p calls rector from the PATH if it is not started from vendor/bin
        $environment['PATH'] = __DIR__ . '/../vendor/bin' . PATH_SEPARATOR . ($environment['PATH'] ?? '');
        // stdout and stderr go to the same file, so the output keeps its order
        $outputFile = $this->project->progressDirectory . '/output.txt';

        $process = proc_open(
            [PHP_BINARY, $binary, '--no-interaction', '--no-ansi', ...$arguments],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $outputFile, 'a'], 2 => ['file', $outputFile, 'a']],
            $pipes,
            $this->project->directory,
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

    /**
     * @return list<string>
     */
    private function cacheFiles(): array
    {
        return glob(__DIR__ . '/../cache*.json') ?: [];
    }
}
