<?php

declare(strict_types=1);

namespace App\Timer;

/**
 * Immutable record of a single fully-completed program run.
 *
 * Persisted as one element inside storage/app/history.json.
 * The list is capped at 20 entries (newest first).
 */
readonly class HistoryEntry
{
    public function __construct(
        public string $programId,
        public string $programName,
        public string $completedAt,    // ISO 8601
        public int    $totalDuration,  // seconds
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            programId:     $data['program_id'],
            programName:   $data['program_name'],
            completedAt:   $data['completed_at'],
            totalDuration: (int) $data['total_duration'],
        );
    }

    public function toArray(): array
    {
        return [
            'program_id'     => $this->programId,
            'program_name'   => $this->programName,
            'completed_at'   => $this->completedAt,
            'total_duration' => $this->totalDuration,
        ];
    }
}
