<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enum\StateMachine;
use App\Timer\AppSettings;
use App\Timer\HistoryEntry;
use App\Timer\HistoryLog;
use App\Timer\Phase;
use App\Timer\TimerCursor;
use App\Timer\TimerProgram;
use App\Timer\TimerRunner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
    /** @var array[] Serialised Phase rows for the phase strip */
    public array $phases = [];

    // ── Settings (for the JS audio layer) ────────────────────────────────
    public string $soundMode = 'beep';
    public float $volume = 0.8;
    public string $endSound = 'triple';

    // ── Ring countdown ────────────────────────────────────────────────────
    public int $programTotalDuration = 0;

    // ── Beep lead-in (so JS can display a countdown label) ───────────────
    public string $countdownLabel = '';

    // ── History (shown on Timer tab when no program is loaded) ───────────
    /** @var array[] serialised HistoryEntry rows + 'program_exists' bool */
    public array $history = [];

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

    public function mount(?string $id = null): void
    {
        $settings = AppSettings::load();
        $this->soundMode = $settings->soundMode;
        $this->volume = $settings->volume;

        if ($id) {
            $this->loadProgram($id);
        } else {
            $this->loadHistory();
        }
    }

    public function loadProgram(string $id): void
    {
        $runner = app(TimerRunner::class);
        $program = TimerProgram::load($id);

        $this->programId = $id;
        $this->programName = $program->name;
        $this->endSound = $program->endSound;
        $this->programTotalDuration = $program->totalDuration();
        $this->rehydrateRunner($runner);

        $this->syncCursor($runner->cursor(), $program);

        // Show program name in the top bar as soon as program is loaded
        $this->dispatch('topbar-title', title: $program->name);

        // Push settings to JS audio layer
        $this->dispatch('settingsLoaded', soundMode: $this->soundMode, volume: $this->volume, program: $program);
    }

    private function rehydrateRunner(TimerRunner $runner): void
    {
        if (!$this->programId) {
            return;
        }
        $program = TimerProgram::load($this->programId);
        $runner->load($program);

        $cursor = new TimerCursor(
            phaseIndex: $this->phaseIndex,
            repIndex: $this->repIndex,
            state: $this->state,
            remaining: $this->remaining,
            totalRemaining: $this->totalRemaining,
        );

        $runner->cursor = $cursor;
        $runner->onBeep(function (string $reason): void {
            $this->handleBeep($reason);
        });
        $runner->onPauseBeep(function (): void {
            $this->dispatch('playPauseBeep');
        });
    }

    private function handleBeep(string $reason): void
    {
        // Determine the countdown label for voice mode
        $this->countdownLabel = match ($reason) {
            'prepare', 'countdown' => (string)$this->remaining,
            'rep_end'              => 'Done',
            'pause_end'            => 'Go',
            'cooldown_end'         => 'Next',
            default                => '',
        };
        $this->dispatch('playBeep', reason: $reason);
    }

    private function syncCursor(TimerCursor $cursor, TimerProgram $program): void
    {
        $this->state = $cursor->state;
        $this->remaining = $cursor->remaining;
        $this->totalRemaining = $cursor->totalRemaining;
        $this->phaseIndex = $cursor->phaseIndex;
        $this->repIndex = $cursor->repIndex;

        $this->phases = $program->phases |> (fn($phase) => array_map(
                static fn(Phase $p) => $p->toArray(),
                $phase,
            ));

        if (isset($program->phases[$cursor->phaseIndex])) {
            $phase = $program->phases[$cursor->phaseIndex];
            $this->phaseLabel = $phase->label;
            $this->phaseColor = $phase->color;
            $this->phaseReps = $phase->repetitions;
        }
    }

    private function loadHistory(): void
    {
        $this->history = array_map(
            static function (array $entry): array {
                $entry['program_exists'] = Storage::exists("programs/{$entry['program_id']}.json");
                return $entry;
            },
            array_map(
                static fn(HistoryEntry $e) => $e->toArray(),
                HistoryLog::all(),
            ),
        );
    }

    /**
     * @throws JsonException
     */
    public function pause(): void
    {
        $runner = app(TimerRunner::class);
        $this->rehydrateRunner($runner);
        $runner->pause();
        $this->syncCursor($runner->cursor(), TimerProgram::load($this->programId));
    }

    public function render(): View
    {
        return view('livewire.timer-screen');
    }

    public function repLabel(): string
    {
        if (in_array($this->state, [StateMachine::prepare, StateMachine::cooldown, StateMachine::completed], true)) {
            return '';
        }
        return sprintf('%d / %d', $this->repIndex + 1, $this->phaseReps);
    }

    public function requestSettings(): void
    {
        $program = $this->programId ? TimerProgram::load($this->programId) : null;
        $this->dispatch('settingsLoaded', soundMode: $this->soundMode, volume: $this->volume, program: $program);
    }

    public function restart(): void
    {
        $runner = app(TimerRunner::class);
        $program = TimerProgram::load($this->programId);
        $this->programTotalDuration = $program->totalDuration();
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
        $this->rehydrateRunner($runner);
        $runner->resume();
        $this->syncCursor($runner->cursor(), TimerProgram::load($this->programId));
    }

    public function segmentLabel(): string
    {
        return match ($this->state) {
            StateMachine::prepare  => 'Get Ready',
            StateMachine::pause    => 'Pause',
            StateMachine::cooldown => 'Cooldown',
            StateMachine::paused   => 'Paused',
            StateMachine::completed => 'Complete!',
            default => $this->phaseLabel,
        };
    }

    public function start(): void
    {
        $runner = app(TimerRunner::class);
        $program = TimerProgram::load($this->programId);

        $this->programTotalDuration = $program->totalDuration();
        $runner->load($program);

        $runner->start();
        $this->syncCursor($runner->cursor(), $program);

        // EDGE top bar → program name
        $this->dispatch('topbar-title', title: $this->programName);
    }

    /** Called every second from JS setInterval via wire:poll equivalent.
     * @throws JsonException
     */
    public function tick(): void
    {
        $runner = app(TimerRunner::class);
        $this->rehydrateRunner($runner);

        if (!$runner->cursor()->isActive()) {
            return;
        }

        $runner->tick();
        $cursor = $runner->cursor();
        $program = TimerProgram::load($this->programId);

        $this->syncCursor($cursor, $program);

        if ($cursor->isCompleted()) {
            Log::info('Completed!');
            $this->dispatch('playEndSound', sound: $this->endSound);
            $this->dispatch('topbar-title', title: config('app.name'));
        }
    }
}
