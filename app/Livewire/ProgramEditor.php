<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enum\BeepLeadIn;
use App\Timer\AppSettings;
use App\Timer\Phase;
use App\Timer\TimerProgram;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;
use JsonException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Program — Interval Timer')]
class ProgramEditor extends Component
{
    // ── Program fields ────────────────────────────────────────────────────
    public string $programId = '';
    public string $name = '';
    public BeepLeadIn $beepLeadIn = BeepLeadIn::Three;
    public string $endSound = 'triple';

    // ── Phase form fields (for the active add/edit panel) ─────────────────
    public ?int $editingPhaseIndex = null;  // null = adding new
    public string $phaseLabel = '';
    public int $phaseDuration = 30;
    public int $phaseReps = 1;
    public int $phasePause = 0;
    public int $phaseCooldown = 0;
    public string $phaseColor = '#3b82f6';

    public bool $showPhaseForm = false;

    /** @var array[] Raw phase arrays (toArray) for display, mutated in-place */
    public array $phases = [];

    // Color palette for quick-pick
    public array $palette = [
        '#3b82f6', // blue
        '#22c55e', // green
        '#f97316', // orange
        '#ef4444', // red
        '#a855f7', // purple
        '#ec4899', // pink
        '#eab308', // yellow
        '#06b6d4', // cyan
    ];

    public function cancelPhaseForm(): void
    {
        $this->showPhaseForm = false;
        $this->resetPhaseForm();
    }

    // ── Phase form ────────────────────────────────────────────────────────

    public function deletePhase(int $index): void
    {
        array_splice($this->phases, $index, 1);
    }

    public function editPhase(int $index): void
    {
        $p = $this->phases[$index];
        $this->phaseLabel = $p['label'];
        $this->phaseDuration = $p['duration'];
        $this->phaseReps = $p['repetitions'];
        $this->phasePause = $p['pause'];
        $this->phaseCooldown = $p['cooldown'];
        $this->phaseColor = $p['color'];
        $this->editingPhaseIndex = $index;
        $this->showPhaseForm = true;
    }

    public function formattedDuration(): string
    {
        $total = $this->totalDuration();
        return sprintf('%d:%02d', intdiv($total, 60), $total % 60);
    }

    public function totalDuration(): int
    {
        return array_reduce(
            $this->phases,
            static function (int $carry, array $p): int {
                $repTime = $p['duration'] * $p['repetitions'];
                $pauses = $p['pause'] * max(0, $p['repetitions'] - 1);
                return $carry + $repTime + $pauses + $p['cooldown'];
            },
            0,
        );
    }

    /**
     * @throws JsonException
     */
    public function mount(string $id): void
    {
        if ($id === 'create') {
            // New program with defaults from settings
            $settings = AppSettings::load();
            $this->beepLeadIn = $settings->defaultBeepLeadIn;
            $this->endSound = $settings->defaultEndSound;
        } else {
            $program = TimerProgram::load($id);
            $this->programId = $program->id;
            $this->name = $program->name;
            $this->beepLeadIn = $program->beepLeadIn;
            $this->endSound = $program->endSound;
            $this->phases = array_map(
                static fn(Phase $p) => $p->toArray(),
                $program->phases,
            );
        }
    }

    public function movePhaseDown(int $index): void
    {
        if ($index >= count($this->phases) - 1) return;
        [$this->phases[$index], $this->phases[$index + 1]] =
            [$this->phases[$index + 1], $this->phases[$index]];
    }

    public function movePhaseUp(int $index): void
    {
        if ($index <= 0) return;
        [$this->phases[$index - 1], $this->phases[$index]] =
            [$this->phases[$index], $this->phases[$index - 1]];
    }

    // ── Program save/delete ───────────────────────────────────────────────

    public function openAddPhase(): void
    {
        if (count($this->phases) >= 10) {
            return;
        }
        $this->resetPhaseForm();
        $this->editingPhaseIndex = null;
        $this->showPhaseForm = true;
    }

    // ── Computed ─────────────────────────────────────────────────────────

    /**
     * True when the phase form is open for the last phase in the list
     * (or for a new phase that will become the last).
     * Used in the view to grey out the cooldown field.
     */
    public function editingIsLastPhase(): bool
    {
        $count = count($this->phases);
        if ($count === 0) {
            return true; // first (and only) phase being added → will be last
        }
        if ($this->editingPhaseIndex === null) {
            return true; // adding a new phase → will be appended as last
        }
        return $this->editingPhaseIndex === $count - 1;
    }

    private function resetPhaseForm(): void
    {
        $this->phaseLabel = '';
        $this->phaseDuration = 30;
        $this->phaseReps = 3;
        $this->phasePause = 0;
        $this->phaseCooldown = 0;
        $this->phaseColor = '#3b82f6';
        $this->editingPhaseIndex = null;
    }

    public function render(): View
    {
        return view('livewire.program-editor');
    }

    public function savePhase(): void
    {
        $this->validate([
            'phaseLabel' => 'required|string|max:40',
            'phaseDuration' => 'required|integer|min:1|max:3600',
            'phaseReps' => 'required|integer|min:1|max:50',
            'phasePause' => 'required|integer|min:0|max:3600',
            'phaseCooldown' => 'required|integer|min:0|max:3600',
        ]);

        if ($this->phaseReps === 1) {
            $this->phasePause = 0;
        }

        $phaseArray = [
            'label' => trim($this->phaseLabel),
            'duration' => $this->phaseDuration,
            'repetitions' => $this->phaseReps,
            'pause' => $this->phasePause,
            'cooldown' => $this->phaseCooldown,
            'color' => $this->phaseColor,
        ];

        if ($this->editingPhaseIndex !== null) {
            $this->phases[$this->editingPhaseIndex] = $phaseArray;
        } else {
            $this->phases[] = $phaseArray;
        }

        $this->showPhaseForm = false;
        $this->resetPhaseForm();
    }

    public function savePhaseAndAddNew(): void
    {
        $this->savePhase();
        $this->editingPhaseIndex = null;
        $this->showPhaseForm = true;
    }

    // ── Internals ─────────────────────────────────────────────────────────

    /**
     * @throws JsonException
     */
    public function saveProgram(): void
    {
        $this->validate([
            'name' => 'required|string|max:60',
            'beepLeadIn' => ['required', new Enum(BeepLeadIn::class)],
            'endSound' => 'required|in:triple,chime',
        ]);

        if ($this->programId === '') {
            $program = TimerProgram::create($this->name);
            $this->programId = $program->id;
        } else {
            $program = TimerProgram::load($this->programId);
            $program->name = $this->name;
        }

        $program->beepLeadIn = $this->beepLeadIn;
        $program->endSound = $this->endSound;
        $program->phases = array_map(
            static fn(array $p) => Phase::fromArray($p),
            $this->phases,
        );

        $program->save();

        $this->redirect("/timer/$program->id");
    }
}
