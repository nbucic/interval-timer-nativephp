<?php

declare(strict_types=1);

namespace App\Timer;

use App\Enum\StateMachine;
use App\Events\PhaseChanged;
use App\Events\ProgramCompleted;
use Closure;
use RuntimeException;

/**
 * Laravel 13 singleton that owns all timer states.
 *
 * Registered in AppServiceProvider as:
 *   $this->app->singleton(TimerRunner::class);
 *
 * State machine transitions:
 *   idle → running → pause → running (between reps)
 *              ↓
 *           cooldown → running (after final rep, before next phase)
 *              ↓
 *           completed
 *
 * Paused state can be entered from running | pause | cooldown via pause().
 * Resume restores to the previous active state.
 *
 * Beep rule: fire the segment-appropriate end beep based on the expired state.
 *   running → 'rep_end'
 *   pause → 'pause_end'
 *   cooldown → 'cooldown_end'
 *
 * Countdown beep fires during the last N seconds of each segment (lead-in).
 * If a segment < lead-in, the countdown starts from second 1.
 */
class TimerRunner
{
    private ?TimerProgram $program = null;
    private TimerCursor   $cursor;

    /** State before the user pressed pause — needed for resume. */
    private StateMachine $stateBeforePause = StateMachine::running;

    /** Callable invoked each time the cursor changes: fn(TimerCursor) */
    private ?Closure $onTick     = null;
    /** Callable invoked when a beep should fire: fn(string $reason) */
    private ?Closure $onBeep     = null;
    /** Callable for the single pause-beep: fn() */
    private ?Closure $onPauseBeep = null;

    /** Fake-clock override (seconds since epoch) — set in tests. */
    private ?Closure $clockFn = null;

    public function __construct()
    {
        $this->cursor = TimerCursor::idle();
    }

    // ── Public accessors ──────────────────────────────────────────────────────

    public function cursor(): TimerCursor    { return $this->cursor; }
    public function program(): ?TimerProgram { return $this->program; }
    public function isIdle(): bool           { return $this->cursor->isIdle(); }
    public function isRunning(): bool        { return $this->cursor->isRunning(); }
    public function isPaused(): bool         { return $this->cursor->isPaused(); }
    public function isCompleted(): bool      { return $this->cursor->isCompleted(); }

    // ── Callback registration ─────────────────────────────────────────────────

    public function onTick(Closure $fn): void      { $this->onTick = $fn; }
    public function onBeep(Closure $fn): void      { $this->onBeep = $fn; }
    public function onPauseBeep(Closure $fn): void { $this->onPauseBeep = $fn; }

    /** Override the clock for tests. fn(): int (seconds since epoch) */
    public function setClock(Closure $fn): void    { $this->clockFn = $fn; }

    // ── Control surface ───────────────────────────────────────────────────────

    /** Load a program and reset the cursor to idle. */
    public function load(TimerProgram $program): void
    {
        $this->program = $program;
        $this->cursor  = TimerCursor::idle();
    }

    /** Start the timer from idle. */
    public function start(): void
    {
        $this->assertProgramLoaded();

        if (! $this->isIdle()) {
            throw new RuntimeException(
                "Cannot start: expected state 'idle', got '{$this->cursor->state->value}'.",
            );
        }

        $phase          = $this->currentPhase();
        $totalRemaining = $this->program->totalDuration();

        $this->cursor = new TimerCursor(
            phaseIndex:     0,
            repIndex:       0,
            state:          StateMachine::running,
            remaining:      $phase->duration,
            totalRemaining: $totalRemaining,
        );

        PhaseChanged::dispatch($this->program->id, 0, $phase, 0);
    }

    /**
     * Advance the timer by one second.
     * Called every second from Alpine's setInterval in timer-screen.blade.php.
     */
    public function tick(): void
    {
        if (! $this->cursor->isActive()) {
            return;
        }

        $cursor = $this->cursor->tick();

        if ($this->shouldBeep($cursor)) {
            $this->fireBeep('countdown');
        }

        if ($cursor->remaining > 0) {
            $this->cursor = $cursor;
            $this->notifyTick();
            return;
        }

        $this->advance($cursor);
    }

    /** User-initiated pause (or PhoneStateListener). */
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

