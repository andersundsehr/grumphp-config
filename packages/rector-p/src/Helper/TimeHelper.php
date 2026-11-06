<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP\Helper;

final class TimeHelper
{
    public static function secondsToHuman(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds, 60) % 60;
        $seconds %= 60;
        if ($hours !== 0) {
            return sprintf('%02dh %02dm %02ds', $hours, $minutes, $seconds);
        }

        if ($minutes !== 0) {
            return sprintf('%02dm %02ds', $minutes, $seconds);
        }

        return sprintf('%02ds', $seconds);
    }
}
