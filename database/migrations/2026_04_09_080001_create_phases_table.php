<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phases', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order');
            $table->string('label', 40);
            $table->unsignedSmallInteger('duration');
            $table->unsignedSmallInteger('repetitions')->default(1);
            $table->unsignedSmallInteger('pause')->default(0);
            $table->unsignedSmallInteger('cooldown')->default(0);
            $table->string('color', 20)->default('#3b82f6');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phases');
    }
};
