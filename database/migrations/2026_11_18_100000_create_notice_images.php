<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pictures on a notice: one shows large, several make a gallery. The files live on the
        // image disk (config/league.php); this holds where, how big, and the caption.
        Schema::create('notice_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->constrained()->cascadeOnDelete();
            $table->string('path');       // the full-size picture
            $table->string('thumb_path'); // a smaller one for the gallery grid
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('caption', 200)->nullable();
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_images');
    }
};
