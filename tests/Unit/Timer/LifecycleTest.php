<?php

declare(strict_types=1);

use App\Enum\StateMachine;
use App\Events\ProgramCompleted;
use App\Models\Program;
use App\Timer\TimerRunner;
use Illuminate\Support\Facades\Event;

// ── Pause resumes from exact position ────────────────────────────────────────

test('pause preserves exact remaining seconds and resumes correctly', function (): void {
    $prog = Program::create(['name' => 'Lifecycle']);
    $prog->phases()->create(['label' => 'Work', 'duration' => 20, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6', 'sort_order' => 0]);

    $runner = new TimerRunner();
    $runner->load($prog);
    $runner->start();
    for ($i = 0; $i < 5; $i++) $runner->tick(); // skip prepare

    // Run 7 seconds → 13 remaining
    for ($i = 0; $i < 7; $i++) $runner->tick();
    expect($runner->cursor()->remaining)->toBe(13);

    $runner->pause();
    expect($runner->cursor()->remaining)->toBe(13);  // unchanged during pause

    // "Phone call" — run tick during pause (must not advance)
    $runner->tick();
    expect($runner->cursor()->remaining)->toBe(13);  // tick is no-op while paused

    $runner->resume();
    expect($runner->cursor()->remaining)->toBe(13);  // still 13 after resume
    expect($runner->cursor()->state)->toBe(StateMachine::running);
});

// ── Pause mid-pause-segment ────────────────────────────────────────────────────

test('user can pause during inter-rep pause and resume to pause state', function (): void {
    $prog = Program::create(['name' => 'Mid-pause test']);
    $prog->phases()->create(['label' => 'Work', 'duration' => 5, 'repetitions' => 2, 'pause' => 10, 'cooldown' => 0, 'color' => '#3b82f6', 'sort_order' => 0]);

    $runner = new TimerRunner();
    $runner->load($prog);
    $runner->start();
    for ($i = 0; $i < 5; $i++) $runner->tick(); // skip prepare

    // Finish rep 1 (5 ticks) → enter pause
    for ($i = 0; $i < 5; $i++) $runner->tick();
    expect($runner->cursor()->state)->toBe(StateMachine::pause);

    // Tick 3 seconds into the pause
    for ($i = 0; $i < 3; $i++) $runner->tick();
    expect($runner->cursor()->remaining)->toBe(7);

    // User pauses mid-pause
    $runner->pause();
    expect($runner->cursor()->state)->toBe(StateMachine::paused);
    expect($runner->cursor()->remaining)->toBe(7);  // frozen

    $runner->resume();
    expect($runner->cursor()->state)->toBe(StateMachine::pause);   // back to pause sub-state
    expect($runner->cursor()->remaining)->toBe(7);
});

// ── Discard → no history / no event ──────────────────────────────────────────

test('discard does not dispatch ProgramCompleted', function (): void {
    Event::fake([ProgramCompleted::class]);

    $prog = Program::create(['name' => 'Kill test']);
    $prog->phases()->create(['label' => 'Work', 'duration' => 30, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6', 'sort_order' => 0]);

    $runner = new TimerRunner();
    $runner->load($prog);
    $runner->start();
    for ($i = 0; $i < 15; $i++) $runner->tick(); // half-way
    $runner->discard();

    Event::assertNotDispatched(ProgramCompleted::class);
    expect($runner->program())->toBeNull();
    expect($runner->cursor()->isIdle())->toBeTrue();
});

// ── last_used_at updated on completion only ────────────────────────────────────

test('last_used_at is null before program completes', function (): void {
    $prog = Program::create(['name' => 'No touch yet']);
    $prog->phases()->create(['label' => 'Work', 'duration' => 5, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6', 'sort_order' => 0]);

    $runner = new TimerRunner();
    $runner->load($prog);
    $runner->start();
    for ($i = 0; $i < 3; $i++) $runner->tick(); // not done yet

    expect(Program::find($prog->id)->last_used_at)->toBeNull();
});

test('last_used_at is set after program completes', function (): void {
    $prog = Program::create(['name' => 'Touch on complete']);
    $prog->phases()->create(['label' => 'Work', 'duration' => 3, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6', 'sort_order' => 0]);

    $runner = new TimerRunner();
    $runner->load($prog);
    $runner->start();
    for ($i = 0; $i < 5; $i++) $runner->tick(); // skip prepare
    for ($i = 0; $i < 3; $i++) $runner->tick();

    expect($runner->cursor()->isCompleted())->toBeTrue();
    expect(Program::find($prog->id)->last_used_at)->not->toBeNull();
});
