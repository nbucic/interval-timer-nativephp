<?php

declare(strict_types=1);

namespace App\Timer;

use App\Enum\StateMachine;

/**
 * Immutable snapshot of where the timer is at any moment.
 *
 * PHP 8.5 FEATURES:
 *   • readonly class — all properties set once at construction.
 *   • clone with syntax — each advance method shows the PHP 8.5 form in a
 *                           comment; the working fallback uses new self() which
 *                           is 100% equivalent for readonly classes.
 *   • StateMachine enum — string-backed enum replaces the string literal.
 */
readonly class TimerCursor
{
    public function __construct(
        public int          $phaseIndex,
        public int          $repIndex,
        public StateMachine $state,           // idle | running | paused | pause | cooldown | completed
        public int          $remaining,       // seconds left in current segment
        public int          $totalRemaining,  // seconds left in the entire program
    )
    {
    }

    public static function idle(): self
    {
        return new self(
            phaseIndex: 0,
            repIndex: 0,
            state: StateMachine::idle,
            remaining: 0,
            totalRemaining: 0,
        );
    }

    /** Mark the program as completed. */
    public function complete(): self
    {
        /* PHP 8.5: return clone $this with { state: StateMachine::completed,
                                              remaining: 0, totalRemaining: 0 }; */
        return new self(
            phaseIndex: $this->phaseIndex,
            repIndex: $this->repIndex,
            state: StateMachine::completed,
            remaining: 0,
            totalRemaining: 0,
        );
    }

    /** Move into the cooldown state after the final rep of a phase. */
    public function enterCooldown(int $cooldownDuration, int $totalRemaining): self
    {
        /* PHP 8.5: return clone $this with { state: StateMachine::cooldown,
                                              remaining: $cooldownDuration,
                                              totalRemaining: $totalRemaining }; */
        return new self(
            phaseIndex: $this->phaseIndex,
            repIndex: $this->repIndex,
            state: StateMachine::cooldown,
            remaining: $cooldownDuration,
            totalRemaining: $totalRemaining,
        );
    }

    /** Move into the pause state between repetitions. */
    public function enterPause(int $pauseDuration, int $totalRemaining): self
    {
        /* PHP 8.5: return clone $this with { state: StateMachine::pause,
                                              remaining: $pauseDuration,
                                              totalRemaining: $totalRemaining }; */
        return new self(
            phaseIndex: $this->phaseIndex,
            repIndex: $this->repIndex,
            state: StateMachine::pause,
            remaining: $pauseDuration,
            totalRemaining: $totalRemaining,
        );
    }

    /** True whenever the timer is actively counting down (not user-paused, not idle). */
    public function isActive(): bool
    {
        return in_array($this->state, [StateMachine::running, StateMachine::pause, StateMachine::cooldown], true);
    }

    public function isCompleted(): bool
    {
        return $this->state === StateMachine::completed;
    }

    public function isIdle(): bool
    {
        return $this->state === StateMachine::idle;
    }

    public function isInCooldown(): bool
    {
        return $this->state === StateMachine::cooldown;
    }

    public function isInPause(): bool
    {
        return $this->state === StateMachine::pause;
    }

    // ── Predicates ────────────────────────────────────────────────────────────

    public function isPaused(): bool
    {
        return $this->state === StateMachine::paused;
    }

    public function isRunning(): bool
    {
        return $this->state === StateMachine::running;
    }

    /** Advance to the first rep of the next phase. */
    public function nextPhase(int $phaseIndex, int $repDuration, int $totalRemaining): self
    {
        /* PHP 8.5: return clone $this with { phaseIndex: $phaseIndex,
                                              repIndex: 0,
                                              state: StateMachine::running,
                                              remaining: $repDuration,
                                              totalRemaining: $totalRemaining }; */
        return new self(
            phaseIndex: $phaseIndex,
            repIndex: 0,
            state: StateMachine::running,
            remaining: $repDuration,
            totalRemaining: $totalRemaining,
        );
    }

    /** Advance to the next repetition of the same phase. */
    public function nextRep(int $repDuration, int $totalRemaining): self
    {
        /* PHP 8.5: return clone $this with { repIndex: $this->repIndex + 1,
                                              state: StateMachine::running,
                                              remaining: $repDuration,
                                              totalRemaining: $totalRemaining }; */
        return new self(
            phaseIndex: $this->phaseIndex,
            repIndex: $this->repIndex + 1,
            state: StateMachine::running,
            remaining: $repDuration,
            totalRemaining: $totalRemaining,
        );
    }

    /** User pressed pause (or phone call received). */
    public function pause(): self
    {
        /* PHP 8.5: return clone $this with { state: StateMachine::paused }; */
        return new self(
            phaseIndex: $this->phaseIndex,
            repIndex: $this->repIndex,
            state: StateMachine::paused,
            remaining: $this->remaining,
            totalRemaining: $this->totalRemaining,
        );
    }

    /** Resume into a named substate (the state that was active pre-pause). */
    public function resumeAs(StateMachine $state): self
    {
        /* PHP 8.5: return clone $this with { state: $state }; */
        return clone($this, [
                'phaseIndex' => $this->phaseIndex,
                'repIndex' => $this->repIndex,
                'state' => $state,
                'remaining' => $this->remaining,
                'totalRemaining' => $this->totalRemaining,
            ]
        );
    }

    /** Tick one second off the current segment and the total remaining. */
    public function tick(): self
    {
        /* PHP 8.5: return clone $this with { remaining: max (0, $this->remaining - 1),
                                              totalRemaining: max(0, $this->totalRemaining - 1) }; */
        return new self(
            phaseIndex: $this->phaseIndex,
            repIndex: $this->repIndex,
            state: $this->state,
            remaining: max(0, $this->remaining - 1),
            totalRemaining: max(0, $this->totalRemaining - 1),
        );
    }
}
