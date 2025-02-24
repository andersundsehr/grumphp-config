<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP\Dto;

enum Task: string
{
    case execute = 'execute';
    case skipFile = 'skip-file';
    case retry = 'retry';
    case quit = 'quit';
}
