<?php

namespace Database\Seeders;

use App\Models\Season;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Only the real starting point: the first season starts Friday 13 November 2026.
 * Players, fixtures and results are uploaded in the admin, and points and
 * subs are deliberately left unset until you choose them in Settings.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Setting::current();

        if (! Season::query()->exists()) {
            Season::create(['starts_on' => '2026-11-13', 'status' => 'current']);
        }
    }
}
