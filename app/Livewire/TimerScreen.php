<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enum\StateMachine;
use App\Timer\AppSettings;
use App\Timer\TimerCursor;
use App\Timer\TimerProgram;
use App\Timer\TimerRunner;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use JsonException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Timer — Interval Timer')]
class TimerScreen extends Component
{
    // ── Identifiers ───────────────────────────────────────────────────────
    public ?string $programId = null;
    public string $programName = '';

    // ── Cursor snapshot (serializable scalars for Livewire) ──────────────
    public StateMachine $state = StateMachine::idle;   // mirrors TimerCursor->state
    public int $remaining = 0;
    public int $totalRemaining = 0;
    public int $phaseIndex = 0;
    public int $repIndex = 0;

    // ── Phase display ─────────────────────────────────────────────────────
    public string $phaseLabel = '';
    public string $phaseColor = '#3b82f6';
    public int $phaseReps = 1;

    // ── Settings (for the JS audio layer) ────────────────────────────────
    public string $soundMode = 'beep';
    public float $volume = 0.8;
    public string $endSound = 'triple';

    // ── Beep lead-in (so JS can display a countdown label) ───────────────
    public string $countdownLabel = '';

    public function discard(): void
    {
        app(TimerRunner::class)->discard();
        $this->state = StateMachine::idle;
        $this->remaining = 0;
        $this->totalRemaining = 0;
        $this->dispatch('topbar-title', title: config('app.name'));
    }

    public function formattedRemaining(): string
    {
        $s = $this->remaining;
        return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    }

    // ── Timer controls ────────────────────────────────────────────────────

    public function formattedTotal(): string
    {
        $s = $this->totalRemaining;
        return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    }

    /**
     * @throws JsonException
     */
    public function mount(?string $id = null): void
    {
        $settings = AppSettings::load();
        $this->soundMode = $settings->soundMode;
        $this->volume = $settings->volume;


        if ($id) {
            $this->loadProgram($id);
        }
    }

    public function loadProgram(string $id): void
    {
        $runner = app(TimerRunner::class);
        $program = TimerProgram::load($id);

        $runner->load($program);

        $this->programId = $id;
        $this->programName = $program->name;
        $this->endSound = $program->endSound;

        $this->syncCursor($runner->cursor(), $program);

        // Push settings to JS audio layer
        $this->dispatch('settingsLoaded', soundMode: $this->soundMode, volume: $this->volume, program: $program);
    }

    public function requestSettings(): void
    {
        $program = $this->programId ? TimerProgram::load($this->programId) : null;
        $this->dispatch('settingsLoaded', soundMode: $this->soundMode, volume: $this->volume, program: $program);
    }

    private function syncCursor(TimerCursor $cursor, TimerProgram $program): void
    {
        $this->state = $cursor->state;
        $this->remaining = $cursor->remaining;
        $this->totalRemaining = $cursor->totalRemaining;
        $this->phaseIndex = $cursor->phaseIndex;
        $this->repIndex = $cursor->repIndex;

        if (isset($program->phases[$cursor->phaseIndex])) {
            $phase = $program->phases[$cursor->phaseIndex];
            $this->phaseLabel = $phase->label;
            $this->phaseColor = $phase->color;
            $this->phaseReps = $phase->repetitions;
        }
    }

    /**
     * @throws JsonException
     */
    public function pause(): void
    {
        $runner = app(TimerRunner::class);
        $runner->pause();
        $this->syncCursor($runner->cursor(), TimerProgram::load($this->programId));
    }

    public function render(): View
    {
        return view('livewire.timer-screen');
    }

    // ── Computed display helpers ──────────────────────────────────────────

    public function repLabel(): string
    {
        if (in_array($this->state, [StateMachine::pause, StateMachine::cooldown, StateMachine::paused, StateMachine::completed, StateMachine::idle], true)) {
            return '';
        }
        return sprintf('%d / %d', $this->repIndex + 1, $this->phaseReps);
    }

    /**
     * @throws JsonException
     */
    public function restart(): void
    {
        $runner = app(TimerRunner::class);
        $program = TimerProgram::load($this->programId);
        $runner->load($program);
        $this->syncCursor($runner->cursor(), $program);
        $this->dispatch('topbar-title', title: config('app.name'));
    }

    /**
     * @throws JsonException
     */
    public function resume(): void
    {
        $runner = app(TimerRunner::class);
        $runner->resume();
        $this->syncCursor($runner->cursor(), TimerProgram::load($this->programId));
    }

    public function segmentLabel(): string
    {
        return match ($this->state) {
            StateMachine::pause => 'Pause',
            StateMachine::cooldown => 'Cooldown',
            StateMachine::paused => 'Paused',
            StateMachine::completed => 'Complete!',
            default => $this->phaseLabel,
        };
    }

    /**
     * @throws JsonException
     */
    public function start(): void
    {
        $runner = app(TimerRunner::class);
        $program = TimerProgram::load($this->programId);

        $runner->onBeep(function (string $reason) use ($program): void {
            $this->handleBeep($reason, $program);
        });
        $runner->onPauseBeep(function (): void {
            $this->dispatch('playPauseBeep');
        });
        $runner->load($program);

        $runner->start();
        $this->syncCursor($runner->cursor(), $program);

        // EDGE top bar → program name
        $this->dispatch('topbar-title', title: $this->programName);
    }

    // ── Internals ─────────────────────────────────────────────────────────

    private function handleBeep(string $reason, TimerProgram $program): void
    {
        // Determine the countdown label for voice mode
        $this->countdownLabel = match ($reason) {
            'countdown' => $this->remaining . ' seconds',
            'rep_end' => 'Done',
            'pause_end' => 'Go',
            'cooldown_end' => 'Next',
            default => '',
        };
        $this->dispatch('playBeep', reason: $reason);
    }

    /** Called every second from JS setInterval via wire:poll equivalent.
     * @throws JsonException
     */
    public function tick(): void
    {
        $runner = app(TimerRunner::class);

        if (!$runner->cursor()->isActive()) {
            return;
        }

        $runner->tick();
        $cursor = $runner->cursor();
        $program = TimerProgram::load($this->programId);

        $this->syncCursor($cursor, $program);

        if ($cursor->isCompleted()) {
            $this->dispatch('playEndSound', sound: $this->endSound);
            $this->dispatch('topbar-title', title: config('app.name'));
        }
    }
}
