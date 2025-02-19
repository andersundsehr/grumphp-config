<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP\Helper;

final class TimeHelper
{
    public static function secondsToHuman(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(((int)($seconds / 60)) % 60);
        $seconds = (int)ceil($seconds % 60);
        if ($hours) {
            return sprintf('%02dh %02dm %02ds', $hours, $minutes, $seconds);
        }

        if ($minutes) {
            return sprintf('%02dm %02ds', $minutes, $seconds);
        }

        return sprintf('%02ds', $seconds);
    }
}
