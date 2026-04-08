<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enum\BeepLeadIn;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;
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

    /** @var array[] Raw phase arrays for display, mutated in-place */
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

    public function mount(string $id): void
    {
        if ($id === 'create') {
            $settings = Setting::current();
            $this->beepLeadIn = $settings->default_beep_lead_in;
            $this->endSound   = $settings->default_end_sound;
        } else {
            $program = Program::with('phases')->findOrFail($id);
            $this->programId = $program->id;
            $this->name      = $program->name;
            $this->beepLeadIn = $program->beep_lead_in;
            $this->endSound   = $program->end_sound;
            $this->phases = $program->phases
                ->map(fn($p) => [
                    'label'       => $p->label,
                    'duration'    => $p->duration,
                    'repetitions' => $p->repetitions,
                    'pause'       => $p->pause,
                    'cooldown'    => $p->cooldown,
                    'color'       => $p->color,
                ])
                ->all();
        }
    }

    // ── Phase form ────────────────────────────────────────────────────────

    public function cancelPhaseForm(): void
    {
        $this->showPhaseForm = false;
        $this->resetPhaseForm();
    }

    public function deletePhase(int $index): void
    {
        array_splice($this->phases, $index, 1);
    }

    public function editPhase(int $index): void
    {
        $p = $this->phases[$index];
        $this->phaseLabel    = $p['label'];
        $this->phaseDuration = $p['duration'];
        $this->phaseReps     = $p['repetitions'];
        $this->phasePause    = $p['pause'];
        $this->phaseCooldown = $p['cooldown'];
        $this->phaseColor    = $p['color'];
        $this->editingPhaseIndex = $index;
        $this->showPhaseForm = true;
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

    public function openAddPhase(): void
    {
        if (count($this->phases) >= 10) {
            return;
        }
        $this->resetPhaseForm();
        $this->editingPhaseIndex = null;
        $this->showPhaseForm = true;
    }

    public function savePhase(): void
    {
        $this->validate([
            'phaseLabel'    => 'required|string|max:40',
            'phaseDuration' => 'required|integer|min:1|max:3600',
            'phaseReps'     => 'required|integer|min:1|max:50',
            'phasePause'    => 'required|integer|min:0|max:3600',
            'phaseCooldown' => 'required|integer|min:0|max:3600',
        ]);

        if ($this->phaseReps === 1) {
            $this->phasePause = 0;
        }

        $phaseArray = [
            'label'       => trim($this->phaseLabel),
            'duration'    => $this->phaseDuration,
            'repetitions' => $this->phaseReps,
            'pause'       => $this->phasePause,
            'cooldown'    => $this->phaseCooldown,
            'color'       => $this->phaseColor,
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

    // ── Program save ──────────────────────────────────────────────────────

    public function saveProgram(): void
    {
        $this->validate([
            'name'       => 'required|string|max:60',
            'beepLeadIn' => ['required', new Enum(BeepLeadIn::class)],
            'endSound'   => 'required|in:triple,chime',
        ]);

        if ($this->programId === '') {
            $settings = Setting::current();
            $program = Program::create([
                'name'         => $this->name,
                'beep_lead_in' => $settings->default_beep_lead_in,
                'end_sound'    => $settings->default_end_sound,
            ]);
            $this->programId = $program->id;
        } else {
            $program = Program::findOrFail($this->programId);
            $program->name = $this->name;
        }

        $program->beep_lead_in = $this->beepLeadIn;
        $program->end_sound    = $this->endSound;
        $program->save();

        $program->phases()->delete();
        foreach ($this->phases as $index => $phaseData) {
            $program->phases()->create([
                'sort_order'  => $index,
                'label'       => $phaseData['label'],
                'duration'    => (int) $phaseData['duration'],
                'repetitions' => (int) $phaseData['repetitions'],
                'pause'       => (int) $phaseData['pause'],
                'cooldown'    => (int) $phaseData['cooldown'],
                'color'       => $phaseData['color'],
            ]);
        }

        $this->redirect("/timer/$program->id");
    }

    // ── Computed ─────────────────────────────────────────────────────────

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
                $pauses  = $p['pause'] * max(0, $p['repetitions'] - 1);
                return $carry + $repTime + $pauses + $p['cooldown'];
            },
            0,
        );
    }

    /**
     * True when the phase form is open for the last phase in the list
     * (or for a new phase that will become the last).
     * Used in the view to grey out the cooldown field.
     */
    public function editingIsLastPhase(): bool
    {
        $count = count($this->phases);
        if ($count === 0) {
            return true;
        }
        if ($this->editingPhaseIndex === null) {
            return true;
        }
        return $this->editingPhaseIndex === $count - 1;
    }

    public function render(): View
    {
        return view('livewire.program-editor');
    }

    // ── Internals ─────────────────────────────────────────────────────────

    private function resetPhaseForm(): void
    {
        $this->phaseLabel    = '';
        $this->phaseDuration = 30;
        $this->phaseReps     = 3;
        $this->phasePause    = 0;
        $this->phaseCooldown = 0;
        $this->phaseColor    = '#3b82f6';
        $this->editingPhaseIndex = null;
    }
}
