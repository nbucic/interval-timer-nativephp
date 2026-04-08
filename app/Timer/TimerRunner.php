<?php

declare(strict_types=1);

namespace App\Timer;

use App\Enum\StateMachine;
use App\Events\PhaseChanged;
use App\Events\ProgramCompleted;
use App\Jobs\WriteHistoryEntry;
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
 *   'Running' → 'rep_end'
 *   'Pause' → 'pause_end'
 *   'Cooldown' → 'cooldown_end'
 *
 * Countdown beep fires during the last N seconds of each segment (lead-in).
 * If a segment < lead-in, the countdown starts from second 1.
 */
class TimerRunner
{
    private const PREPARE_SECONDS = 5;

    private ?TimerProgram $program = null;
    public TimerCursor $cursor {
        set {
            $this->cursor = $value;
        }
    }

    /** State before the user pressed pause — needed for resume. */
    private StateMachine $stateBeforePause = StateMachine::running;

    /** Callable invoked each time the cursor changes: fn(TimerCursor) */
    private ?Closure $onTick = null;
    /** Callable invoked when a beep should fire: fn(string $reason) */
    private ?Closure $onBeep = null;
    /** Callable for the single pause-beep: fn() */
    private ?Closure $onPauseBeep = null;

    public function __construct()
    {
        $this->cursor = TimerCursor::idle();
    }

    // ── Public accessors ──────────────────────────────────────────────────────

    public function cursor(): TimerCursor
    {
        return $this->cursor;
    }

    /** Silent discard — no history entry, no ProgramCompleted event. */
    public function discard(): void
    {
        $this->program = null;
        $this->cursor = TimerCursor::idle();
    }

    /** Load a program and reset the cursor to idle. */
    public function load(TimerProgram $program): void
    {
        $this->program = $program;

        $this->cursor = TimerCursor::idle();
    }

    public function onBeep(Closure $fn): void
    {
        $this->onBeep = $fn;
    }

    // ── Callback registration ─────────────────────────────────────────────────

    public function onPauseBeep(Closure $fn): void
    {
        $this->onPauseBeep = $fn;
    }

    public function onTick(Closure $fn): void
    {
        $this->onTick = $fn;
    }

    /** User-initiated pause (or PhoneStateListener). Cannot pause during prepare. */
    public function pause(): void
    {
        if (!$this->cursor->isActive()) {
            return;
        }

        if ($this->cursor->state === StateMachine::prepare) {
            return;
        }

        $this->stateBeforePause = $this->cursor->state;
        $this->cursor = $this->cursor->pause();
        $this->fireOnPauseBeep();
        $this->notifyTick();
    }

    private function fireOnPauseBeep(): void
    {
        if ($this->onPauseBeep !== null) {
            ($this->onPauseBeep)();
        }
    }

    public function program(): ?TimerProgram
    {
        return $this->program;
    }

    // ── Control surface ───────────────────────────────────────────────────────

    /** Resume from the user-paused state, restoring the pre-pause substate. */
    public function resume(): void
    {
        if (!$this->cursor->isPaused()) {
            return;
        }

        $this->cursor = $this->cursor->resumeAs($this->stateBeforePause);
        $this->notifyTick();
    }

    /** Start the timer from idle — enters a 5-second PREPARE countdown first. */
    public function start(): void
    {
        $this->assertProgramLoaded();

        if (!$this->isIdle()) {
            throw new RuntimeException(
                "Cannot start: expected state 'idle', got '{$this->cursor->state->value}'.",
            );
        }

        $this->cursor = $this->cursor->enterPrepare(self::PREPARE_SECONDS);
    }

    /** Transition from prepare → running, initialising the first rep. */
    private function beginFirstRep(): void
    {
        $phase = $this->currentPhase();
        $totalRemaining = $this->program->totalDuration();

        $this->cursor = new TimerCursor(
            phaseIndex: 0,
            repIndex: 0,
            state: StateMachine::running,
            remaining: $phase->duration,
            totalRemaining: $totalRemaining,
        );

        PhaseChanged::dispatch($this->program->id, 0, $phase, 0);
        $this->notifyTick();
    }

