<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\HistoryEntry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class WriteHistoryEntry implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $programId,
        private readonly string $programName,
        private readonly string $completedAt,
        private readonly int    $totalDuration,
    ) {}

    public function handle(): void
    {
        HistoryEntry::create([
            'program_id'     => $this->programId,
            'program_name'   => $this->programName,
            'completed_at'   => $this->completedAt,
            'total_duration' => $this->totalDuration,
        ]);

        // Keep only the 20 most recent entries
        $toDelete = HistoryEntry::latest('completed_at')->limit(10)->skip(20)->pluck('id');
        if ($toDelete->isNotEmpty()) {
            HistoryEntry::whereIn('id', $toDelete)->delete();
        }
    }
}
