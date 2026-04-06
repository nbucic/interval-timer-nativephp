<?php

declare(strict_types=1);

namespace App\Timer;

use App\Events\PhaseChanged;
use App\Events\ProgramCompleted;

/**
 * Laravel 13 singleton that owns all timer state.
 *
 * Registered in AppServiceProvider as:
 *   $this->app->singleton(TimerRunner::class);
 *
 * State machine transitions:
 *   idle → running → pause → running   (between reps)
 *              ↓
 *           cooldown → running          (after final rep, before next phase)
 *              ↓
 *           completed
 *
 * Paused state can be entered from running | pause | cooldown via pause().
 * Resume restores to the previous active state.
 *
 * Beep scheduling:
 *   A countdown beep fires at the END of:  every rep, every pause, every cooldown.
 *   Lead-in: last N seconds of each segment (N = program.beepLeadIn, 3 or 5).
 *   If segment duration < lead-in, beep from second 1 instead.
 *   Paused-state beep: fires once when user pauses (a gentle single beep).
 */
class TimerRunner
{
    private ?TimerProgram $program  = null;
    private TimerCursor   $cursor;

    /** State before the user pressed pause — needed for resume. */
    private string        $stateBeforePause = 'running';

    /** Callable invoked each time the cursor changes: fn(TimerCursor) */
    private ?\Closure     $onTick     = null;
    /** Callable invoked when a beep should fire: fn(string $reason) */
    private ?\Closure     $onBeep     = null;
    /** Callable for the single pause-beep: fn() */
    private ?\Closure     $onPauseBeep = null;

    /** Fake-clock override (seconds since epoch) — set in tests. */
    private ?\Closure     $clockFn    = null;

    public function __construct()
    {
        $this->cursor = TimerCursor::idle();
    }

    // -------------------------------------------------------------------------
    // Public control surface
    // -------------------------------------------------------------------------

    /** Load a program and reset cursor to idle. */
    public function load(TimerProgram $program): void
    {
        $this->program = $program;
        $this->cursor  = TimerCursor::idle();
    }

    /** Start the timer from idle. */
    public function start(): void
    {
        $this->assertProgramLoaded();
        $this->assertState('idle', 'start');

        $phase          = $this->currentPhase();
        $totalRemaining = $this->program->totalDuration();

        $this->cursor = new TimerCursor(
            phaseIndex: 0,
            repIndex: 0,
            state: 'running',
            remaining: $phase->duration,
            totalRemaining: $totalRemaining,
        );

        PhaseChanged::dispatch(
            $this->program->id,
            0,
            $phase,
            0,
        );
    }

    /**
     * Advance the timer by one second.
     * Call this every second from a JS setInterval / Livewire polling loop.
     */
    public function tick(): void
    {
        if (! $this->cursor->isActive()) {
            return;
        }

        $cursor = $this->cursor->tick();

        // Check if we should fire a countdown beep BEFORE we decrement further.
        if ($this->shouldBeep($cursor)) {
            $this->fireBeep('countdown');
        }

        if ($cursor->remaining > 0) {
            $this->cursor = $cursor;
            $this->notifyTick();
            return;
        }

        // Segment expired — advance state machine.
        $this->advance($cursor);
    }

    /** User-initiated pause (or PhoneStateListener hook). */
    public function pause(): void
    {
        if (! $this->cursor->isActive()) {
            return;
        }

        $this->stateBeforePause = $this->cursor->state;
        $this->cursor           = $this->cursor->pause();
        $this->fireOnPauseBeep();
        $this->notifyTick();
    }

    /** Resume from user-paused state. */
    public function resume(): void
    {
        if (! $this->cursor->isPaused()) {
            return;
        }

        /* PHP 8.5: $this->cursor = clone $this->cursor with(state: $this->stateBeforePause); */
        $this->cursor = $this->cursor->resumeAs($this->stateBeforePause);
        $this->notifyTick();
    }

    /** Discard the current run silently (app killed / user bails). */
    public function discard(): void
    {
        $this->program = null;
        $this->cursor  = TimerCursor::idle();
    }

    // -------------------------------------------------------------------------
    // Callback registration
    // -------------------------------------------------------------------------

    public function onTick(\Closure $fn): void      { $this->onTick = $fn; }
    public function onBeep(\Closure $fn): void      { $this->onBeep = $fn; }
    public function onPauseBeep(\Closure $fn): void { $this->onPauseBeep = $fn; }

    /** Override the clock (for tests). fn(): int  (seconds since epoch) */
    public function setClock(\Closure $fn): void    { $this->clockFn = $fn; }

    // -------------------------------------------------------------------------
    // Read-only accessors
    // -------------------------------------------------------------------------

    public function cursor(): TimerCursor     { return $this->cursor; }
    public function program(): ?TimerProgram  { return $this->program; }

    public function isIdle(): bool       { return $this->cursor->isIdle(); }
    public function isRunning(): bool    { return $this->cursor->isRunning(); }
    public function isPaused(): bool     { return $this->cursor->isPaused(); }
    public function isCompleted(): bool  { return $this->cursor->isCompleted(); }

    // -------------------------------------------------------------------------
    // State-machine internals
    // -------------------------------------------------------------------------

