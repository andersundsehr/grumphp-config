<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP\Exception;

use RuntimeException;

/**
 * stops rector-p, the command returns the exit code
 */
final class StopException extends RuntimeException
{
    public function __construct(public readonly int $exitCode, string $message = '')
    {
        parent::__construct($message);
    }
}
