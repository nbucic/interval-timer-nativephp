<?php

declare(strict_types=1);

use App\Enum\BeepLeadIn;
use App\Timer\AppSettings;
use App\Timer\Phase;
use App\Timer\TimerProgram;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();

    $s = AppSettings::load();
    $s->defaultBeepLeadIn = BeepLeadIn::Three;
    $s->defaultEndSound   = 'triple';
    $s->save();
});

// ── create() ──────────────────────────────────────────────────────────────────

test('create seeds beepLeadIn and endSound from settings', function (): void {
    $prog = TimerProgram::create('My Workout');

    expect($prog->beepLeadIn)->toBe(BeepLeadIn::Three)
        ->and($prog->endSound)->toBe('triple')
        ->and($prog->name)->toBe('My Workout')
        ->and($prog->phases)->toBeArray()->toBeEmpty();
});

test('create assigns a uuid id', function (): void {
    $prog = TimerProgram::create('Test');
    expect($prog->id)->toBeString()->not->toBeEmpty();
});

// ── save() / load() round-trip (pipe-operator chain) ─────────────────────────

test('save writes json file under programs directory', function (): void {
    $prog = TimerProgram::create('Test');
    $prog->save();
    expect(Storage::exists("programs/{$prog->id}.json"))->toBeTrue();
});

test('load returns same data that was saved', function (): void {
    $prog = TimerProgram::create('Round-trip');
    $prog->addPhase(new Phase('Work', 30, 3, 5, 10, '#3b82f6'));
    $prog->save();

    $loaded = TimerProgram::load($prog->id);

    expect($loaded->name)->toBe('Round-trip')
        ->and($loaded->beepLeadIn)->toBe(BeepLeadIn::Three)
        ->and($loaded->endSound)->toBe('triple')
        ->and($loaded->phases)->toHaveCount(1)
        ->and($loaded->phases[0]->label)->toBe('Work')
        ->and($loaded->phases[0]->duration)->toBe(30)
        ->and($loaded->phases[0]->repetitions)->toBe(3)
        ->and($loaded->phases[0]->pause)->toBe(5)
        ->and($loaded->phases[0]->cooldown)->toBe(10);
});

test('load throws RuntimeException for unknown id', function (): void {
    expect(fn () => TimerProgram::load('does-not-exist'))->toThrow(\RuntimeException::class);
});

test('loaded program is an instance of TimerProgram', function (): void {
    $prog = TimerProgram::create('Pipe test');
    $prog->save();
    expect(TimerProgram::load($prog->id))->toBeInstanceOf(TimerProgram::class);
});

// ── addPhase / 10-phase cap ───────────────────────────────────────────────────

test('can add up to 10 phases', function (): void {
    $prog = TimerProgram::create('10-phases');
    for ($i = 0; $i < 10; $i++) {
        $prog->addPhase(new Phase("Phase {$i}", 10, 1, 0, 0, '#3b82f6'));
    }
    expect($prog->phases)->toHaveCount(10);
});

test('adding 11th phase throws OverflowException', function (): void {
    $prog = TimerProgram::create('Overflow');
    for ($i = 0; $i < 10; $i++) {
        $prog->addPhase(new Phase("P{$i}", 5, 1, 0, 0, '#3b82f6'));
    }
    expect(fn () => $prog->addPhase(new Phase('Too many', 5, 1, 0, 0, '#3b82f6')))
        ->toThrow(\OverflowException::class);
});

// ── Phase 50-rep cap ──────────────────────────────────────────────────────────

test('phase with 50 repetitions is valid', function (): void {
    $phase = new Phase('Max reps', 10, 50, 5, 0, '#3b82f6');
    expect($phase->repetitions)->toBe(50);
});

test('phase with 51 repetitions throws RangeException', function (): void {
    expect(fn () => new Phase('Too many reps', 10, 51, 0, 0, '#3b82f6'))
        ->toThrow(\RangeException::class);
});

test('phase with 0 repetitions throws RangeException', function (): void {
    expect(fn () => new Phase('Zero reps', 10, 0, 0, 0, '#3b82f6'))
        ->toThrow(\RangeException::class);
});

// ── all() ─────────────────────────────────────────────────────────────────────

test('all returns all saved programs', function (): void {
    $a = TimerProgram::create('A'); $a->save();
    $b = TimerProgram::create('B'); $b->save();
    expect(TimerProgram::all())->toHaveCount(2);
});

test('all returns empty array when no programs exist', function (): void {
    expect(TimerProgram::all())->toBeArray()->toBeEmpty();
});

// ── delete() ─────────────────────────────────────────────────────────────────

test('delete removes the json file', function (): void {
    $prog = TimerProgram::create('Delete me');
    $prog->save();
    $prog->delete();
    expect(Storage::exists("programs/{$prog->id}.json"))->toBeFalse();
});
