<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('default_beep_lead_in')->default(3);
            $table->string('default_end_sound', 20)->default('triple');
            $table->string('sound_mode', 10)->default('beep');
            $table->float('volume')->default(0.8);
            $table->boolean('keep_screen_on')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
