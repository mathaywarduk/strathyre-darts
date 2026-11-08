<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The league's written rules: one page of formatted text, edited in the admin
        Schema::table('settings', function (Blueprint $table) {
            $table->longText('rules_html')->nullable();
        });

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('date');
            $table->longText('content');
            $table->timestamps();
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('rules_html');
        });
    }
};
