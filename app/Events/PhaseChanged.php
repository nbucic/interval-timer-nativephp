<?php

declare(strict_types=1);

namespace App\Events;

use App\Timer\Phase;
use Attribute;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

#[Attribute]
class PhaseChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $programId,
        public readonly int    $phaseIndex,
        public readonly Phase  $phase,
        public readonly int    $repIndex,
    ) {}
}
