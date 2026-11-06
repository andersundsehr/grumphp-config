<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP;

use Symfony\Component\Console\Application;

final class RectorPApplication
{
    public static function createApplication(Cache $cache = new Cache()): Application
    {
        $application = new Application('rector-p');
        $application->add(new PartialCommand($cache));
        $application->setDefaultCommand('partial', true);
        return $application;
    }
}
