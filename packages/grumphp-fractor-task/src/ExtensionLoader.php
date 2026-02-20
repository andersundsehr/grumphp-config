<?php

declare(strict_types=1);

namespace Andersundsehr\GrumPHPFractorTask;

use Iterator;
use GrumPHP\Extension\ExtensionInterface;

final class ExtensionLoader implements ExtensionInterface
{
    public function imports(): Iterator
    {
        yield __DIR__ . '/../Services.yaml';
    }
}
