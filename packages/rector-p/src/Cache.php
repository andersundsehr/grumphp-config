<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP;

use function array_filter;
use function hash_file;

final class Cache
{
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
        unlink($this->getCacheFilePath());
    }

    private function getCacheFilePath(): string
    {
        return __DIR__ . '/../cache.json';
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
