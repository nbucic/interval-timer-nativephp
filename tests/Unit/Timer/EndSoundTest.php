<?php

declare(strict_types=1);

use App\Events\ProgramCompleted;
use App\Timer\Phase;
use App\Timer\TimerProgram;
use App\Timer\TimerRunner;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

// ── ProgramCompleted event ────────────────────────────────────────────────────

test('ProgramCompleted dispatched exactly once on program finish', function (): void {
    Storage::fake();
    Event::fake([ProgramCompleted::class]);

    $prog = TimerProgram::create('End sound test');
    $prog->endSound = 'triple';
    $prog->addPhase(new Phase('Work', 3, 1, 0, 0, '#3b82f6'));
    $prog->save();

    $runner = new TimerRunner();
    $runner->load(TimerProgram::load($prog->id));
    $runner->start();
    for ($i = 0; $i < 5; $i++) $runner->tick(); // skip prepare
    for ($i = 0; $i < 3; $i++) $runner->tick();

    Event::assertDispatchedTimes(ProgramCompleted::class, 1);
});

test('ProgramCompleted carries correct endSound', function (): void {
    Storage::fake();
    Event::fake([ProgramCompleted::class]);

    $prog = TimerProgram::create('Chime test');
    $prog->endSound = 'chime';
    $prog->addPhase(new Phase('Work', 2, 1, 0, 0, '#3b82f6'));
    $prog->save();

    $runner = new TimerRunner();
    $runner->load(TimerProgram::load($prog->id));
    $runner->start();
    for ($i = 0; $i < 5; $i++) $runner->tick(); // skip prepare
    for ($i = 0; $i < 2; $i++) $runner->tick();

    Event::assertDispatched(ProgramCompleted::class, function (ProgramCompleted $e): bool {
        return $e->endSound === 'chime';
    });
});

test('ProgramCompleted not dispatched while timer is still running', function (): void {
    Storage::fake();
    Event::fake([ProgramCompleted::class]);

    $prog = TimerProgram::create('Not done yet');
    $prog->addPhase(new Phase('Work', 10, 1, 0, 0, '#3b82f6'));
    $prog->save();

    $runner = new TimerRunner();
    $runner->load(TimerProgram::load($prog->id));
    $runner->start();

    // Only tick 5 of 10 seconds
    for ($i = 0; $i < 5; $i++) $runner->tick();

    Event::assertNotDispatched(ProgramCompleted::class);
});

test('ProgramCompleted not dispatched on discard', function (): void {
    Storage::fake();
    Event::fake([ProgramCompleted::class]);

    $prog = TimerProgram::create('Discard test');
    $prog->addPhase(new Phase('Work', 5, 1, 0, 0, '#3b82f6'));
    $prog->save();

    $runner = new TimerRunner();
    $runner->load(TimerProgram::load($prog->id));
    $runner->start();
    $runner->tick();
    $runner->discard();

    Event::assertNotDispatched(ProgramCompleted::class);
});

test('ProgramCompleted carries total duration', function (): void {
    Storage::fake();
    Event::fake([ProgramCompleted::class]);

    $prog = TimerProgram::create('Duration test');
    $prog->addPhase(new Phase('Work', 5, 2, 3, 0, '#3b82f6')); // 5+3+5 = 13
    $prog->save();

    $runner = new TimerRunner();
    $runner->load(TimerProgram::load($prog->id));
    $runner->start();
    for ($i = 0; $i < 5; $i++) $runner->tick(); // skip prepare
    // Rep 1 (5) + pause (3) + rep 2 (5) = 13 ticks
    for ($i = 0; $i < 13; $i++) $runner->tick();

    Event::assertDispatched(ProgramCompleted::class, function (ProgramCompleted $e): bool {
        return $e->durationSeconds === 13;
    });
});
