<?php

declare(strict_types=1);

use App\Enum\BeepLeadIn;
use App\Models\Setting;

test('settings load with defaults when no row exists', function (): void {
    $s = Setting::current();

    expect($s->default_beep_lead_in)->toBe(BeepLeadIn::Three)
        ->and($s->default_end_sound)->toBe('triple')
        ->and($s->sound_mode)->toBe('beep')
        ->and($s->volume)->toBe(0.8)
        ->and($s->keep_screen_on)->toBeTrue();
});

test('settings save and reload correctly', function (): void {
    $s = Setting::current();
    $s->update([
        'default_beep_lead_in' => BeepLeadIn::Five,
        'default_end_sound'    => 'chime',
        'sound_mode'           => 'voice',
        'volume'               => 0.5,
        'keep_screen_on'       => false,
    ]);

    $loaded = Setting::current();
    expect($loaded->default_beep_lead_in)->toBe(BeepLeadIn::Five)
        ->and($loaded->default_end_sound)->toBe('chime')
        ->and($loaded->sound_mode)->toBe('voice')
        ->and($loaded->volume)->toBe(0.5)
        ->and($loaded->keep_screen_on)->toBeFalse();
});

test('settings.current() creates a DB row', function (): void {
    expect(Setting::count())->toBe(0);
    Setting::current();
    expect(Setting::count())->toBe(1);
});