    private function assertProgramLoaded(): void
    {
        if ($this->program === null) {
            throw new RuntimeException('No program loaded. Call load() first.');
        }
        if (count($this->program->phases) === 0) {
            throw new RuntimeException('Program has no phases.');
        }
    }

    // ── State machine ─────────────────────────────────────────────────────────

    public function isIdle(): bool
    {
        return $this->cursor->isIdle();
    }

    private function currentPhase(): Phase
    {
        return array_first($this->phases())
            ?? throw new RuntimeException('Program has no phases.');
    }

    /** @return Phase[] */
    private function phases(): array
    {
        return $this->program->phases;
    }

    /**
     * Advance the timer by one second.
     * Called every second from Alpine's setInterval in timer-screen.blade.php.
     */
    public function tick(): void
    {
        if (!$this->cursor->isActive()) {
            return;
        }

        $cursor = $this->cursor->tick();

        // Prepare: beep every second (bypass lead-in logic), transition when done
        if ($cursor->state === StateMachine::prepare) {
            if ($cursor->remaining > 0) {
                $this->fireBeep('prepare');
                $this->cursor = $cursor;
                $this->notifyTick();
            } else {
                $this->beginFirstRep();
            }
            return;
        }

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

    // ── Beep helpers ──────────────────────────────────────────────────────────

    /**
     * True when the cursor's remaining falls within the lead-in countdown window.
     * Uses beepLeadIn->value to extract the int from the BeepLeadIn backed enum.
     */
    private function shouldBeep(TimerCursor $cursor): bool
    {
        if (!$cursor->isActive()) {
            return false;
        }

        $leadIn = $this->program->beepLeadIn->value;  // BeepLeadIn: int enum
        $segmentTotal = $this->segmentDurationForCursor($cursor);
        $effectiveLead = ($segmentTotal < $leadIn) ? max(1, $segmentTotal - 1) : $leadIn;

        return $cursor->remaining <= $effectiveLead && $cursor->remaining > 0;
    }

    private function segmentDurationForCursor(TimerCursor $cursor): int
    {
        $phase = $this->phases()[$cursor->phaseIndex];

        return match ($cursor->state) {
            StateMachine::prepare  => self::PREPARE_SECONDS,
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

    private function notifyTick(): void
    {
        if ($this->onTick !== null) {
            ($this->onTick)($this->cursor);
        }
    }

    /**
     * Called when cursor->remaining hits 0.
     * Dispatches the right end-of-segment beep then decides the next state.
     */
    private function advance(TimerCursor $cursor): void
    {
        $phase = $this->phases()[$cursor->phaseIndex];
        $isLastRep = ($cursor->repIndex >= $phase->repetitions - 1);
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

        if (!$isLastRep && $phase->pause > 0) {
            $this->cursor = $cursor->enterPause(
                $phase->pause,
                max(0, $cursor->totalRemaining),
            );
            $this->notifyTick();
            return;
        }

        if (!$isLastRep) {
            $this->advanceToNextRep($cursor, $phase);
            return;
        }

        // Last rep → cooldown (always executes, even as the last phase's last rep).
        if ($phase->cooldown > 0 && !$isLastPhase) {
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

    // ── Helpers ───────────────────────────────────────────────────────────────

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
        $nextPhase = $this->phases()[$nextPhaseIndex];

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

        $totalDuration = $this->program->totalDuration();

        ProgramCompleted::dispatch(
            $this->program->id,
            $this->program->endSound,
            $totalDuration,
        );

        WriteHistoryEntry::dispatch(
            $this->program->id,
            $this->program->name,
            now()->toISOString(),
            $totalDuration,
        );

        $this->program->touch();
        $this->notifyTick();
    }
}
