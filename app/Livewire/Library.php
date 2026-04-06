<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Timer\TimerProgram;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

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
        $this->programs = TimerProgram::all();
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

        $program = TimerProgram::create(trim($this->newName));
        $program->save();

        $this->showCreate = false;
        $this->newName    = '';

        $this->redirect("/programs/{$program->id}/edit", navigate: true);
    }

    public function deleteProgram(string $id): void
    {
        try {
            $program = TimerProgram::load($id);
            $program->delete();
        } catch (\RuntimeException) {
            // Already gone — ignore.
        }
        $this->loadPrograms();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.library');
    }
}
