<?php

return $GLOBALS['andersundsehr/phpstan-git-files.memory-cache'] ??= (function (): array {
    $exec = fn(string $command): array => array_filter(explode("\n", (string)shell_exec($command)));

    $command = <<<BASH
    git ls-files | xargs ls -d 2>/dev/null | grep '\.php$' | awk -v cwd="$(pwd)" '{print cwd "/" $0}'
BASH;
    $absoluteFiles = $exec($command);

    // sort paths by amount of slashes high count first
    usort($absoluteFiles, static fn($a, $b): int => substr_count($b, '/') <=> substr_count($a, '/'));
    $deepestDirectory = dirname($absoluteFiles[0]) . '/';

    //filter all files in that directory
    $absoluteFiles = array_filter($absoluteFiles, static fn($path): bool => !str_starts_with($path, $deepestDirectory));
    // add directory, We add this so the cache is not ignored. see https://github.com/andersundsehr/phpstan-git-files/issues/3
    array_unshift($absoluteFiles, $deepestDirectory);

    $command = <<<BASH
    git status --ignored --porcelain {$deepestDirectory} | awk -v cwd="$(pwd)" '/^!!/ {print cwd "/" $2}'
BASH;
    $absoluteIgnoredFiles = $exec($command);

    return [
        'parameters' => [
            'paths' => $absoluteFiles,
            'excludePaths' => [
                'analyse' => $absoluteIgnoredFiles,
            ],
        ],
    ];
})();
