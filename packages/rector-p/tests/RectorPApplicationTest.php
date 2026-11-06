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

    /**
     * Symfony 8 removed Application::add(), Symfony 7.4 deprecates it
     */
    public function testUsesNoDeprecatedMethods(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;
            return true;
        }, E_USER_DEPRECATED | E_DEPRECATED);

        try {
            RectorPApplication::createApplication();
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
    }
}
