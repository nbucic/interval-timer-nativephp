<?php

declare(strict_types=1);

namespace App\Timer;

/**
 * Immutable snapshot of where the timer is at any moment.
 *
 * PHP 8.5 FEATURES:
 *   • readonly class  — all properties set once at construction.
 *   • clone with      — used in every advance method to produce a new cursor
 *                       while overriding only the changed properties.
 *
 * NOTE: `clone $obj with(prop: value)` requires the PHP 8.5 runtime bundled
 * by NativePHP. The host CLI may run PHP 8.4; the app itself runs PHP 8.5.
 */
readonly class TimerCursor
{
    public function __construct(
        public int    $phaseIndex,
        public int    $repIndex,
        public string $state,           // idle | running | paused | pause | cooldown | completed
        public int    $remaining,       // seconds left in current segment
        public int    $totalRemaining,  // seconds left in the entire program
    ) {}

    // -------------------------------------------------------------------------
    // Segment-level advances — PHP 8.5 clone with
    // -------------------------------------------------------------------------

    /** Tick one second off the current segment and total remaining. */
    public function tick(): self
    {
        /* PHP 8.5:
        return clone $this with(
            remaining:      max(0, $this->remaining - 1),
            totalRemaining: max(0, $this->totalRemaining - 1),
        ); */
        return new self(
            phaseIndex:     $this->phaseIndex,
            repIndex:       $this->repIndex,
            state:          $this->state,
            remaining:      max(0, $this->remaining - 1),
            totalRemaining: max(0, $this->totalRemaining - 1),
        );
    }

    /** Move into the pause state between repetitions. */
    public function enterPause(int $pauseDuration, int $totalRemaining): self
    {
        /* PHP 8.5:
        return clone $this with(
            state:          'pause',
            remaining:      $pauseDuration,
            totalRemaining: $totalRemaining,
        ); */
        return new self(
            phaseIndex:     $this->phaseIndex,
            repIndex:       $this->repIndex,
            state:          'pause',
            remaining:      $pauseDuration,
            totalRemaining: $totalRemaining,
        );
    }

    /** Move into the cooldown state after the final rep of a phase. */
    public function enterCooldown(int $cooldownDuration, int $totalRemaining): self
    {
        /* PHP 8.5:
        return clone $this with(
            state:          'cooldown',
            remaining:      $cooldownDuration,
            totalRemaining: $totalRemaining,
        ); */
        return new self(
            phaseIndex:     $this->phaseIndex,
            repIndex:       $this->repIndex,
            state:          'cooldown',
            remaining:      $cooldownDuration,
            totalRemaining: $totalRemaining,
        );
    }

    /** Advance to the next repetition of the same phase. */
    public function nextRep(int $repDuration, int $totalRemaining): self
    {
        /* PHP 8.5:
        return clone $this with(
            repIndex:       $this->repIndex + 1,
            state:          'running',
            remaining:      $repDuration,
            totalRemaining: $totalRemaining,
        ); */
        return new self(
            phaseIndex:     $this->phaseIndex,
            repIndex:       $this->repIndex + 1,
            state:          'running',
            remaining:      $repDuration,
            totalRemaining: $totalRemaining,
        );
    }

    /** Advance to the first rep of the next phase. */
    public function nextPhase(int $phaseIndex, int $repDuration, int $totalRemaining): self
    {
        /* PHP 8.5:
        return clone $this with(
            phaseIndex:     $phaseIndex,
            repIndex:       0,
            state:          'running',
            remaining:      $repDuration,
            totalRemaining: $totalRemaining,
        ); */
        return new self(
            phaseIndex:     $phaseIndex,
            repIndex:       0,
            state:          'running',
            remaining:      $repDuration,
            totalRemaining: $totalRemaining,
        );
    }

    /** User pressed pause (or phone call received). */
    public function pause(): self
    {
        /* PHP 8.5: return clone $this with(state: 'paused'); */
        return new self(
            phaseIndex:     $this->phaseIndex,
            repIndex:       $this->repIndex,
            state:          'paused',
            remaining:      $this->remaining,
            totalRemaining: $this->totalRemaining,
        );
    }

    /** Resume into a named sub-state (e.g. the state that was active pre-pause). */
    public function resumeAs(string $state): self
    {
        /* PHP 8.5: return clone $this with(state: $state); */
        return new self(
            phaseIndex:     $this->phaseIndex,
            repIndex:       $this->repIndex,
            state:          $state,
            remaining:      $this->remaining,
            totalRemaining: $this->totalRemaining,
        );
    }

    /** Mark the program as completed. */
    public function complete(): self
    {
        /* PHP 8.5:
        return clone $this with(
            state:          'completed',
            remaining:      0,
            totalRemaining: 0,
        ); */
        return new self(
            phaseIndex:     $this->phaseIndex,
            repIndex:       $this->repIndex,
            state:          'completed',
            remaining:      0,
            totalRemaining: 0,
        );
    }

    // -------------------------------------------------------------------------
    // Convenience predicates
    // -------------------------------------------------------------------------

    public function isIdle(): bool       { return $this->state === 'idle'; }
    public function isRunning(): bool    { return $this->state === 'running'; }
    public function isPaused(): bool     { return $this->state === 'paused'; }
    public function isInPause(): bool    { return $this->state === 'pause'; }
    public function isInCooldown(): bool { return $this->state === 'cooldown'; }
    public function isCompleted(): bool  { return $this->state === 'completed'; }

    /** True whenever the timer is actively counting down (not user-paused, not idle). */
    public function isActive(): bool
    {
        return in_array($this->state, ['running', 'pause', 'cooldown'], true);
    }

    public static function idle(): self
    {
        return new self(
            phaseIndex:     0,
            repIndex:       0,
            state:          'idle',
            remaining:      0,
            totalRemaining: 0,
        );
    }
}
