<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When the subs records were last changed by the admin, shown on the public pages
        // so people who have paid aren't confused if it isn't showing yet.
        Schema::table('seasons', function (Blueprint $table) {
            $table->timestamp('subs_updated_at')->nullable();
        });

        foreach (DB::table('subs_payments')->selectRaw('season_id, max(updated_at) as last')->groupBy('season_id')->get() as $row) {
            DB::table('seasons')->where('id', $row->season_id)->update(['subs_updated_at' => $row->last]);
        }
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('subs_updated_at');
        });
    }
};
