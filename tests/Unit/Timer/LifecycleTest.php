<?php

declare(strict_types=1);

use App\Enum\StateMachine;
use App\Events\ProgramCompleted;
use App\Timer\Phase;
use App\Timer\TimerProgram;
use App\Timer\TimerRunner;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

// ── Pause resumes from exact position ────────────────────────────────────────

test('pause preserves exact remaining seconds and resumes correctly', function (): void {
    Storage::fake();

    $prog = TimerProgram::create('Lifecycle');
    $prog->addPhase(new Phase('Work', 20, 1, 0, 0, '#3b82f6'));
    $prog->save();

    $runner = new TimerRunner();
    $runner->load(TimerProgram::load($prog->id));
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
    Storage::fake();

    $prog = TimerProgram::create('Mid-pause test');
    $prog->addPhase(new Phase('Work', 5, 2, 10, 0, '#3b82f6'));
    $prog->save();

    $runner = new TimerRunner();
    $runner->load(TimerProgram::load($prog->id));
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
    Storage::fake();
    Event::fake([ProgramCompleted::class]);

    $prog = TimerProgram::create('Kill test');
    $prog->addPhase(new Phase('Work', 30, 1, 0, 0, '#3b82f6'));
    $prog->save();

    $runner = new TimerRunner();
    $runner->load(TimerProgram::load($prog->id));
    $runner->start();
    for ($i = 0; $i < 15; $i++) $runner->tick(); // half-way
    $runner->discard();

    Event::assertNotDispatched(ProgramCompleted::class);
    expect($runner->program())->toBeNull();
    expect($runner->cursor()->isIdle())->toBeTrue();
});

// ── last_used_at updated on completion only ────────────────────────────────────

test('last_used_at is null before program completes', function (): void {
    Storage::fake();

    $prog = TimerProgram::create('No touch yet');
    $prog->addPhase(new Phase('Work', 5, 1, 0, 0, '#3b82f6'));
    $prog->save();

    $runner = new TimerRunner();
    $runner->load(TimerProgram::load($prog->id));
    $runner->start();
    for ($i = 0; $i < 3; $i++) $runner->tick(); // not done yet

    expect(TimerProgram::load($prog->id)->lastUsedAt)->toBeNull();
});

test('last_used_at is set after program completes', function (): void {
    Storage::fake();

    $prog = TimerProgram::create('Touch on complete');
    $prog->addPhase(new Phase('Work', 3, 1, 0, 0, '#3b82f6'));
    $prog->save();

    $runner = new TimerRunner();
    $runner->load(TimerProgram::load($prog->id));
    $runner->start();
    for ($i = 0; $i < 5; $i++) $runner->tick(); // skip prepare
    for ($i = 0; $i < 3; $i++) $runner->tick();

    expect($runner->cursor()->isCompleted())->toBeTrue();
    expect(TimerProgram::load($prog->id)->lastUsedAt)->not->toBeNull();
});
