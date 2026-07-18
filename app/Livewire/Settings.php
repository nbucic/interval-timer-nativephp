<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enum\BeepLeadIn;
use App\Models\Setting;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Settings — Interval Timer')]
class Settings extends Component
{
    public BeepLeadIn $defaultBeepLeadIn = BeepLeadIn::Three;

    public string $defaultEndSound = 'triple';

    public string $soundMode = 'beep';

    public float $volume = 1.0;

    public bool $keepScreenOn = true;

    public bool $saved = false;

    public function mount(): void
    {
        $settings = Setting::current();

        $this->defaultBeepLeadIn = $settings->default_beep_lead_in;
        $this->defaultEndSound = $settings->default_end_sound;
        $this->soundMode = $settings->sound_mode;
        $this->volume = $settings->volume;
        $this->keepScreenOn = $settings->keep_screen_on;
    }

    public function render(): View
    {
        return view('livewire.settings');
    }

    public function save(): void
    {
        $this->validate([
            'defaultBeepLeadIn' => ['required', new Enum(BeepLeadIn::class)],
            'defaultEndSound' => 'required|in:triple,chime',
            'soundMode' => 'required|in:beep,voice',
            'volume' => 'required|numeric|min:0|max:1',
            'keepScreenOn' => 'boolean',
        ]);

        $settings = Setting::current();

        $settings->default_beep_lead_in = $this->defaultBeepLeadIn;
        $settings->default_end_sound = $this->defaultEndSound;
        $settings->sound_mode = $this->soundMode;
        $settings->volume = round((float)$this->volume, 2);
        $settings->keep_screen_on = $this->keepScreenOn;

        $settings->save();

        $this->dispatch('settingsLoaded', soundMode: $this->soundMode, volume: $this->volume, keepScreenOn: $this->keepScreenOn, program: null);

        $this->saved = true;
    }

    public function updateAndTest(string $soundMode): void
    {
        $this->soundMode = $soundMode;
        if (app()->isLocal()) {
            if ($soundMode === 'voice') {
                $this->dispatch('play-TTS-Sound', text: '3, 2, 1, - GO');
            } else {
                $this->dispatch('playBeepSound', sound: 'triple');
            }
        }
    }
}
