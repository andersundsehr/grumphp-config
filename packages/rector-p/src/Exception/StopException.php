<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP\Exception;

use RuntimeException;

/**
 * stops rector-p, the command returns the code of the exception as exit code
 */
final class StopException extends RuntimeException
{
    public static function rectorFailed(string $file, int $exitCode): self
    {
        return new self('Rector failed for ' . $file . ' with exit code ' . $exitCode, $exitCode);
    }
}
