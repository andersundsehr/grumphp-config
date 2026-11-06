<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP\Tests;

use Andersundsehr\RectorP\Helper\TimeHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimeHelperTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function durations(): iterable
    {
        yield 'seconds' => [5, '05s'];
        yield 'full minute' => [60, '01m 00s'];
        yield 'minutes' => [65, '01m 05s'];
        yield 'hours' => [3665, '01h 01m 05s'];
    }

    #[DataProvider('durations')]
    public function testSecondsToHuman(int $seconds, string $expected): void
    {
        self::assertSame($expected, TimeHelper::secondsToHuman($seconds));
    }
}
