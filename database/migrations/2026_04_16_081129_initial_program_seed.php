<?php

use App\Enum\BeepLeadIn;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        DB::table('settings')->truncate();

        DB::table('settings')->insert([
            'default_beep_lead_in' => BeepLeadIn::Three->value,
            'default_end_sound' => 'triple',
            'sound_mode' => 'beep',
            'volume' => 0.8,
            'keep_screen_on' => true,
        ]);

        $programId = Str::uuid7()->toString();
        Log::info(sprintf('Program ID: %s', $programId));

        DB::table('programs')->insert([
            'id' => $programId,
            'name' => 'HIIT',
            'beep_lead_in' => BeepLeadIn::Three->value,
            'end_sound' => 'chime',
        ]);

        foreach ([
                     ['label' => 'Warmup', 'duration' => 10, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 5, 'color' => '#3b82f6'],
                     ['label' => 'Sprint', 'duration' => 8, 'repetitions' => 3, 'pause' => 4, 'cooldown' => 10, 'color' => '#ef4444'],
                     ['label' => 'Stretch', 'duration' => 8, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#22c55e'],
                 ] as $index => $phase) {
            DB::table('phases')->insert(
                array_merge(
                    $phase,
                    [
                        'program_id' => $programId,
                        'sort_order' => $index,
                    ],
                ),
            );
        }
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
