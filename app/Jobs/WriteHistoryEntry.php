<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Timer\HistoryEntry;
use App\Timer\HistoryLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes a single history entry to storage/app/history.json.
 *
 * Dispatched from TimerRunner::complete() via the "background" queue driver
 * (Laravel 13 process-based concurrency — no Redis, no SQLite required).
 *
 * The background driver serialises this job into a base64 env-var and spawns
 * a dedicated `artisan invoke-serialized-closure` process, so the JSON write
 * happens asynchronously without blocking the UI tick.
 */
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
        HistoryLog::append(new HistoryEntry(
            programId:     $this->programId,
            programName:   $this->programName,
            completedAt:   $this->completedAt,
            totalDuration: $this->totalDuration,
        ));
    }
}
