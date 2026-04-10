<?php

declare(strict_types=1);

namespace App\Timer;

use App\Enum\BeepLeadIn;
use Illuminate\Support\Facades\Storage;
use JsonException;

/**
 * Global app settings persisted to storage/app/settings.json.
 */
class AppSettings
{
    private const string PATH = 'settings.json';

    public BeepLeadIn $defaultBeepLeadIn = BeepLeadIn::Three;    // 3 | 5
    public string $defaultEndSound = 'triple'; // 'triple' | 'chime'
    public string $soundMode = 'beep';   // 'beep' | 'voice'
    public float $volume = 0.8;       // 0–1
    public bool $keepScreenOn = true;

    private function __construct()
    {
    }

    public static function load(): self
    {
        $settings = new self();

        if (Storage::exists(self::PATH)) {
            try {
                $data = json_decode(
                    Storage::get(self::PATH),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
            } catch (JsonException) {

            }
            $settings->defaultBeepLeadIn = BeepLeadIn::from((int)($data['default_beep_lead_in'] ?? 3));
            $settings->defaultEndSound = $data['default_end_sound'] ?? 'triple';
            $settings->soundMode = $data['sound_mode'] ?? 'beep';
            $settings->volume = (float)($data['volume'] ?? 0.8);
            $settings->keepScreenOn = (bool)($data['keep_screen_on'] ?? true);
        }

        return $settings;
    }

    /**
     * @throws JsonException
     */
    public function save(): void
    {
        Storage::put(
            self::PATH,
            json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    public function toArray(): array
    {
        return [
            'default_beep_lead_in' => $this->defaultBeepLeadIn,
            'default_end_sound' => $this->defaultEndSound,
            'sound_mode' => $this->soundMode,
            'volume' => $this->volume,
            'keep_screen_on' => $this->keepScreenOn,
        ];
    }
}