    /**
     * Called when cursor->remaining hits 0.
     * Decides what comes next: pause → next rep → cooldown → next phase → complete.
     */
    private function advance(TimerCursor $cursor): void
    {
        $phase      = $this->phases()[$cursor->phaseIndex];
        $isLastRep  = ($cursor->repIndex >= $phase->repetitions - 1);
        $isLastPhase = ($cursor->phaseIndex >= count($this->phases()) - 1);

        $this->fireBeep('rep_end');

        if ($cursor->isRunning() && ! $isLastRep && $phase->pause > 0) {
            // → inter-rep pause
            $this->cursor = $cursor->enterPause(
                $phase->pause,
                max(0, $cursor->totalRemaining),
            );
            $this->notifyTick();
            return;
        }

        if ($cursor->isRunning() && ! $isLastRep) {
            // No pause configured: move directly to next rep.
            $this->advanceToNextRep($cursor, $phase);
            return;
        }

        if ($cursor->isInPause()) {
            // Pause over → next rep.
            $this->fireBeep('pause_end');
            $this->advanceToNextRep($cursor, $phase);
            return;
        }

        if (($cursor->isRunning() || $cursor->isInPause()) && $isLastRep) {
            // All reps done for this phase → enter cooldown (always, even if 0s).
            if ($phase->cooldown > 0) {
                $this->cursor = $cursor->enterCooldown(
                    $phase->cooldown,
                    max(0, $cursor->totalRemaining),
                );
                $this->notifyTick();
                return;
            }
            // Zero-second cooldown: fall through to next phase / complete.
            $this->advanceAfterCooldown($cursor, $isLastPhase);
            return;
        }

        if ($cursor->isInCooldown()) {
            $this->fireBeep('cooldown_end');
            $this->advanceAfterCooldown($cursor, $isLastPhase);
            return;
        }
    }

    private function advanceToNextRep(TimerCursor $cursor, Phase $phase): void
    {
        $this->cursor = $cursor->nextRep(
            $phase->duration,
            max(0, $cursor->totalRemaining),
        );
        $this->notifyTick();
    }

    private function advanceAfterCooldown(TimerCursor $cursor, bool $isLastPhase): void
    {
        if ($isLastPhase) {
            $this->complete();
            return;
        }

        $nextPhaseIndex = $cursor->phaseIndex + 1;
        $nextPhase      = $this->phases()[$nextPhaseIndex];

        $this->cursor = $cursor->nextPhase(
            $nextPhaseIndex,
            $nextPhase->duration,
            max(0, $cursor->totalRemaining),
        );

        PhaseChanged::dispatch(
            $this->program->id,
            $nextPhaseIndex,
            $nextPhase,
            0,
        );

        $this->notifyTick();
    }

    private function complete(): void
    {
        $this->cursor = $this->cursor->complete();

        ProgramCompleted::dispatch(
            $this->program->id,
            $this->program->endSound,
            $this->program->totalDuration(),
        );

        $this->program->touch(); // update last_used_at
        $this->notifyTick();
    }

    // -------------------------------------------------------------------------
    // Beep logic
    // -------------------------------------------------------------------------

    /**
     * True when the cursor's remaining time falls within the lead-in window.
     *
     * Lead-in window: final N seconds of the current segment (N = beepLeadIn).
     * If the segment itself is shorter than N, the window starts from second 1.
     */
    private function shouldBeep(TimerCursor $cursor): bool
    {
        if (! $cursor->isActive()) {
            return false;
        }

        $leadIn       = $this->program->beepLeadIn;
        $segmentTotal = $this->segmentDurationForCursor($cursor);
        $effectiveLead = ($segmentTotal < $leadIn) ? max(1, $segmentTotal - 1) : $leadIn;

        // Fire when remaining drops INTO the lead-in window.
        return $cursor->remaining <= $effectiveLead && $cursor->remaining > 0;
    }

    /** Returns the configured duration of whatever segment the cursor is in. */
    private function segmentDurationForCursor(TimerCursor $cursor): int
    {
        $phase = $this->phases()[$cursor->phaseIndex];

        return match ($cursor->state) {
            'running'  => $phase->duration,
            'pause'    => $phase->pause,
            'cooldown' => $phase->cooldown,
            default    => 0,
        };
    }

    private function fireBeep(string $reason): void
    {
        if ($this->onBeep !== null) {
            ($this->onBeep)($reason);
        }
    }

    private function fireOnPauseBeep(): void
    {
        if ($this->onPauseBeep !== null) {
            ($this->onPauseBeep)();
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function notifyTick(): void
    {
        if ($this->onTick !== null) {
            ($this->onTick)($this->cursor);
        }
    }

    /** @return Phase[] */
    private function phases(): array
    {
        return $this->program->phases;
    }

    private function currentPhase(): Phase
    {
        // PHP 8.5: array_first_value($this->phases())
        return ($this->phases()[0] ?? null)
            ?? throw new \RuntimeException('Program has no phases.');
    }

    private function assertProgramLoaded(): void
    {
        if ($this->program === null) {
            throw new \RuntimeException('No program loaded. Call load() first.');
        }
        if (count($this->program->phases) === 0) {
            throw new \RuntimeException('Program has no phases.');
        }
    }

    private function assertState(string $expected, string $action): void
    {
        if ($this->cursor->state !== $expected) {
            throw new \RuntimeException(
                "Cannot {$action}: expected state '{$expected}', got '{$this->cursor->state}'.",
            );
        }
    }
}