    /** Resume from the user-paused state, restoring the pre-pause substate. */
    public function resume(): void
    {
        if (! $this->cursor->isPaused()) {
            return;
        }

        $this->cursor = $this->cursor->resumeAs($this->stateBeforePause);
        $this->notifyTick();
    }

    /** Silent discard — no history entry, no ProgramCompleted event. */
    public function discard(): void
    {
        $this->program = null;
        $this->cursor  = TimerCursor::idle();
    }

    // ── State machine ─────────────────────────────────────────────────────────

    /**
     * Called when cursor->remaining hits 0.
     * Dispatches the right end-of-segment beep then decides the next state.
     */
    private function advance(TimerCursor $cursor): void
    {
        $phase       = $this->phases()[$cursor->phaseIndex];
        $isLastRep   = ($cursor->repIndex >= $phase->repetitions - 1);
        $isLastPhase = ($cursor->phaseIndex >= count($this->phases()) - 1);

        // ── Pause segment expired → next rep ──────────────────────────────
        if ($cursor->isInPause()) {
            $this->fireBeep('pause_end');
            $this->advanceToNextRep($cursor, $phase);
            return;
        }

        // ── Cooldown segment expired → next phase or complete ─────────────
        if ($cursor->isInCooldown()) {
            $this->fireBeep('cooldown_end');
            $this->advanceAfterCooldown($cursor, $isLastPhase);
            return;
        }

        // ── Running rep expired ───────────────────────────────────────────
        $this->fireBeep('rep_end');

        if (! $isLastRep && $phase->pause > 0) {
            $this->cursor = $cursor->enterPause(
                $phase->pause,
                max(0, $cursor->totalRemaining),
            );
            $this->notifyTick();
            return;
        }

        if (! $isLastRep) {
            $this->advanceToNextRep($cursor, $phase);
            return;
        }

        // Last rep → cooldown (always executes, even as the last phase's last rep).
        if ($phase->cooldown > 0) {
            $this->cursor = $cursor->enterCooldown(
                $phase->cooldown,
                max(0, $cursor->totalRemaining),
            );
            $this->notifyTick();
            return;
        }

        // Zero-second cooldown: proceed immediately.
        $this->advanceAfterCooldown($cursor, $isLastPhase);
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

        PhaseChanged::dispatch($this->program->id, $nextPhaseIndex, $nextPhase, 0);
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

        $this->program->touch();
        $this->notifyTick();
    }

    // ── Beep helpers ──────────────────────────────────────────────────────────

    /**
     * True when the cursor's remaining falls within the lead-in countdown window.
     * Uses beepLeadIn->value to extract the int from the BeepLeadIn backed enum.
     */
    private function shouldBeep(TimerCursor $cursor): bool
    {
        if (! $cursor->isActive()) {
            return false;
        }

        $leadIn       = $this->program->beepLeadIn->value;  // BeepLeadIn: int enum
        $segmentTotal = $this->segmentDurationForCursor($cursor);
        $effectiveLead = ($segmentTotal < $leadIn) ? max(1, $segmentTotal - 1) : $leadIn;

        return $cursor->remaining <= $effectiveLead && $cursor->remaining > 0;
    }

    private function segmentDurationForCursor(TimerCursor $cursor): int
    {
        $phase = $this->phases()[$cursor->phaseIndex];

        return match ($cursor->state) {
            StateMachine::running  => $phase->duration,
            StateMachine::pause    => $phase->pause,
            StateMachine::cooldown => $phase->cooldown,
            default                => 0,
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

    private function notifyTick(): void
    {
        if ($this->onTick !== null) {
            ($this->onTick)($this->cursor);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function assertProgramLoaded(): void
    {
        if ($this->program === null) {
            throw new RuntimeException('No program loaded. Call load() first.');
        }
        if (count($this->program->phases) === 0) {
            throw new RuntimeException('Program has no phases.');
        }
    }

    private function currentPhase(): Phase
    {
        // PHP 8.5: array_first_value($this->phases())
        return ($this->phases()[0] ?? null)
            ?? throw new RuntimeException('Program has no phases.');
    }

    /** @return Phase[] */
    private function phases(): array
    {
        return $this->program->phases;
    }
}
