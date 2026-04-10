<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enum\StateMachine;
use App\Models\HistoryEntry;
use App\Models\Phase;
use App\Models\Program;
use App\Models\Setting;
use App\Timer\TimerCursor;
use App\Timer\TimerRunner;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
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
    public StateMachine $state = StateMachine::idle;
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

    public function mount(?string $id = null): void
    {
        $settings = Setting::current();
        $this->soundMode = $settings->sound_mode;
        $this->volume    = $settings->volume;

        if ($id) {
            $this->loadProgram($id);
        } else {
            $this->loadHistory();
        }
    }

    // ── Timer controls ────────────────────────────────────────────────────

    public function loadProgram(string $id): void
    {
        $runner  = app(TimerRunner::class);
        $program = Program::with('phases')->findOrFail($id);

        $this->programId           = $id;
        $this->programName         = $program->name;
        $this->endSound            = $program->end_sound;
        $this->programTotalDuration = $program->totalDuration();
        $this->rehydrateRunner($runner);

        $this->syncCursor($runner->cursor(), $program);

        $this->dispatch('topbar-title', title: $program->name);
        $this->dispatch('settingsLoaded', soundMode: $this->soundMode, volume: $this->volume, program: $program);
    }

    public function discard(): void
    {
        app(TimerRunner::class)->discard();
        $this->state          = StateMachine::idle;
        $this->remaining      = 0;
        $this->totalRemaining = 0;
        $this->dispatch('topbar-title', title: config('app.name'));
    }

    public function pause(): void
    {
        $runner = app(TimerRunner::class);
        $this->rehydrateRunner($runner);
        $runner->pause();
        $this->syncCursor($runner->cursor(), Program::with('phases')->findOrFail($this->programId));
    }

    public function resume(): void
    {
        $runner = app(TimerRunner::class);
        $this->rehydrateRunner($runner);
        $runner->resume();
        $this->syncCursor($runner->cursor(), Program::with('phases')->findOrFail($this->programId));
    }

    public function restart(): void
    {
        $runner  = app(TimerRunner::class);
        $program = Program::with('phases')->findOrFail($this->programId);
        $this->programTotalDuration = $program->totalDuration();
        $runner->load($program);
        $this->syncCursor($runner->cursor(), $program);
        $this->dispatch('topbar-title', title: config('app.name'));
    }

    public function start(): void
    {
        $runner  = app(TimerRunner::class);
        $program = Program::with('phases')->findOrFail($this->programId);

        $this->programTotalDuration = $program->totalDuration();
        $runner->load($program);
        $runner->start();
        $this->syncCursor($runner->cursor(), $program);

        $this->dispatch('topbar-title', title: $this->programName);
    }

    /** Called every second from JS setInterval via wire:poll equivalent. */
    public function tick(): void
    {
        $runner = app(TimerRunner::class);
        $this->rehydrateRunner($runner);

        if (!$runner->cursor()->isActive()) {
            return;
        }

        $runner->tick();
        $cursor  = $runner->cursor();
        $program = Program::with('phases')->findOrFail($this->programId);

        $this->syncCursor($cursor, $program);

        if ($cursor->isCompleted()) {
            Log::info('Completed!');
            $this->dispatch('playEndSound', sound: $this->endSound);
            $this->dispatch('topbar-title', title: config('app.name'));
        }
    }

    public function requestSettings(): void
    {
        $program = $this->programId ? Program::with('phases')->find($this->programId) : null;
        $this->dispatch('settingsLoaded', soundMode: $this->soundMode, volume: $this->volume, program: $program);
    }

    public function render(): View
    {
        return view('livewire.timer-screen');
    }

    // ── Display helpers ───────────────────────────────────────────────────

    public function formattedRemaining(): string
    {
        $s = $this->remaining;
        return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    }

    public function formattedTotal(): string
    {
        $s = $this->totalRemaining;
        return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    }

    public function repLabel(): string
    {
        if (in_array($this->state, [StateMachine::prepare, StateMachine::cooldown, StateMachine::completed], true)) {
            return '';
        }
        return sprintf('%d / %d', $this->repIndex + 1, $this->phaseReps);
    }

    public function segmentLabel(): string
    {
        return match ($this->state) {
            StateMachine::prepare   => 'Get Ready',
            StateMachine::pause     => 'Pause',
            StateMachine::cooldown  => 'Cooldown',
            StateMachine::paused    => 'Paused',
            StateMachine::completed => 'Complete!',
            default                 => $this->phaseLabel,
        };
    }

    // ── Internals ─────────────────────────────────────────────────────────

    private function rehydrateRunner(TimerRunner $runner): void
    {
        if (!$this->programId) {
            return;
        }

        $program = Program::with('phases')->findOrFail($this->programId);
        $runner->load($program);

        $cursor = new TimerCursor(
            phaseIndex:     $this->phaseIndex,
            repIndex:       $this->repIndex,
            state:          $this->state,
            remaining:      $this->remaining,
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
        $this->countdownLabel = match ($reason) {
            'prepare', 'countdown' => (string) $this->remaining,
            'rep_end'              => 'Done',
            'pause_end'            => 'Go',
            'cooldown_end'         => 'Next',
            default                => '',
        };
        $this->dispatch('playBeep', reason: $reason);
    }

    private function syncCursor(TimerCursor $cursor, Program $program): void
    {
        $this->state          = $cursor->state;
        $this->remaining      = $cursor->remaining;
        $this->totalRemaining = $cursor->totalRemaining;
        $this->phaseIndex     = $cursor->phaseIndex;
        $this->repIndex       = $cursor->repIndex;

        $this->phases = $program->phases
            ->map(fn(Phase $p) => [
                'label'       => $p->label,
                'duration'    => $p->duration,
                'repetitions' => $p->repetitions,
                'pause'       => $p->pause,
                'cooldown'    => $p->cooldown,
                'color'       => $p->color,
            ])
            ->all();

        if (isset($program->phases[$cursor->phaseIndex])) {
            $phase = $program->phases[$cursor->phaseIndex];
            $this->phaseLabel = $phase->label;
            $this->phaseColor = $phase->color;
            $this->phaseReps  = $phase->repetitions;
        }
    }

    private function loadHistory(): void
    {
        $entries = HistoryEntry::latest('completed_at')->limit(20)->get();

        $existingIds = Program::whereIn('id', $entries->pluck('program_id')->filter())
            ->pluck('id')
            ->flip();

        $this->history = $entries
            ->map(fn(HistoryEntry $e) => [
                'program_id'     => $e->program_id,
                'program_name'   => $e->program_name,
                'completed_at'   => $e->completed_at->toISOString(),
                'total_duration' => $e->total_duration,
                'program_exists' => $e->program_id !== null && $existingIds->has($e->program_id),
            ])
            ->all();
    }
}
