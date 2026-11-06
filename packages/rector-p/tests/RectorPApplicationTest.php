<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP\Tests;

use Andersundsehr\RectorP\PartialCommand;
use Andersundsehr\RectorP\RectorPApplication;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RectorPApplication::class)]
final class RectorPApplicationTest extends TestCase
{
    public function testPartialIsTheOnlyCommand(): void
    {
        $application = RectorPApplication::createApplication();

        self::assertSame('rector-p', $application->getName());
        self::assertInstanceOf(PartialCommand::class, $application->find('partial'));
        self::assertTrue($application->isSingleCommand());
    }
}
