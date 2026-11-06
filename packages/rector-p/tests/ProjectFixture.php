<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP\Tests;

use Rector\Php70\Rector\Ternary\TernaryToNullCoalescingRector;
use Rector\Php71\Rector\List_\ListToArrayDestructRector;
use RuntimeException;

/**
 * a small temporary project with two rector configs and two PHP files
 *
 * rector.dist.php replaces isset() ternaries with ??, custom.php replaces list() with [].
 * The space in the path makes sure that paths are escaped for the shell.
 */
final readonly class ProjectFixture
{
    private const CONFIG = <<<'PHP'
        <?php
        return Rector\Config\RectorConfig::configure()
            ->withPaths([__DIR__ . '/src'])
            ->withCache(__DIR__ . '/var/rector-cache')
            ->withRules([%s::class]);
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

    public string $directory;

    /**
     * where rector-p stores its progress
     */
    public string $progressDirectory;

    private string $baseDirectory;

    public function __construct()
    {
        $this->baseDirectory = sys_get_temp_dir() . '/rector-p-test-' . bin2hex(random_bytes(8));
        mkdir($this->baseDirectory . '/my project/src', 0777, true);
        mkdir($this->baseDirectory . '/progress');
        $this->directory = $this->realPath($this->baseDirectory . '/my project');
        $this->progressDirectory = $this->realPath($this->baseDirectory . '/progress');

        $this->writeConfig('rector.dist.php', TernaryToNullCoalescingRector::class);
        $this->writeConfig('custom.php', ListToArrayDestructRector::class);
        $this->writeSource('A Space');
        $this->writeSource('B');
    }

    public function writeConfig(string $fileName, string $ruleClass): void
    {
        file_put_contents($this->directory . '/' . $fileName, sprintf(self::CONFIG, $ruleClass));
    }

    /**
     * @param bool $listConverted true: the file has nothing to change for custom.php
     */
    public function writeSource(string $name, bool $listConverted = false): void
    {
        $source = sprintf(self::SOURCE, 'f' . str_replace(' ', '', $name));
        if ($listConverted) {
            $source = str_replace('list($a, $b) = $arr;', '[$a, $b] = $arr;', $source);
        }

        file_put_contents($this->filePath($name), $source);
    }

    public function filePath(string $name): string
    {
        return $this->directory . '/src/' . $name . '.php';
    }

    public function source(string $name): string
    {
        return (string)file_get_contents($this->filePath($name));
    }

    public function isListConverted(string $name): bool
    {
        return str_contains($this->source($name), '[$a, $b] = $arr;');
    }

    public function isTernaryConverted(string $name): bool
    {
        return str_contains($this->source($name), "\$c = \$arr['c'] ?? 0;");
    }

    public function remove(): void
    {
        $this->removeDirectory($this->baseDirectory);
    }

    private function realPath(string $path): string
    {
        $realPath = realpath($path);
        if ($realPath === false) {
            throw new RuntimeException('Could not resolve ' . $path);
        }

        return $realPath;
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
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
