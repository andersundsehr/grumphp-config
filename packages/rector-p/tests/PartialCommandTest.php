<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP\Tests;

use Rector\Config\RectorConfig;
use Andersundsehr\RectorP\Cache;
use Andersundsehr\RectorP\Exception\StopException;
use Andersundsehr\RectorP\PartialCommand;
use Andersundsehr\RectorP\RectorPApplication;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Rector\Configuration\Option;
use Rector\Configuration\Parameter\SimpleParameterProvider;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * runs the partial command in this process, with real rector runs on a small project
 */
#[CoversClass(PartialCommand::class)]
#[CoversClass(StopException::class)]
final class PartialCommandTest extends TestCase
{
    private ProjectFixture $project;

    private string $binDirectory;

    private mixed $previousBinDirectory;

    protected function setUp(): void
    {
        $this->project = new ProjectFixture();
        $this->binDirectory = (string)realpath(__DIR__ . '/../vendor/bin');
        // like vendor/bin/rector-p, so rector is called from the vendor/bin directory of this package
        $this->previousBinDirectory = $GLOBALS['_composer_bin_dir'] ?? null;
        $GLOBALS['_composer_bin_dir'] = $this->binDirectory;
    }

    protected function tearDown(): void
    {
        $GLOBALS['_composer_bin_dir'] = $this->previousBinDirectory;
        if ($this->previousBinDirectory === null) {
            unset($GLOBALS['_composer_bin_dir']);
        }

        $this->project->remove();
    }

