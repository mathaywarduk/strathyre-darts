<?php

namespace App\Support;

use App\Models\Player;
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

    /**
     * What has to happen before the league goes live, in order.
     *
     * @return list<array{0: string, 1: bool, 2: string, 3: ?string}> label, done, where to do it, a note
     */
    public static function setup(): array
    {
        $players = Player::count();
        $settings = Setting::query()->first() ?? new Setting;
        $season = Season::current();

        return [
            ['Upload the players', $players >= 2, route('admin.players.index'), $players.' players'],
            ['Set points and subs', $settings->isComplete(), route('admin.settings.edit'), $settings->isComplete() ? null : 'Still to set: '.implode(', ', $settings->missing())],
            ['Start a season', (bool) $season, route('admin.season.index'), null],
            ['Save the fixtures', $season && $season->fixtures()->exists(), route('admin.fixtures.index'), null],
        ];
    }
}
