<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enum\BeepLeadIn;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the database with the demo HIIT program.
     *
     * Idempotent: does nothing when any program already exists.
     * Called from AppServiceProvider::boot() on every Laravel bootstrap.
     */
    public function run(): void
    {
        // Ensure the settings row exists before Program creation touches it.
        Setting::current();

        if (Program::exists()) {
            return;
        }

        $program = Program::create([
            'name'         => 'HIIT',
            'beep_lead_in' => BeepLeadIn::Three->value,
            'end_sound'    => 'chime',
        ]);

        foreach ([
            ['label' => 'Warmup', 'duration' => 10, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 5,  'color' => '#3b82f6'],
            ['label' => 'Sprint', 'duration' => 8,  'repetitions' => 3, 'pause' => 4, 'cooldown' => 10, 'color' => '#ef4444'],
            ['label' => 'Stretch','duration' => 8,  'repetitions' => 1, 'pause' => 0, 'cooldown' => 0,  'color' => '#22c55e'],
        ] as $phase) {
            $program->addPhase($phase);
        }
    }
}
