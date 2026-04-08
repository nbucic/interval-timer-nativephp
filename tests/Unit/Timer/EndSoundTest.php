<?php

declare(strict_types=1);

use App\Events\ProgramCompleted;
use App\Models\Program;
use App\Timer\TimerRunner;
use Illuminate\Support\Facades\Event;

// ── ProgramCompleted event ────────────────────────────────────────────────────

test('ProgramCompleted dispatched exactly once on program finish', function (): void {
    Event::fake([ProgramCompleted::class]);

    $prog = Program::create(['name' => 'End sound test', 'end_sound' => 'triple']);
    $prog->phases()->create(['label' => 'Work', 'duration' => 3, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6', 'sort_order' => 0]);

    $runner = new TimerRunner();
    $runner->load($prog);
    $runner->start();
    for ($i = 0; $i < 5; $i++) $runner->tick(); // skip prepare
    for ($i = 0; $i < 3; $i++) $runner->tick();

    Event::assertDispatchedTimes(ProgramCompleted::class, 1);
});

test('ProgramCompleted carries correct endSound', function (): void {
    Event::fake([ProgramCompleted::class]);

    $prog = Program::create(['name' => 'Chime test', 'end_sound' => 'chime']);
    $prog->phases()->create(['label' => 'Work', 'duration' => 2, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6', 'sort_order' => 0]);

    $runner = new TimerRunner();
    $runner->load($prog);
    $runner->start();
    for ($i = 0; $i < 5; $i++) $runner->tick(); // skip prepare
    for ($i = 0; $i < 2; $i++) $runner->tick();

    Event::assertDispatched(ProgramCompleted::class, function (ProgramCompleted $e): bool {
        return $e->endSound === 'chime';
    });
});

test('ProgramCompleted not dispatched while timer is still running', function (): void {
    Event::fake([ProgramCompleted::class]);

    $prog = Program::create(['name' => 'Not done yet']);
    $prog->phases()->create(['label' => 'Work', 'duration' => 10, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6', 'sort_order' => 0]);

    $runner = new TimerRunner();
    $runner->load($prog);
    $runner->start();

    // Only tick 5 of 10 seconds
    for ($i = 0; $i < 5; $i++) $runner->tick();

    Event::assertNotDispatched(ProgramCompleted::class);
});

test('ProgramCompleted not dispatched on discard', function (): void {
    Event::fake([ProgramCompleted::class]);

    $prog = Program::create(['name' => 'Discard test']);
    $prog->phases()->create(['label' => 'Work', 'duration' => 5, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6', 'sort_order' => 0]);

    $runner = new TimerRunner();
    $runner->load($prog);
    $runner->start();
    $runner->tick();
    $runner->discard();

    Event::assertNotDispatched(ProgramCompleted::class);
});

test('ProgramCompleted carries total duration', function (): void {
    Event::fake([ProgramCompleted::class]);

    $prog = Program::create(['name' => 'Duration test']);
    $prog->phases()->create(['label' => 'Work', 'duration' => 5, 'repetitions' => 2, 'pause' => 3, 'cooldown' => 0, 'color' => '#3b82f6', 'sort_order' => 0]);

    $runner = new TimerRunner();
    $runner->load($prog);
    $runner->start();
    for ($i = 0; $i < 5; $i++) $runner->tick(); // skip prepare
    // Rep 1 (5) + pause (3) + rep 2 (5) = 13 ticks
    for ($i = 0; $i < 13; $i++) $runner->tick();

    Event::assertDispatched(ProgramCompleted::class, function (ProgramCompleted $e): bool {
        return $e->durationSeconds === 13;
    });
});
