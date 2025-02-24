<?php

declare(strict_types=1);

namespace PLUS\GrumPHPBomTask;

use Symfony\Component\Finder\SplFileInfo;
use GrumPHP\Runner\TaskResult;
use GrumPHP\Runner\TaskResultInterface;
use GrumPHP\Task\Config\ConfigOptionsResolver;
use GrumPHP\Task\Config\EmptyTaskConfig;
use GrumPHP\Task\Config\TaskConfigInterface;
use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\GitPreCommitContext;
use GrumPHP\Task\Context\RunContext;
use GrumPHP\Task\TaskInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class BomFixerTask implements TaskInterface
{
    private EmptyTaskConfig|TaskConfigInterface $config;

    public function __construct()
    {
        $this->config = new EmptyTaskConfig();
    }

    public static function getConfigurableOptions(): ConfigOptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(
            [
                'triggered_by' => ['php', 'css', 'scss', 'less', 'json', 'sql', 'yml', 'txt'],
            ]
        );

        $resolver->addAllowedTypes('triggered_by', ['array']);
        return ConfigOptionsResolver::fromOptionsResolver($resolver);
    }

    public function getConfig(): TaskConfigInterface
    {
        return $this->config;
    }

    public function withConfig(TaskConfigInterface $config): TaskInterface
    {
        $new = clone $this;
        $new->config = $config;

        return $new;
    }

    public function canRunInContext(ContextInterface $context): bool
    {
        return $context instanceof RunContext || $context instanceof GitPreCommitContext;
    }

    public function run(ContextInterface $context): TaskResultInterface
    {
        $files = $context->getFiles()->extensions($this->config->getOptions()['triggered_by']);
        if (0 === count($files)) {
            return TaskResult::createSkipped($this, $context);
        }

        if (is_file('./vendor/bin/fixbom')) {
            $fixCommand = './vendor/bin/fixbom';
        } elseif (is_file('./bin/fixbom')) {
            $fixCommand = './bin/fixbom';
        } else {
            $fixCommand = 'fixbom';
        }

        $shouldGetFixedLog = [];
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $execFile = $file->getPathname();
            if ($this->isFileWithBOM($execFile)) {
                $shouldGetFixedLog[] = $execFile . ' has BOM and should be fixed';
                $fixCommand .= " '" . $execFile . "'";
            }
        }

        if (count($shouldGetFixedLog) > 0) {
            $errorMessage = implode(PHP_EOL, $shouldGetFixedLog) . PHP_EOL
                . 'you can use this to fix them:' . PHP_EOL
                . $fixCommand;
            return TaskResult::createFailed($this, $context, $errorMessage);
        }

        return TaskResult::createPassed($this, $context);
    }

    private function isFileWithBOM(string $filename): bool
    {
        return $this->fileInfoSearch($filename, 'BOM');
    }

    private function fileInfoSearch(string $filename, string $search): bool
    {
        $output = [];
        exec('file "' . $filename . '"', $output, $returnVar);
        if ($returnVar !== 0) {
            return false;
        }

        if (empty($output[0])) {
            return false;
        }

        return str_contains($output[0], $search);
    }
}
