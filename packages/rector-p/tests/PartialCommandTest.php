<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP\Tests;

use Rector\Config\RectorConfig;
use Andersundsehr\RectorP\Cache;
use Andersundsehr\RectorP\Exception\StopException;
use Andersundsehr\RectorP\PartialCommand;
use Andersundsehr\RectorP\RectorPApplication;
use FilesystemIterator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rector\Configuration\Option;
use Rector\Configuration\Parameter\SimpleParameterProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * runs the partial command in this process
 *
 * Most tests use the fake rector in tests/Fixture/bin, which takes milliseconds instead of a second per call.
 * The tests named testRealRector… use the real rector to check that rector-p works with it.
 */
#[CoversClass(PartialCommand::class)]
#[CoversClass(StopException::class)]
final class PartialCommandTest extends TestCase
{
    private ProjectFixture $project;

    private string $fakeBinDirectory;

    private mixed $previousBinDirectory;

    protected function setUp(): void
    {
        $this->project = new ProjectFixture();
        $this->fakeBinDirectory = (string)realpath(__DIR__ . '/Fixture/bin');
        // like vendor/bin/rector-p: rector-p calls rector from this directory
        $this->previousBinDirectory = $GLOBALS['_composer_bin_dir'] ?? null;
        $GLOBALS['_composer_bin_dir'] = $this->fakeBinDirectory;
    }

    protected function tearDown(): void
    {
        $GLOBALS['_composer_bin_dir'] = $this->previousBinDirectory;
        if ($this->previousBinDirectory === null) {
            unset($GLOBALS['_composer_bin_dir']);
        }

        $this->project->remove();
    }

    public function testRealRectorAppliesTheRulesOfRectorDistPhp(): void
    {
        $this->useRealRector();

        $result = $this->runRectorP([]);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertTrue($this->project->isTernaryConverted('A Space'));
        self::assertTrue($this->project->isTernaryConverted('B'));
        self::assertFalse($this->project->isListConverted('A Space'), 'the rules of custom.php must not run');
    }

