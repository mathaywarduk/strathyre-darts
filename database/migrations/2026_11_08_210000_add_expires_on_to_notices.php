<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optional: the last day a notice is shown on the public page
        Schema::table('notices', function (Blueprint $table) {
            $table->date('expires_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->dropColumn('expires_on');
        });
    }
};
