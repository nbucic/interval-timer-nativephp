<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Timer\AppSettings;
use App\Timer\TimerProgram;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use JsonException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts.app')]
#[Title('Library — Interval Timer')]
class Library extends Component
{
    public string $newName = '';
    public bool   $showCreate = false;

    /** @var TimerProgram[] */
    public array $programs = [];

    public function mount(): void
    {
        $this->loadPrograms();
    }

    public function loadPrograms(): void
    {
        $this->programs = array_map(
            static fn(TimerProgram $p) => $p->toArray(),
            TimerProgram::all()
        );
    }

    public function openCreate(): void
    {
        $this->newName   = '';
        $this->showCreate = true;
    }

    public function cancelCreate(): void
    {
        $this->showCreate = false;
        $this->newName    = '';
    }

    public function createProgram(): void
    {
        $this->validate(['newName' => 'required|string|max:60']);

        $settings = AppSettings::load();
        $program = TimerProgram::create(trim($this->newName));
        $program->beepLeadIn = $settings->defaultBeepLeadIn;
        $program->endSound = $settings->defaultEndSound;
        $program->save();

        Log::info('Message', ['data' => $this]);

        $this->showCreate = false;
        $this->newName    = '';

        $this->redirect("/programs/$program->id/edit");
    }

    public function deleteProgram(string $id): void
    {
        try {
            $program = TimerProgram::load($id);
            $program->delete();
        } catch (RuntimeException|JsonException) {
            // Already gone — ignore.
        }

        $this->loadPrograms();
    }

    public function render(): View
    {
        return view('livewire.library');
    }
}
