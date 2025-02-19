<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP;

use Andersundsehr\RectorP\Dto\ChunkConfig;
use Andersundsehr\RectorP\Dto\Task;
use Andersundsehr\RectorP\Helper\TimeHelper;
use InvalidArgumentException;
use Rector\Bootstrap\RectorConfigsResolver;
use Rector\Config\RectorConfig;
use Rector\Configuration\ConfigInitializer;
use Rector\Configuration\ConfigurationFactory;
use Rector\Configuration\Option;
use Rector\Configuration\Parameter\SimpleParameterProvider;
use Rector\DependencyInjection\RectorContainerFactory;
use Rector\FileSystem\FilesFinder;
use Rector\StaticReflection\DynamicSourceLocatorDecorator;
use Rector\ValueObject\Configuration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

final class PartialCommand extends Command
{
    private InputInterface $input;

    private OutputInterface $output;

    private readonly float $startTime;

    public function __construct(private readonly Cache $cache)
    {
        parent::__construct('partial');
        $this->startTime = microtime(true);
    }

    protected function configure(): void
    {
        $this->addArgument(Option::SOURCE, InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Files or directories to be upgraded.');
        $this->addOption(
            'startOver',
            's',
            InputOption::VALUE_NONE,
            'Start over with the first file (be default rector-p keeps a record of files that have no changes in them)'
        );
        $this->addOption('chunk', 'p', InputOption::VALUE_REQUIRED, 'chunk(part) definition eg 1/2 (first half) or 2/2 (second half) or 3/10 (third tenth)', '1/1');
        $this->addOption(Option::CONFIG, 'c', InputOption::VALUE_REQUIRED, 'Path to config file', getcwd() . '/rector.php');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        if ($input->getOption('startOver')) {
            $this->output->writeln('Starting over (cleared the processed cache)');
            $this->cache->clear();
        }

        $allFiles = $this->getAllFiles($this->input->getArgument(Option::SOURCE));

        $chunkConfig = $this->parseChunkConfig($input->getOption('chunk'));
        if ($chunkConfig->chunkNumber === 1) {
            $this->output->writeln('Total files ' . count($allFiles));
        } else {
            $this->output->writeln('Working on chunk ' . $chunkConfig->chunkNumber . ' of ' . $chunkConfig->totalChunks);
        }

        $chunkedFiles = $this->chunkFiles($allFiles, $chunkConfig);
        if ($chunkConfig->chunkNumber !== 1) {
            $this->output->writeln('Files ' . count($chunkedFiles) . ' of ' . count($allFiles));
        }

        $this->workOnFiles($chunkedFiles);

        $this->output->writeln('Done with chunk ' . $chunkConfig->chunkNumber . ' of ' . $chunkConfig->totalChunks);
        if ($chunkConfig->chunkNumber === $chunkConfig->totalChunks) {
            $this->output->writeln('All chunks done');
        } else {
            $verbosity = match ($output->getVerbosity()) {
                // can remove defined check if Symfony 7.2 is lowest supported version:
                defined(OutputInterface::class . '::VERBOSITY_SILENT') ? OutputInterface::VERBOSITY_SILENT : 8 => '--silent',
                OutputInterface::VERBOSITY_QUIET => '-q',
                OutputInterface::VERBOSITY_NORMAL => '',
                OutputInterface::VERBOSITY_VERBOSE => ' -v',
                OutputInterface::VERBOSITY_VERY_VERBOSE => ' -vv',
                OutputInterface::VERBOSITY_DEBUG => ' -vvv',
            };
            $cmd = $_SERVER['argv'][0] . ' ' . $verbosity . ' --chunk ' . $chunkConfig->increaseChunk()->__toString();
            $this->output->writeln('you can now start the next chunk:' . PHP_EOL . '<fg=gray>' . $cmd . '</>');
        }

        return self::SUCCESS;
    }

    /**
     * @param list<string> $sources
     * @return list<string>
     */
    private function getAllFiles(array $sources): array
    {
        $bootstrapConfigs = (new RectorConfigsResolver())->provide();

        $configFunction = require $bootstrapConfigs->getMainConfigFile();
        if (is_callable($configFunction)) {
            $configFunction(new RectorConfig());
        }

        if ($sources) {
            $paths = $sources;
        } else {
            $paths = SimpleParameterProvider::provideArrayParameter(Option::PATHS);
            if (!$paths) {
                throw new InvalidArgumentException('No paths found in configuration ' . $bootstrapConfigs->getMainConfigFile());
            }
        }

        $rectorContainer = (new RectorContainerFactory())->createFromBootstrapConfigs($bootstrapConfigs);
        $filesFinder = $rectorContainer->get(FilesFinder::class);

        return $filesFinder->findFilesInPaths($paths, new Configuration(shouldClearCache: false));
    }

    /**
     * @param list<string> $allFiles
     * @return list<string>
     */
    private function chunkFiles(array $allFiles, ChunkConfig $chunkConfig): array
    {
        sort($allFiles);

        $totalFiles = count($allFiles);
        if ($totalFiles < $chunkConfig->totalChunks) {
            throw new InvalidArgumentException('Total files ' . $totalFiles . ' is less than total chunks ' . $chunkConfig->totalChunks);
        }

        $chunkSize = (int)ceil($totalFiles / $chunkConfig->totalChunks);

        $start = $chunkSize * ($chunkConfig->chunkNumber - 1);
        $max = $start + $chunkSize;

        $chunkedFiles = [];
        for ($i = $start; $i < $max; $i++) {
            if (!isset($allFiles[$i])) {
                return $chunkedFiles;
            }

            $chunkedFiles[] = $allFiles[$i];
        }

        return $chunkedFiles;
    }

    private function parseChunkConfig(string $chunk): ChunkConfig
    {
        if ($chunk === '1') {
            return new ChunkConfig(1, 1);
        }

        if (preg_match('/^(\d+)\/(\d+)$/', $chunk, $matches)) {
            return new ChunkConfig((int)$matches[1], (int)$matches[2]);
        }

        throw new InvalidArgumentException('Invalid chunk config given ' . $chunk);
    }

    /**
     * @param list<string> $chunkedFiles
     */
    private function workOnFiles(array $chunkedFiles): void
    {
        $timeCountMax = count($chunkedFiles);
        $timeIndex = 0;
        foreach (array_values($chunkedFiles) as $index => $file) {
            if ($this->cache->isProcessed($file)) {
                $timeCountMax--;
                continue;
            }

            $this->workOnFile($file);

            $durationTotal = microtime(true) - $this->startTime;
            $timePerFile = $durationTotal / (++$timeIndex);
            $timeLeft = $timePerFile * ($timeCountMax - $timeIndex);

            $countPart = '<options=bold>' . ($index + 1) . '</>/' . count($chunkedFiles);
            $timePart = '<options=bold>~' . TimeHelper::secondsToHuman((int)ceil($timeLeft)) . '</>';
            $this->output->writeln('done with ' . $countPart . ' files ' . $timePart . ' to finish');
        }
    }

    private function workOnFile(string $file): void
    {
        if ($this->dryRunRector($file)) {
            $this->cache->setProcessed($file);
            return;
        }

        match ($this->askTask($file)) {
            Task::execute => $this->executeRector($file),
            Task::retry => $this->workOnFile($file),
            Task::skipFile => false,
            Task::quit => exit(0),
        };
    }

    private function askTask(string $file): Task
    {
        $default = Task::execute->value;
        $choices = array_map(static fn($task) => $task->value, Task::cases());
        $question = new ChoiceQuestion('Found changes in file ' . $file . ' (default: ' . $default . ')?', $choices, $default);

        $questionHelper = $this->getHelper('question');
        assert($questionHelper instanceof QuestionHelper);
        $result = $questionHelper->ask($this->input, $this->output, $question);
        return Task::from($result);
    }

    /***
     * Execute Commands
     */

    private function dryRunRector(string $file): bool
    {
        $this->output->writeln('<fg=white;bg=blue>Checking file ' . $file . ' ...</>');
        $exitCode = $this->runCommand($this->getBinPath() . "rector process --ansi --no-progress-bar --dry-run " . $file);
        return $exitCode === 0;
    }

    private function executeRector(string $file): void
    {
        $exitCode = $this->runCommand($this->getBinPath() . "rector process --ansi --no-progress-bar --no-diffs " . $file);
        if ($exitCode) {
            exit($exitCode);
        }

        $this->cache->setProcessed($file);
    }

    private function runCommand(string $command): int
    {
        $this->output->writeln('<fg=gray>' . $command . '</>', OutputInterface::VERBOSITY_VERY_VERBOSE);
        $startTime = microtime(true);
        passthru($command, $exitCode);
        $duration = microtime(true) - $startTime;
        $this->output->writeln('<fg=gray>duration: ' . round($duration, 3) . 's</>', OutputInterface::VERBOSITY_DEBUG);
        return $exitCode;
    }

    private function getBinPath(): string
    {
        if (isset($GLOBALS['_composer_bin_dir'])) {
            return $GLOBALS['_composer_bin_dir'] . '/';
        }

        if (file_exists('vendor/bin/rector')) {
            return 'vendor/bin/';
        }

        return '';
    }
}
