<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;

final class RectorPApplication
{
    public static function createApplication(Cache $cache = new Cache()): Application
    {
        $application = new Application('rector-p');
        // a command loader works with every supported Symfony version, Symfony 8 removed Application::add()
        $application->setCommandLoader(new FactoryCommandLoader(['partial' => static fn(): PartialCommand => new PartialCommand($cache)]));
        $application->setDefaultCommand('partial', true);
        return $application;
    }
}
