<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enum\BeepLeadIn;
use App\Models\HistoryEntry;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use JsonException;

/**
 * One-time migration from the old JSON file storage to SQLite.
 *
 * Run this once on an existing install before deploying the database version:
 *   php artisan timer:migrate-from-files
 *
 * Safe to run multiple times — skips programs already present by UUID.
 */
class MigrateFromFiles extends Command
{
    protected $signature = 'timer:migrate-from-files
                            {--force : Skip confirmation prompt}';

    protected $description = 'Import programs, history, and settings from JSON file storage into the database';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('This will import JSON file data into the database. Continue?')) {
            return self::FAILURE;
        }

        $this->migrateSettings();
        $this->migratePrograms();
        $this->migrateHistory();

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function migrateSettings(): void
    {
        $path = 'settings.json';

        if (!Storage::exists($path)) {
            $this->line('  settings.json not found — skipping (defaults will be used on first load).');
            return;
        }

        try {
            $data = json_decode(Storage::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->warn("  Could not parse settings.json: {$e->getMessage()}");
            return;
        }

        $settings = Setting::first() ?? new Setting();
        $settings->default_beep_lead_in = BeepLeadIn::from((int) ($data['default_beep_lead_in'] ?? 3));
        $settings->default_end_sound    = $data['default_end_sound'] ?? 'triple';
        $settings->sound_mode           = $data['sound_mode'] ?? 'beep';
        $settings->volume               = (float) ($data['volume'] ?? 0.8);
        $settings->keep_screen_on       = (bool) ($data['keep_screen_on'] ?? true);
        $settings->save();

        $this->info('  Settings imported.');
    }

    private function migratePrograms(): void
    {
        $files = collect(Storage::files('programs'))
            ->filter(fn(string $p) => str_ends_with($p, '.json'));

        if ($files->isEmpty()) {
            $this->line('  No program files found — skipping.');
            return;
        }

        $imported = 0;
        $skipped  = 0;

        foreach ($files as $file) {
            try {
                $data = json_decode(Storage::get($file), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $this->warn("  Skipping $file: {$e->getMessage()}");
                $skipped++;
                continue;
            }

            $id = $data['id'] ?? null;
            if (!$id) {
                $this->warn("  Skipping $file: missing id.");
                $skipped++;
                continue;
            }

            if (Program::where('id', $id)->exists()) {
                $this->line("  Skipping $id — already in database.");
                $skipped++;
                continue;
            }

            $program = Program::create([
                'id'           => $id,
                'name'         => $data['name'],
                'beep_lead_in' => BeepLeadIn::from((int) ($data['beep_lead_in'] ?? 3)),
                'end_sound'    => $data['end_sound'] ?? 'triple',
                'last_used_at' => $data['last_used_at'] ?? null,
//                'created_at'   => $data['created_at'] ?? now(),
//                'updated_at'   => $data['created_at'] ?? now(),
            ]);

            foreach (($data['phases'] ?? []) as $index => $phaseData) {
                $program->phases()->create([
                    'sort_order'  => $index,
                    'label'       => $phaseData['label'],
                    'duration'    => (int) $phaseData['duration'],
                    'repetitions' => (int) ($phaseData['repetitions'] ?? 1),
                    'pause'       => (int) ($phaseData['pause'] ?? 0),
                    'cooldown'    => (int) ($phaseData['cooldown'] ?? 0),
                    'color'       => $phaseData['color'] ?? '#3b82f6',
                ]);
            }

            $imported++;
        }

        $this->info("  Programs: $imported imported, $skipped skipped.");
    }

    private function migrateHistory(): void
    {
        $path = 'history.json';

        if (!Storage::exists($path)) {
            $this->line('  history.json not found — skipping.');
            return;
        }

        try {
            $entries = json_decode(Storage::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->warn("  Could not parse history.json: {$e->getMessage()}");
            return;
        }

        if (HistoryEntry::exists()) {
            $this->line('  History table already has rows — skipping.');
            return;
        }

        $imported = 0;
        foreach ($entries as $entry) {
            HistoryEntry::create([
                'program_id'     => $entry['program_id'] ?? null,
                'program_name'   => $entry['program_name'],
                'completed_at'   => $entry['completed_at'],
                'total_duration' => (int) $entry['total_duration'],
            ]);
            $imported++;
        }

        $this->info("  History: $imported entries imported.");
    }
}
