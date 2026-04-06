<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

#[\Attribute]
class ProgramCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $programId,
        public readonly string $endSound,   // 'triple' | 'chime'
        public readonly int    $durationSeconds,
    ) {}
}
