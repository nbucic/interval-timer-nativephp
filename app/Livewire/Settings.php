<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Timer\AppSettings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Settings — Interval Timer')]
class Settings extends Component
{
    public int    $defaultBeepLeadIn = 3;
    public string $defaultEndSound   = 'triple';
    public string $soundMode         = 'beep';
    public float  $volume            = 0.8;
    public bool   $keepScreenOn      = true;

    public bool   $saved = false;

    public function mount(): void
    {
        $settings = AppSettings::load();

        $this->defaultBeepLeadIn = $settings->defaultBeepLeadIn;
        $this->defaultEndSound   = $settings->defaultEndSound;
        $this->soundMode         = $settings->soundMode;
        $this->volume            = $settings->volume;
        $this->keepScreenOn      = $settings->keepScreenOn;
    }

    public function save(): void
    {
        $this->validate([
            'defaultBeepLeadIn' => 'required|in:3,5',
            'defaultEndSound'   => 'required|in:triple,chime',
            'soundMode'         => 'required|in:beep,voice',
            'volume'            => 'required|numeric|min:0|max:1',
            'keepScreenOn'      => 'boolean',
        ]);

        $settings = AppSettings::load();

        $settings->defaultBeepLeadIn = $this->defaultBeepLeadIn;
        $settings->defaultEndSound   = $this->defaultEndSound;
        $settings->soundMode         = $this->soundMode;
        $settings->volume            = round((float) $this->volume, 2);
        $settings->keepScreenOn      = $this->keepScreenOn;

        $settings->save();

        $this->saved = true;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.settings');
    }
}
