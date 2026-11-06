<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP;

use Andersundsehr\RectorP\Dto\ChunkConfig;
use Andersundsehr\RectorP\Dto\Task;
use Andersundsehr\RectorP\Exception\StopException;
use Andersundsehr\RectorP\Helper\TimeHelper;
use InvalidArgumentException;
use Rector\Bootstrap\RectorConfigsResolver;
use Rector\Config\RectorConfig;
use Rector\Configuration\Option;
use Rector\Configuration\Parameter\SimpleParameterProvider;
use Rector\Console\ExitCode;
use Rector\DependencyInjection\RectorContainerFactory;
use Rector\FileSystem\FilesFinder;
use Rector\ValueObject\Bootstrap\BootstrapConfigs;
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

    /**
     * the rector config that was used to find the files (--config, rector.php or rector.dist.php)
     */
    private ?string $configFile = null;

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

        $bootstrapConfigs = (new RectorConfigsResolver())->provide();
        $this->configFile = $bootstrapConfigs->getMainConfigFile();
        $this->cache->useConfigFile($this->configFile);

        if ($input->getOption('startOver')) {
            $this->output->writeln('Starting over (cleared the processed cache)');
            $this->cache->clear();
        }

        $chunkConfig = $this->parseChunkConfig($input->getOption('chunk'));

        $sources = $this->input->getArgument(Option::SOURCE);
        $missingSources = $this->missingSources($sources);
        if ($missingSources !== []) {
            $this->output->writeln('<error>These paths match no file or directory: ' . implode(', ', $missingSources) . '</error>');
            return self::FAILURE;
        }

        $allFiles = $this->getAllFiles($sources, $bootstrapConfigs);
        if ($allFiles === []) {
            $this->output->writeln('No files found');
            return self::SUCCESS;
        }

        if ($chunkConfig->chunkNumber === 1) {
            $this->output->writeln('Total files ' . count($allFiles));
        } else {
            $this->output->writeln('Working on chunk ' . $chunkConfig->chunkNumber . ' of ' . $chunkConfig->totalChunks);
        }

        $chunkedFiles = $this->chunkFiles($allFiles, $chunkConfig);
        if ($chunkConfig->chunkNumber !== 1) {
            $this->output->writeln('Files ' . count($chunkedFiles) . ' of ' . count($allFiles));
        }

        try {
            $this->workOnFiles($chunkedFiles);
        } catch (StopException $stopException) {
            if ($stopException->getMessage() !== '') {
                $this->output->writeln('<error>' . $stopException->getMessage() . '</error>');
            }

            return $stopException->getCode();
        }

        $this->output->writeln('Done with chunk ' . $chunkConfig->chunkNumber . ' of ' . $chunkConfig->totalChunks);
        if ($chunkConfig->chunkNumber === $chunkConfig->totalChunks) {
            $this->output->writeln('All chunks done');
        } else {
            $this->output->writeln('you can now start the next chunk:');
            $this->output->writeln('<fg=gray>' . $this->nextChunkCommand($chunkConfig) . '</>');
        }

        return self::SUCCESS;
    }

    /**
     * the command for the next chunk keeps the verbosity, the config and the paths, so it works on the same file list
     */
    private function nextChunkCommand(ChunkConfig $chunkConfig): string
    {
        $parts = [$this->shellArgument($_SERVER['argv'][0])];

        $verbosity = match ($this->output->getVerbosity()) {
            // can remove defined check if Symfony 7.2 is lowest supported version:
            defined(OutputInterface::class . '::VERBOSITY_SILENT') ? OutputInterface::VERBOSITY_SILENT : 8 => '--silent',
            OutputInterface::VERBOSITY_QUIET => '-q',
            OutputInterface::VERBOSITY_NORMAL => '',
            OutputInterface::VERBOSITY_VERBOSE => '-v',
            OutputInterface::VERBOSITY_VERY_VERBOSE => '-vv',
            OutputInterface::VERBOSITY_DEBUG => '-vvv',
        };
        if ($verbosity !== '') {
            $parts[] = $verbosity;
        }

        $configIsGiven = $this->input->getOption(Option::CONFIG) !== $this->getDefinition()->getOption(Option::CONFIG)->getDefault();
        if ($configIsGiven && $this->configFile !== null) {
            $parts[] = '--config=' . escapeshellarg($this->configFile);
        }

        $parts[] = '--chunk ' . $chunkConfig->increaseChunk()->__toString();

        $sources = $this->input->getArgument(Option::SOURCE);
        assert(is_array($sources));
        if ($sources !== []) {
            $parts[] = '--';
            foreach ($sources as $source) {
                assert(is_string($source));
                $parts[] = escapeshellarg($source);
            }
        }

        return implode(' ', $parts);
    }

    /**
     * keeps simple arguments readable, e.g. vendor/bin/rector-p
     */
    private function shellArgument(string $argument): string
    {
        if (preg_match('#^[\w./-]+$#', $argument)) {
            return $argument;
        }

        return escapeshellarg($argument);
    }

    /**
     * like rector, rector-p accepts glob patterns, e.g. src/*Bundle
     *
     * @param list<string> $sources
     * @return list<string>
     */
    private function missingSources(array $sources): array
    {
        $missingSources = [];
        foreach ($sources as $source) {
            if (!file_exists($source) && (glob($source) ?: []) === []) {
                $missingSources[] = $source;
            }
        }

        return $missingSources;
    }

    /**
     * @param list<string> $sources
     * @return list<string>
     */
    private function getAllFiles(array $sources, BootstrapConfigs $bootstrapConfigs): array
    {
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

        // without the main config file, the container does not check the result cache of the project
        $rectorContainer = (new RectorContainerFactory())->createFromBootstrapConfigs(new BootstrapConfigs(null, $bootstrapConfigs->getConfigFiles()));
        $configuration = new Configuration(fileExtensions: SimpleParameterProvider::provideArrayParameter(Option::FILE_EXTENSIONS));

        // rector leaves out files that its result cache marks as unchanged, so the list would depend on earlier runs
        // and the chunks would shift. rector-p lists the files with an empty cache of its own: its progress decides
        // which files are left, and the result cache of the project stays as it is.
        $cacheDirectory = sys_get_temp_dir() . '/rector-p-file-list-' . bin2hex(random_bytes(8));
        SimpleParameterProvider::setParameter(Option::CACHE_DIR, $cacheDirectory);
        try {
            return $rectorContainer->get(FilesFinder::class)->findFilesInPaths($paths, $configuration);
        } finally {
            // rector creates the directory, listing the files leaves it empty
            rmdir($cacheDirectory);
        }
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
        foreach ($chunkedFiles as $index => $file) {
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
            Task::skipFile => null,
            Task::quit => throw new StopException('', self::SUCCESS),
        };
    }

    private function askTask(string $file): Task
    {
        $default = Task::execute->value;
        $choices = array_map(static fn(Task $task) => $task->value, Task::cases());
        $question = new ChoiceQuestion('Found changes in file ' . $file . ' (default: ' . $default . ')?', $choices, $default);

        $questionHelper = $this->getHelper('question');
        assert($questionHelper instanceof QuestionHelper);
        $result = $questionHelper->ask($this->input, $this->output, $question);
        return Task::from($result);
    }

    /***
     * Execute Commands
     */

    /**
     * @return bool true if rector has nothing to change in the file
     */
    private function dryRunRector(string $file): bool
    {
        $this->output->writeln('<fg=white;bg=blue>Checking file ' . $file . ' ...</>');
        $exitCode = $this->runCommand($this->rectorCommand('--dry-run', $file));
        if ($exitCode === ExitCode::CHANGED_CODE) {
            return false;
        }

        if ($exitCode !== ExitCode::SUCCESS) {
            // e.g. a broken config or a file rector can not parse, this is no change that could be applied
            throw StopException::rectorFailed($file, $exitCode);
        }

        return true;
    }

    private function executeRector(string $file): void
    {
        $exitCode = $this->runCommand($this->rectorCommand('--no-diffs', $file));
        if ($exitCode !== ExitCode::SUCCESS) {
            throw StopException::rectorFailed($file, $exitCode);
        }

        $this->cache->setProcessed($file);
    }

    /**
     * every rector run uses the config that was used to find the files
     */
    private function rectorCommand(string $mode, string $file): string
    {
        $command = escapeshellarg($this->getBinPath() . 'rector') . ' process --ansi --no-progress-bar ' . $mode;
        if ($this->configFile !== null) {
            $command .= ' --config=' . escapeshellarg($this->configFile);
        }

        return $command . ' ' . escapeshellarg($file);
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
