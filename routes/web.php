<?php

use App\Livewire\Library;
use App\Livewire\ProgramEditor;
use App\Livewire\Settings;
use App\Livewire\TimerScreen;
use Illuminate\Support\Facades\Route;

// Library (default / home tab)
Route::get('/', Library::class)->name('library');

// Program editor — 'create' handled by ProgramEditor::mount()
Route::get('/programs/{id}/edit', ProgramEditor::class)->name('programs.edit');

// Timer — optional program ID; without one shows the "pick a program" prompt
Route::get('/timer/{id?}', TimerScreen::class)->name('timer');

// Settings tab
Route::get('/settings', Settings::class)->name('settings');
