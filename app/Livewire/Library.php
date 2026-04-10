<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Program;
use App\Models\Setting;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Library — Interval Timer')]
class Library extends Component
{
    public string $newName = '';
    public bool   $showCreate = false;

    public array $programs = [];

    public function mount(): void
    {
        $this->loadPrograms();
    }

    public function loadPrograms(): void
    {
        $this->programs = Program::with('phases')
            ->orderByRaw('COALESCE(last_used_at, created_at) DESC')
            ->get()
            ->toArray();
    }

    public function openCreate(): void
    {
        $this->newName    = '';
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

        $settings = Setting::current();

        $program = Program::create([
            'name'         => trim($this->newName),
            'beep_lead_in' => $settings->default_beep_lead_in,
            'end_sound'    => $settings->default_end_sound,
        ]);

        $this->showCreate = false;
        $this->newName    = '';

        $this->redirect("/programs/$program->id/edit");
    }

    public function deleteProgram(string $id): void
    {
        Program::find($id)?->delete();
        $this->loadPrograms();
    }

    public function render(): View
    {
        return view('livewire.library');
    }
}