    public function testRealRectorAppliesTheRulesOfTheConfigOption(): void
    {
        $this->useRealRector();

        $result = $this->runRectorP(['-c', 'custom.php']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        foreach (['A Space', 'B'] as $name) {
            self::assertTrue($this->project->isListConverted($name));
            self::assertFalse($this->project->isTernaryConverted($name), 'the rules of rector.dist.php must not run');
        }
    }

    public function testRealRectorFailureStopsWithItsExitCode(): void
    {
        $this->useRealRector();
        $this->project->writeFile('0Broken', "<?php\nfunction broken( {\n");

        $result = $this->runRectorP([]);

        self::assertSame(1, $result['exitCode'], $result['output']);
        self::assertStringContainsString('Rector failed for ' . $this->project->filePath('0Broken') . ' with exit code 1', $result['output']);
        self::assertFalse($this->project->isTernaryConverted('A Space'), 'rector-p must stop at the broken file');
    }

    public function testFindsRectorDistPhpAndPassesItToEveryRectorRun(): void
    {
        $result = $this->runRectorP([], verbosity: OutputInterface::VERBOSITY_VERY_VERBOSE);

        self::assertSame(0, $result['exitCode'], $result['output']);
        $rector = escapeshellarg($this->fakeBinDirectory . '/rector') . ' process --ansi --no-progress-bar';
        $config = "--config='" . $this->project->directory . "/rector.dist.php'";
        self::assertStringContainsString($rector . ' --dry-run ' . $config . " '" . $this->project->filePath('A Space') . "'", $result['output']);
        self::assertStringContainsString($rector . ' --no-diffs ' . $config . " '" . $this->project->filePath('A Space') . "'", $result['output']);
        $configArgument = '--config=' . $this->project->directory . '/rector.dist.php';
        self::assertSame(
            [
                ['process', '--ansi', '--no-progress-bar', '--dry-run', $configArgument, $this->project->filePath('A Space')],
                ['process', '--ansi', '--no-progress-bar', '--no-diffs', $configArgument, $this->project->filePath('A Space')],
                ['process', '--ansi', '--no-progress-bar', '--dry-run', $configArgument, $this->project->filePath('B')],
                ['process', '--ansi', '--no-progress-bar', '--no-diffs', $configArgument, $this->project->filePath('B')],
            ],
            $this->project->rectorCalls(),
        );
        self::assertStringContainsString('Total files 2', $result['output']);
        self::assertMatchesRegularExpression('#done with 1/2 files ~[\dhms ]+ to finish#', $result['output']);
        self::assertMatchesRegularExpression('#done with 2/2 files ~[\dhms ]+ to finish#', $result['output']);
        self::assertStringContainsString('Done with chunk 1 of 1', $result['output']);
        self::assertStringContainsString('All chunks done', $result['output']);
        self::assertStringNotContainsString('duration: ', $result['output']);
        self::assertTrue($this->project->isListConverted('A Space'));
        self::assertTrue($this->project->isListConverted('B'));
    }

    public function testDebugVerbosityPrintsTheDurationOfEveryRectorRun(): void
    {
        $result = $this->runRectorP([], verbosity: OutputInterface::VERBOSITY_DEBUG);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertMatchesRegularExpression('/duration: [\d.]+s/', $result['output']);
    }

    public function testNormalVerbosityPrintsNoRectorCommands(): void
    {
        $result = $this->runRectorP([]);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('Checking file ' . $this->project->filePath('A Space') . ' ...', $result['output']);
        self::assertStringNotContainsString('process --ansi', $result['output']);
    }

    public function testConfigOptionIsPassedToEveryRectorRun(): void
    {
        $result = $this->runRectorP(['-c', 'custom.php']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        $calls = $this->project->rectorCalls();
        self::assertCount(4, $calls);
        foreach ($calls as $call) {
            self::assertContains('--config=' . $this->project->directory . '/custom.php', $call);
        }
    }

    public function testStopsWithTheExitCodeOfRectorWhenTheCheckFails(): void
    {
        $this->project->writeFile('0Broken', "<?php\nfunction broken( {\n");

        $result = $this->runRectorP([]);

        self::assertSame(1, $result['exitCode'], $result['output']);
        self::assertStringContainsString('Rector failed for ' . $this->project->filePath('0Broken') . ' with exit code 1', $result['output']);
        self::assertStringNotContainsString('Done with chunk', $result['output']);
        self::assertCount(1, $this->project->rectorCalls(), 'rector-p must stop at the broken file');
    }

    public function testStopsWithTheExitCodeOfRectorWhenApplyingTheChangesFails(): void
    {
        $this->project->writeFile('A Space', "<?php\n// fail on apply\nlist(\$a, \$b) = [1, 2];\n");

        $result = $this->runRectorP([]);

        self::assertSame(1, $result['exitCode'], $result['output']);
        self::assertStringContainsString('Rector failed for ' . $this->project->filePath('A Space') . ' with exit code 1', $result['output']);
        self::assertStringNotContainsString('Done with chunk', $result['output']);
        $this->project->writeSource('A Space');
        self::assertSame(2, $this->checkedFiles($this->runRectorP([])), 'the failed file is not done');
    }

    public function testSkipFileLeavesTheFileForTheNextRun(): void
    {
        $result = $this->runRectorP([], ['skip-file', 'skip-file']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('Found changes in file ' . $this->project->filePath('A Space') . ' (default: execute)?', $result['output']);
        self::assertFalse($this->project->isListConverted('A Space'));
        self::assertFalse($this->project->isListConverted('B'));
        self::assertSame(2, $this->checkedFiles($this->runRectorP([])), 'skipped files are not done');
    }

    public function testRetryChecksTheFileAgain(): void
    {
        $result = $this->runRectorP([], ['retry', 'execute', 'execute']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertSame(2, substr_count($result['output'], 'Checking file ' . $this->project->filePath('A Space')));
        self::assertSame(1, substr_count($result['output'], 'Checking file ' . $this->project->filePath('B')));
        self::assertTrue($this->project->isListConverted('A Space'));
        self::assertTrue($this->project->isListConverted('B'));
    }

    public function testQuitStopsWithoutChangingTheFile(): void
    {
        $result = $this->runRectorP([], ['quit']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertFalse($this->project->isListConverted('A Space'));
        self::assertStringNotContainsString('Checking file ' . $this->project->filePath('B'), $result['output']);
        self::assertStringNotContainsString('Done with chunk', $result['output']);
    }

    public function testEmptyAnswerExecutes(): void
    {
        $result = $this->runRectorP([], ['', '']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertTrue($this->project->isListConverted('A Space'));
        self::assertTrue($this->project->isListConverted('B'));
    }

    public function testFilesWithoutChangesAreDoneWithoutQuestion(): void
    {
        $this->project->writeSource('A Space', true);
        $this->project->writeSource('B', true);

        $result = $this->runRectorP(['-c', 'custom.php'], ['quit']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringNotContainsString('Found changes', $result['output']);
        self::assertStringContainsString('All chunks done', $result['output']);
        self::assertSame(0, $this->checkedFiles($this->runRectorP(['-c', 'custom.php'])));
    }

    public function testDoneFilesAreSkipped(): void
    {
        $cache = new Cache($this->project->progressDirectory);
        $cache->useConfigFile($this->project->directory . '/custom.php');
        $cache->setProcessed($this->project->filePath('A Space'));

        $result = $this->runRectorP(['-c', 'custom.php']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringNotContainsString('Checking file ' . $this->project->filePath('A Space'), $result['output']);
        self::assertStringContainsString('Checking file ' . $this->project->filePath('B'), $result['output']);
        self::assertStringContainsString('done with 2/2 files', $result['output']);
        self::assertStringNotContainsString('done with 1/2 files', $result['output']);
    }

    public function testStartOverClearsTheProgress(): void
    {
        $this->runRectorP(['-c', 'custom.php']);

        $result = $this->runRectorP(['-c', 'custom.php', '--startOver']);

        self::assertStringContainsString('Starting over (cleared the processed cache)', $result['output']);
        self::assertSame(2, $this->checkedFiles($result));
    }

    public function testProgressIsKeptPerConfig(): void
    {
        self::assertSame(2, $this->checkedFiles($this->runRectorP(['-c', 'custom.php'])));
        self::assertSame(0, $this->checkedFiles($this->runRectorP(['-c', 'custom.php'])), 'all files are done for custom.php');
        self::assertSame(2, $this->checkedFiles($this->runRectorP([])), 'rector.dist.php has its own progress');
        self::assertSame(2, $this->checkedFiles($this->runRectorP(['-c', 'custom.php', '--startOver'])));
        self::assertSame(0, $this->checkedFiles($this->runRectorP([])), '--startOver must only clear the progress of custom.php');
    }

    public function testChunksWithConfigAndPaths(): void
    {
        $this->project->writeSource('C');

        $firstChunk = $this->runRectorP(['-c', 'custom.php', '--chunk', '1/2', 'src']);

        self::assertSame(0, $firstChunk['exitCode'], $firstChunk['output']);
        self::assertStringContainsString('Total files 3', $firstChunk['output']);
        self::assertStringNotContainsString('Files ', $firstChunk['output']);
        self::assertStringContainsString('Done with chunk 1 of 2', $firstChunk['output']);
        $nextCommand = "vendor/bin/rector-p --config='" . $this->project->directory . "/custom.php' --chunk 2/2 -- 'src'";
        self::assertStringContainsString('you can now start the next chunk:' . PHP_EOL . $nextCommand . PHP_EOL, $firstChunk['output']);
        self::assertTrue($this->project->isListConverted('A Space'));
        self::assertTrue($this->project->isListConverted('B'));
        self::assertFalse($this->project->isListConverted('C'));

        $secondChunk = $this->runRectorP(['--config=' . $this->project->directory . '/custom.php', '--chunk', '2/2', '--', 'src']);

        self::assertSame(0, $secondChunk['exitCode'], $secondChunk['output']);
        self::assertStringContainsString('Working on chunk 2 of 2', $secondChunk['output']);
        self::assertStringContainsString('Files 1 of 3', $secondChunk['output']);
        self::assertStringNotContainsString('Total files', $secondChunk['output']);
        self::assertStringContainsString('All chunks done', $secondChunk['output']);
        self::assertTrue($this->project->isListConverted('C'));
    }

    public function testFilesAreSortedBeforeTheyAreSplitIntoChunks(): void
    {
        // rector keeps files given on the command line in the given order
        $result = $this->runRectorP(['--chunk', '1/2', 'src/B.php', 'src/A Space.php']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('Checking file ' . $this->project->filePath('A Space'), $result['output']);
        self::assertStringNotContainsString('Checking file ' . $this->project->filePath('B'), $result['output']);
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function chunkSizes(): iterable
    {
        yield '3 files, the last chunk is smaller' => [3, '2/2', 'Files 1 of 3'];
        yield '5 files, the last chunk is smaller' => [5, '2/2', 'Files 2 of 5'];
        yield '7 files, chunk size is rounded up' => [7, '3/3', 'Files 1 of 7'];
    }

    #[DataProvider('chunkSizes')]
    public function testChunkSizes(int $fileCount, string $chunk, string $expected): void
    {
        foreach (array_slice(['C', 'D', 'E', 'F', 'G'], 0, $fileCount - 2) as $name) {
            $this->project->writeSource($name, true);
        }

        $result = $this->runRectorP(['--chunk', $chunk]);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString($expected, $result['output']);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function verbosities(): iterable
    {
        yield 'normal' => [OutputInterface::VERBOSITY_NORMAL, ''];
        yield 'verbose' => [OutputInterface::VERBOSITY_VERBOSE, ' -v'];
        yield 'very verbose' => [OutputInterface::VERBOSITY_VERY_VERBOSE, ' -vv'];
        yield 'debug' => [OutputInterface::VERBOSITY_DEBUG, ' -vvv'];
    }

    #[DataProvider('verbosities')]
    public function testNextChunkCommandKeepsTheVerbosity(int $verbosity, string $option): void
    {
        $result = $this->runRectorP(['--chunk', '1/2'], verbosity: $verbosity);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString(PHP_EOL . 'vendor/bin/rector-p' . $option . ' --chunk 2/2' . PHP_EOL, $result['output']);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function quietVerbosities(): iterable
    {
        yield 'quiet' => [OutputInterface::VERBOSITY_QUIET];
        // can remove defined check if Symfony 7.2 is lowest supported version:
        if (defined(OutputInterface::class . '::VERBOSITY_SILENT')) {
            yield 'silent' => [OutputInterface::VERBOSITY_SILENT];
        }
    }

    #[DataProvider('quietVerbosities')]
    public function testQuietChunkRunPrintsNoNextChunkCommand(int $verbosity): void
    {
        $result = $this->runRectorP(['--chunk', '1/2'], verbosity: $verbosity);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringNotContainsString('--chunk 2/2', $result['output']);
        self::assertTrue($this->project->isListConverted('A Space'));
    }

    public function testChunkOneMeansAllFiles(): void
    {
        $result = $this->runRectorP(['--chunk', '1']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('Total files 2', $result['output']);
        self::assertStringContainsString('All chunks done', $result['output']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidChunks(): iterable
    {
        yield 'dash' => ['1-2'];
        yield 'text after' => ['1/2x'];
        yield 'text before' => ['x1/2'];
    }

    #[DataProvider('invalidChunks')]
    public function testInvalidChunkThrows(string $chunk): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid chunk config given ' . $chunk);

        $this->runRectorP(['--chunk', $chunk]);
    }

    public function testMoreChunksThanFilesThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Total files 2 is less than total chunks 3');

        $this->runRectorP(['--chunk', '1/3']);
    }

    public function testInvalidChunkIsReportedBeforeTheFilesAreListed(): void
    {
        mkdir($this->project->directory . '/empty');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid chunk config given 1-2');

        $this->runRectorP(['--chunk', '1-2', 'empty']);
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function missingPaths(): iterable
    {
        yield 'typo' => [['src/Controler'], 'src/Controler'];
        yield 'glob pattern without match' => [['lib*'], 'lib*'];
        yield 'one of two' => [['src', 'tests'], 'tests'];
    }

    /**
     * @param list<string> $paths
     */
    #[DataProvider('missingPaths')]
    public function testPathsThatMatchNothingFail(array $paths, string $expected): void
    {
        $result = $this->runRectorP($paths);

        self::assertSame(1, $result['exitCode'], $result['output']);
        self::assertStringContainsString('These paths match no file or directory: ' . $expected . PHP_EOL, $result['output']);
        self::assertSame([], $this->project->rectorCalls());
    }

    public function testGlobPatternsAreAccepted(): void
    {
        $result = $this->runRectorP(['s*c/A*']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('Total files 1', $result['output']);
        self::assertTrue($this->project->isListConverted('A Space'));
    }

    public function testFileExtensionsOfTheConfigAreUsed(): void
    {
        file_put_contents(
            $this->project->directory . '/extensions.php',
            '<?php return ' . RectorConfig::class . "::configure()->withPaths([__DIR__ . '/src'])->withFileExtensions(['php', 'phtml']);",
        );
        file_put_contents($this->project->directory . '/src/view.phtml', "<?php list(\$a, \$b) = [1, 2]; ?>\n");

        $result = $this->runRectorP(['-c', 'extensions.php']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('Total files 3', $result['output']);
        self::assertStringContainsString('[$a, $b]', (string)file_get_contents($this->project->directory . '/src/view.phtml'));
    }

    /**
     * rector leaves out files that its result cache marks as unchanged.
     * rector-p must list all files and must not touch that cache.
     */
    public function testListsAllFilesWithoutTouchingTheResultCacheOfRector(): void
    {
        $this->project->writeSource('A Space', true);
        $this->project->writeSource('B', true);
        $this->project->writeSource('C');
        // the real rector caches A Space and B as unchanged, C has changes
        self::assertSame(2, $this->runRealRector(['process', '--dry-run', '--no-progress-bar', '--config=custom.php']));
        $rectorCacheFiles = $this->filesIn($this->project->directory . '/var/rector-cache');
        self::assertNotSame([], $rectorCacheFiles);
        $temporaryDirectories = glob(sys_get_temp_dir() . '/rector-p-file-list-*') ?: [];

        $result = $this->runRectorP(['-c', 'custom.php']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('Total files 3', $result['output']);
        self::assertSame($rectorCacheFiles, $this->filesIn($this->project->directory . '/var/rector-cache'));
        self::assertSame($temporaryDirectories, glob(sys_get_temp_dir() . '/rector-p-file-list-*') ?: [], 'rector-p must remove its own cache directory');
    }

    public function testNextChunkCommandQuotesTheBinaryIfNeeded(): void
    {
        $result = $this->runRectorP(['--chunk', '1/2'], binary: '/my project/vendor/bin/rector-p');

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString(PHP_EOL . "'/my project/vendor/bin/rector-p' --chunk 2/2" . PHP_EOL, $result['output']);
    }

    public function testEmptyFileListIsNoError(): void
    {
        mkdir($this->project->directory . '/empty');

        $result = $this->runRectorP(['empty']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('No files found', $result['output']);
        self::assertStringNotContainsString('Total files', $result['output']);
    }

    public function testConfigWithoutPathsThrows(): void
    {
        file_put_contents($this->project->directory . '/no-paths.php', '<?php return ' . RectorConfig::class . '::configure();');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No paths found in configuration ' . $this->project->directory . '/no-paths.php');

        $this->runRectorP(['-c', 'no-paths.php']);
    }

    public function testUsesRectorFromTheVendorBinDirectoryOfTheProject(): void
    {
        unset($GLOBALS['_composer_bin_dir']);
        mkdir($this->project->directory . '/vendor/bin', 0777, true);
        symlink($this->fakeBinDirectory . '/rector', $this->project->directory . '/vendor/bin/rector');

        $result = $this->runRectorP(['-c', 'custom.php'], verbosity: OutputInterface::VERBOSITY_VERY_VERBOSE);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString("'vendor/bin/rector' process", $result['output']);
        self::assertTrue($this->project->isListConverted('A Space'));
    }

    public function testUsesRectorFromThePath(): void
    {
        unset($GLOBALS['_composer_bin_dir']);
        $path = (string)getenv('PATH');
        putenv('PATH=' . $this->fakeBinDirectory . PATH_SEPARATOR . $path);

        try {
            $result = $this->runRectorP(['-c', 'custom.php'], verbosity: OutputInterface::VERBOSITY_VERY_VERBOSE);
        } finally {
            putenv('PATH=' . $path);
        }

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString("'rector' process", $result['output']);
        self::assertTrue($this->project->isListConverted('A Space'));
    }

    private function useRealRector(): void
    {
        $GLOBALS['_composer_bin_dir'] = (string)realpath(__DIR__ . '/../vendor/bin');
    }

    /**
     * @param list<string> $arguments
     * @return int the exit code
     */
    private function runRealRector(array $arguments): int
    {
        $process = proc_open(
            [PHP_BINARY, (string)realpath(__DIR__ . '/../vendor/bin/rector'), ...$arguments],
            // all descriptors are given: under Infection the test process has no usable stdin
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            $this->project->directory,
        );
        self::assertIsResource($process);

        return proc_close($process);
    }

    /**
     * @return list<string>
     */
    private function filesIn(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            assert($file instanceof SplFileInfo);
            $files[] = $file->getPathname();
        }

        sort($files);
        return $files;
    }

    /**
     * @param array{exitCode: int, output: string} $result
     */
    private function checkedFiles(array $result): int
    {
        self::assertSame(0, $result['exitCode'], $result['output']);

        return substr_count($result['output'], 'Checking file ');
    }

    /**
     * @param list<string> $arguments
     * @param list<string> $answers answers to the questions; without answers the command runs without interaction
     * @param string $binary how rector-p was started
     * @return array{exitCode: int, output: string}
     */
    private function runRectorP(
        array $arguments,
        array $answers = [],
        int $verbosity = OutputInterface::VERBOSITY_NORMAL,
        string $binary = 'vendor/bin/rector-p',
    ): array {
        $argv = [$binary, ...$arguments];
        $previousArgv = $_SERVER['argv'];
        $previousDirectory = (string)getcwd();
        // rector reads --config from the command line of the process
        $_SERVER['argv'] = $argv;
        // rector keeps its parameters in a static class, paths of an earlier run must not leak into this one
        SimpleParameterProvider::setParameter(Option::PATHS, []);
        chdir($this->project->directory);

        $answerStream = fopen('php://memory', 'r+');
        self::assertIsResource($answerStream);
        fwrite($answerStream, implode(PHP_EOL, $answers) . PHP_EOL);
        rewind($answerStream);

        $input = new ArgvInput($argv);
        $input->setInteractive($answers !== []);
        $input->setStream($answerStream);

        $output = new BufferedOutput($verbosity);

        ob_start();
        try {
            $command = RectorPApplication::createApplication(new Cache($this->project->progressDirectory))->find('partial');
            $exitCode = $command->run($input, $output);
        } finally {
            // the output of the rector runs (passthru)
            $rectorOutput = (string)ob_get_clean();
            chdir($previousDirectory);
            $_SERVER['argv'] = $previousArgv;
        }

        return ['exitCode' => $exitCode, 'output' => $output->fetch() . $rectorOutput];
    }
}
