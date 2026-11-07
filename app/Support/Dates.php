<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class Dates
{
    /** Friday 13 November */
    public static function long(CarbonInterface $d): string
    {
        return $d->format('l j F');
    }

    /** 13 Nov */
    public static function short(CarbonInterface $d): string
    {
        return $d->format('j M');
    }

    /** 8th November */
    public static function dayMonth(CarbonInterface $d): string
    {
        return self::ordinal($d->day).' '.$d->format('F');
    }

    public static function ordinal(int $n): string
    {
        $s = ['th', 'st', 'nd', 'rd'];
        $v = $n % 100;

        return $n.($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
    }

    /** Only same-site relative paths, so a "back" link can't send anyone elsewhere */
    public static function safePath(?string $path, string $fallback): string
    {
        return is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//') ? $path : $fallback;
    }
}
