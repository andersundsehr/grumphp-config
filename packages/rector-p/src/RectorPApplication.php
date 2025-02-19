<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP;

use Symfony\Component\Console\Application;

final class RectorPApplication
{
    public static function createApplication(): Application
    {
        $application = new Application('rector-p');
        $application->add(new PartialCommand(new Cache()));
        $application->setDefaultCommand('partial', true);
        return $application;
    }
}
