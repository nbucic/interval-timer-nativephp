<?php

declare(strict_types=1);

use App\Enum\BeepLeadIn;
use App\Models\Program;
use Illuminate\Database\Eloquent\ModelNotFoundException;

test('create assigns a uuid id', function (): void {
    $prog = Program::create(['name' => 'Test']);
    expect($prog->id)->toBeString()->not->toBeEmpty()->toHaveLength(36);
});

test('program is persisted and retrievable by id', function (): void {
    $prog = Program::create(['name' => 'Persist Test']);

    $found = Program::find($prog->id);
    expect($found)->not->toBeNull()
        ->and($found->name)->toBe('Persist Test');
});

test('findOrFail returns same data that was saved', function (): void {
    $prog = Program::create([
        'name' => 'Round-trip',
        'beep_lead_in' => BeepLeadIn::Three,
        'end_sound' => 'triple',
    ]);
    $prog->phases()->create([
        'label' => 'Work',
        'duration' => 30,
        'repetitions' => 3,
        'pause' => 5,
        'cooldown' => 10,
        'color' => '#3b82f6',
        'sort_order' => 0,
    ]);

    $loaded = Program::findOrFail($prog->id);

    expect($loaded->name)->toBe('Round-trip')
        ->and($loaded->beep_lead_in)->toBe(BeepLeadIn::Three)
        ->and($loaded->end_sound)->toBe('triple')
        ->and($loaded->phases)->toHaveCount(1)
        ->and($loaded->phases[0]->label)->toBe('Work')
        ->and($loaded->phases[0]->duration)->toBe(30)
        ->and($loaded->phases[0]->repetitions)->toBe(3)
        ->and($loaded->phases[0]->pause)->toBe(5)
        ->and($loaded->phases[0]->cooldown)->toBe(10);
});

test('findOrFail throws ModelNotFoundException for unknown id', function (): void {
    expect(fn () => Program::findOrFail('00000000-0000-0000-0000-000000000000'))->toThrow(ModelNotFoundException::class);
});

test('loaded program is an instance of Program', function (): void {
    $prog = Program::create(['name' => 'Type test']);
    expect(Program::find($prog->id))->toBeInstanceOf(Program::class);
});

test('can add up to 10 phases', function (): void {
    $prog = Program::create(['name' => '10-phases']);
    for ($i = 0; $i < 10; $i++) {
        $prog->addPhase(['label' => "Phase {$i}", 'duration' => 10, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6']);
    }
    expect($prog->phases()->count())->toBe(10);
});

test('adding 11th phase throws OverflowException', function (): void {
    $prog = Program::create(['name' => 'Overflow']);
    for ($i = 0; $i < 10; $i++) {
        $prog->addPhase(['label' => "P{$i}", 'duration' => 5, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6']);
    }
    expect(fn () => $prog->addPhase(['label' => 'Too many', 'duration' => 5, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6']))
        ->toThrow(\OverflowException::class);
});

test('phase with 50 repetitions is valid', function (): void {
    $prog = Program::create(['name' => 'Reps test']);
    $phase = $prog->phases()->create(['label' => 'Max reps', 'duration' => 10, 'repetitions' => 50, 'pause' => 5, 'cooldown' => 0, 'color' => '#3b82f6', 'sort_order' => 0]);
    expect($phase->repetitions)->toBe(50);
});

test('phase with 51 repetitions throws RangeException', function (): void {
    $prog = Program::create(['name' => 'Reps test']);
    expect(fn () => $prog->phases()->create(['label' => 'Too many reps', 'duration' => 10, 'repetitions' => 51, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6', 'sort_order' => 0]))
        ->toThrow(\RangeException::class);
});

test('phase with 0 repetitions throws RangeException', function (): void {
    $prog = Program::create(['name' => 'Reps test']);
    expect(fn () => $prog->phases()->create(['label' => 'Zero reps', 'duration' => 10, 'repetitions' => 0, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6', 'sort_order' => 0]))
        ->toThrow(\RangeException::class);
});

test('all returns all saved programs', function (): void {
    Program::create(['name' => 'A']);
    Program::create(['name' => 'B']);
    expect(Program::all())->toHaveCount(2);
});

test('all returns empty collection when no programs exist', function (): void {
    expect(Program::all())->toBeEmpty();
});

test('delete removes the program from database', function (): void {
    $prog = Program::create(['name' => 'Delete me']);
    $id = $prog->id;
    $prog->delete();
    expect(Program::find($id))->toBeNull();
});
