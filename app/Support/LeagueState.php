<?php

namespace App\Support;

use App\Models\Season;
use App\Models\Setting;

/**
 * How far set up the league is, so links to pages that can't work yet stay hidden.
 * Read-only: it never creates anything.
 */
final class LeagueState
{
    /** @return array{hasSeason: bool, leagueLive: bool} */
    public static function flags(): array
    {
        $season = Season::current();
        $settings = Setting::query()->first();

        return [
            'hasSeason' => (bool) $season,
            'leagueLive' => $season
                && $settings?->isComplete()
                && $season->fixtures()->exists(),
        ];
    }
}
