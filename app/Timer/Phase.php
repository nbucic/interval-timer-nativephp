<?php

declare(strict_types=1);

namespace App\Timer;

/**
 * Value object representing a single named timed block within a program.
 */
readonly class Phase
{
    public function __construct(
        public string $label,
        public int    $duration,      // seconds per repetition
        public int    $repetitions,   // 1–50
        public int    $pause,         // dead-time between reps (seconds)
        public int    $cooldown,      // dead-time after final rep (seconds)
        public string $color,         // hex or Tailwind colour token
    ) {
        if ($repetitions < 1 || $repetitions > 50) {
            throw new \RangeException('Repetitions must be between 1 and 50.');
        }
        if ($duration < 1) {
            throw new \RangeException('Phase duration must be at least 1 second.');
        }
    }

    public function toArray(): array
    {
        return [
            'label'       => $this->label,
            'duration'    => $this->duration,
            'repetitions' => $this->repetitions,
            'pause'       => $this->pause,
            'cooldown'    => $this->cooldown,
            'color'       => $this->color,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            label: $data['label'],
            duration: (int) $data['duration'],
            repetitions: (int) ($data['repetitions'] ?? 1),
            pause: (int) ($data['pause'] ?? 0),
            cooldown: (int) ($data['cooldown'] ?? 0),
            color: $data['color'] ?? '#3b82f6',
        );
    }
}
