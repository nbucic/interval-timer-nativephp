<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enum\BeepLeadIn;
use App\Timer\AppSettings;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;
use JsonException;
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
    public float $volume = 0.8;
    public bool $keepScreenOn = true;

    public bool $saved = false;

    public function mount(): void
    {
        $settings = AppSettings::load();

        $this->defaultBeepLeadIn = $settings->defaultBeepLeadIn;
        $this->defaultEndSound = $settings->defaultEndSound;
        $this->soundMode = $settings->soundMode;
        $this->volume = $settings->volume;
        $this->keepScreenOn = $settings->keepScreenOn;
    }

    public function render(): View
    {
        return view('livewire.settings');
    }

    /**
     * @throws JsonException
     */
    public function save(): void
    {
        $this->validate([
            'defaultBeepLeadIn' => ['required', new Enum(BeepLeadIn::class)],
            'defaultEndSound' => 'required|in:triple,chime',
            'soundMode' => 'required|in:beep,voice',
            'volume' => 'required|numeric|min:0|max:1',
            'keepScreenOn' => 'boolean',
        ]);

        $settings = AppSettings::load();

        $settings->defaultBeepLeadIn = $this->defaultBeepLeadIn;
        $settings->defaultEndSound = $this->defaultEndSound;
        $settings->soundMode = $this->soundMode;
        $settings->volume = round((float)$this->volume, 2);
        $settings->keepScreenOn = $this->keepScreenOn;

        $settings->save();

        $this->dispatch('settingsLoaded', soundMode: $this->soundMode, volume: $this->volume, program: null);

        $this->saved = true;
    }
}
