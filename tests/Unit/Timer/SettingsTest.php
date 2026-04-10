<?php

declare(strict_types=1);

use App\Enum\BeepLeadIn;
use App\Timer\AppSettings;
use App\Timer\TimerProgram;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();
});

// ── Default values ────────────────────────────────────────────────────────────

test('settings load with defaults when no file exists', function (): void {
    $s = AppSettings::load();

    expect($s->defaultBeepLeadIn)->toBe(BeepLeadIn::Three)
        ->and($s->defaultEndSound)->toBe('triple')
        ->and($s->soundMode)->toBe('beep')
        ->and($s->volume)->toBe(0.8)
        ->and($s->keepScreenOn)->toBeTrue();
});

// ── Save / load round-trip ────────────────────────────────────────────────────

test('settings save and reload correctly', function (): void {
    $s = AppSettings::load();
    $s->defaultBeepLeadIn = BeepLeadIn::Five;
    $s->defaultEndSound   = 'chime';
    $s->soundMode         = 'voice';
    $s->volume            = 0.5;
    $s->keepScreenOn      = false;
    $s->save();

    $loaded = AppSettings::load();
    expect($loaded->defaultBeepLeadIn)->toBe(BeepLeadIn::Five)
        ->and($loaded->defaultEndSound)->toBe('chime')
        ->and($loaded->soundMode)->toBe('voice')
        ->and($loaded->volume)->toBe(0.5)
        ->and($loaded->keepScreenOn)->toBeFalse();
});

test('settings writes to storage/app/settings.json', function (): void {
    $s = AppSettings::load();
    $s->save();
    expect(Storage::exists('settings.json'))->toBeTrue();
});

// ── Per-program seeding ───────────────────────────────────────────────────────

test('new program inherits beepLeadIn from settings', function (): void {
    $settings = AppSettings::load();
    $settings->defaultBeepLeadIn = BeepLeadIn::Five;
    $settings->save();

    $prog = TimerProgram::create('Seeded');
    expect($prog->beepLeadIn)->toBe(BeepLeadIn::Five);
});

test('new program inherits endSound from settings', function (): void {
    $settings = AppSettings::load();
    $settings->defaultEndSound = 'chime';
    $settings->save();

    $prog = TimerProgram::create('Seeded chime');
    expect($prog->endSound)->toBe('chime');
});

test('per-program beepLeadIn can be overridden independently of global', function (): void {
    $settings = AppSettings::load();
    $settings->defaultBeepLeadIn = BeepLeadIn::Three;
    $settings->save();

    $prog = TimerProgram::create('Override');
    $prog->beepLeadIn = BeepLeadIn::Five;
    $prog->save();

    $loaded = TimerProgram::load($prog->id);
    expect($loaded->beepLeadIn)->toBe(BeepLeadIn::Five)
        ->and(AppSettings::load()->defaultBeepLeadIn)->toBe(BeepLeadIn::Three);
});
