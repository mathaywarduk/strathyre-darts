<?php

namespace App\Support;

/** Money is stored as whole pence. */
final class Money
{
    public static function format(int $pence): string
    {
        return ($pence < 0 ? '-' : '').'£'.number_format(abs($pence) / 100, 2);
    }

    /** "2", "2.5", "£2.50" → pence; null if it isn't an amount */
    public static function parse(?string $input): ?int
    {
        $clean = trim(str_replace(['£', ','], '', (string) $input));
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $clean)) {
            return null;
        }

        return (int) round(((float) $clean) * 100);
    }

    /** Like parse(), but a balance can be below zero: "-1.50" → -150 */
    public static function parseSigned(?string $input): ?int
    {
        $clean = trim(str_replace(['£', ',', ' '], '', (string) $input));
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $clean)) {
            return null;
        }

        return (int) round(((float) $clean) * 100);
    }

    /** For form fields: 250 → "2.50" */
    public static function input(?int $pence): string
    {
        return $pence === null ? '' : number_format($pence / 100, 2, '.', '');
    }
}