    public function testFindsRectorDistPhpAndPassesItToEveryRectorRun(): void
    {
        $result = $this->runRectorP([], verbosity: OutputInterface::VERBOSITY_VERY_VERBOSE);

        self::assertSame(0, $result['exitCode'], $result['output']);
        $rector = escapeshellarg($this->binDirectory . '/rector') . ' process --ansi --no-progress-bar';
        $config = "--config='" . $this->project->directory . "/rector.dist.php'";
        self::assertStringContainsString($rector . ' --dry-run ' . $config . " '" . $this->project->filePath('A Space') . "'", $result['output']);
        self::assertStringContainsString($rector . ' --no-diffs ' . $config . " '" . $this->project->filePath('A Space') . "'", $result['output']);
        self::assertStringContainsString($rector . ' --no-diffs ' . $config . " '" . $this->project->filePath('B') . "'", $result['output']);
        self::assertStringContainsString('Total files 2', $result['output']);
        self::assertStringContainsString('done with 1/2 files', $result['output']);
        self::assertStringContainsString('done with 2/2 files', $result['output']);
        self::assertStringContainsString('Done with chunk 1 of 1', $result['output']);
        self::assertStringContainsString('All chunks done', $result['output']);
        self::assertStringNotContainsString('duration: ', $result['output']);
        self::assertTrue($this->project->isTernaryConverted('A Space'));
        self::assertTrue($this->project->isTernaryConverted('B'));
        self::assertFalse($this->project->isListConverted('A Space'), 'the rules of custom.php must not run');
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

    public function testConfigOptionIsUsedForEveryRectorRun(): void
    {
        $result = $this->runRectorP(['-c', 'custom.php'], verbosity: OutputInterface::VERBOSITY_VERY_VERBOSE);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString("--config='" . $this->project->directory . "/custom.php'", $result['output']);
        foreach (['A Space', 'B'] as $name) {
            self::assertTrue($this->project->isListConverted($name));
            self::assertFalse($this->project->isTernaryConverted($name), 'the rules of rector.dist.php must not run');
        }
    }

    public function testStopsWithTheExitCodeOfRectorWhenRectorFails(): void
    {
        file_put_contents($this->project->filePath('0Broken'), "<?php\nfunction broken( {\n");

        $result = $this->runRectorP([]);

        self::assertSame(1, $result['exitCode'], $result['output']);
        self::assertStringContainsString('Rector failed for ' . $this->project->filePath('0Broken') . ' with exit code 1', $result['output']);
        self::assertStringNotContainsString('Done with chunk', $result['output']);
        self::assertFalse($this->project->isTernaryConverted('A Space'), 'rector-p must stop at the broken file');
    }

    public function testSkipFileLeavesTheFileForTheNextRun(): void
    {
        $result = $this->runRectorP([], ['skip-file', 'skip-file']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('Found changes in file ' . $this->project->filePath('A Space') . ' (default: execute)?', $result['output']);
        self::assertFalse($this->project->isTernaryConverted('A Space'));
        self::assertFalse($this->project->isTernaryConverted('B'));
        self::assertSame(2, $this->checkedFiles($this->runRectorP([])), 'skipped files are not done');
    }

    public function testRetryChecksTheFileAgain(): void
    {
        $result = $this->runRectorP([], ['retry', 'execute', 'execute']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertSame(2, substr_count($result['output'], 'Checking file ' . $this->project->filePath('A Space')));
        self::assertSame(1, substr_count($result['output'], 'Checking file ' . $this->project->filePath('B')));
        self::assertTrue($this->project->isTernaryConverted('A Space'));
        self::assertTrue($this->project->isTernaryConverted('B'));
    }

    public function testQuitStopsWithoutChangingTheFile(): void
    {
        $result = $this->runRectorP([], ['quit']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertFalse($this->project->isTernaryConverted('A Space'));
        self::assertStringNotContainsString('Checking file ' . $this->project->filePath('B'), $result['output']);
        self::assertStringNotContainsString('Done with chunk', $result['output']);
    }

    public function testEmptyAnswerExecutes(): void
    {
        $result = $this->runRectorP([], ['', '']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertTrue($this->project->isTernaryConverted('A Space'));
        self::assertTrue($this->project->isTernaryConverted('B'));
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

    public function testChunksCoverAllFilesAfterRectorCachedTheFirstChunk(): void
    {
        // the first chunk has nothing to change, so rector caches its files as unchanged
        $this->project->writeSource('A Space', true);
        $this->project->writeSource('B', true);
        $this->project->writeSource('C');
        $this->project->writeSource('D');

        $this->runRectorP(['-c', 'custom.php', '--chunk', '1/2']);
        $secondChunk = $this->runRectorP(['-c', 'custom.php', '--chunk', '2/2']);

        self::assertStringContainsString('Files 2 of 4', $secondChunk['output']);
        self::assertTrue($this->project->isListConverted('C'), 'C was skipped');
        self::assertTrue($this->project->isListConverted('D'));
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

    public function testChunkOneMeansAllFiles(): void
    {
        $result = $this->runRectorP(['--chunk', '1']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('Total files 2', $result['output']);
        self::assertStringContainsString('All chunks done', $result['output']);
    }

    public function testInvalidChunkThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid chunk config given 1-2');

        $this->runRectorP(['--chunk', '1-2']);
    }

    public function testMoreChunksThanFilesThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Total files 2 is less than total chunks 3');

        $this->runRectorP(['--chunk', '1/3']);
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
        symlink($this->binDirectory . '/rector', $this->project->directory . '/vendor/bin/rector');

        $result = $this->runRectorP(['-c', 'custom.php'], verbosity: OutputInterface::VERBOSITY_VERY_VERBOSE);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString("'vendor/bin/rector' process", $result['output']);
        self::assertTrue($this->project->isListConverted('A Space'));
    }

    public function testUsesRectorFromThePath(): void
    {
        unset($GLOBALS['_composer_bin_dir']);
        $path = (string)getenv('PATH');
        putenv('PATH=' . $this->binDirectory . PATH_SEPARATOR . $path);

        try {
            $result = $this->runRectorP(['-c', 'custom.php'], verbosity: OutputInterface::VERBOSITY_VERY_VERBOSE);
        } finally {
            putenv('PATH=' . $path);
        }

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString("'rector' process", $result['output']);
        self::assertTrue($this->project->isListConverted('A Space'));
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
     * @return array{exitCode: int, output: string}
     */
    private function runRectorP(array $arguments, array $answers = [], int $verbosity = OutputInterface::VERBOSITY_NORMAL): array
    {
        $argv = ['vendor/bin/rector-p', ...$arguments];
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
