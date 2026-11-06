<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP;

use function array_filter;
use function hash_file;

final class Cache
{
    private string $cacheFileName = 'cache.json';

    /**
     * @param string $directory where the progress is stored, the package directory by default
     */
    public function __construct(private readonly string $directory = __DIR__ . '/..')
    {
    }

    /**
     * every rector config has its own progress,
     * rector.php in the current directory keeps cache.json, so the progress of older versions is kept
     */
    public function useConfigFile(?string $configFile): void
    {
        $this->cacheFileName = 'cache.json';
        if ($configFile !== null && $configFile !== getcwd() . DIRECTORY_SEPARATOR . 'rector.php') {
            $this->cacheFileName = 'cache-' . hash('sha256', $configFile) . '.json';
        }
    }

    public function setProcessed(string $fileName): void
    {
        $data = $this->getData();
        $data[$fileName] = hash_file('sha256', $fileName);
        file_put_contents($this->getCacheFilePath(), json_encode($data, flags: JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function isProcessed(string $fileName): bool
    {
        $hash = $this->getData()[$fileName] ?? '';
        return $hash === hash_file('sha256', $fileName);
    }

    public function clear(): void
    {
        if (file_exists($this->getCacheFilePath())) {
            unlink($this->getCacheFilePath());
        }
    }

    private function getCacheFilePath(): string
    {
        return $this->directory . '/' . $this->cacheFileName;
    }

    /**
     * @return array<string, string>
     */
    private function getData(): array
    {
        if (!file_exists($this->getCacheFilePath())) {
            return [];
        }

        $contents = file_get_contents($this->getCacheFilePath());
        assert(is_string($contents));
        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        assert(is_array($data));
        return array_filter($data, is_string(...));
    }
}
