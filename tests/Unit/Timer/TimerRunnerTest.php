<?php

declare(strict_types=1);

use App\Enum\StateMachine;
use App\Models\Program;
use App\Timer\TimerCursor;
use App\Timer\TimerRunner;

// ── Helpers ───────────────────────────────────────────────────────────────────

function freshRunner(): TimerRunner
{
    return new TimerRunner();
}

/** Tick through the 5-second PREPARE countdown so the runner enters RUNNING. */
function skipPrepare(TimerRunner $runner): void
{
    for ($i = 0; $i < 5; $i++) $runner->tick();
}

function onePhaseProgram(int $duration = 10, int $reps = 1, int $pause = 0, int $cooldown = 0): Program
{
    $prog = Program::create(['name' => 'Single Phase']);
    $prog->phases()->create([
        'label' => 'Work',
        'duration' => $duration,
        'repetitions' => $reps,
        'pause' => $pause,
        'cooldown' => $cooldown,
        'color' => '#3b82f6',
        'sort_order' => 0,
    ]);
    return $prog->load('phases');
}

function twoPhaseProgram(): Program
{
    $prog = Program::create(['name' => 'Two Phases']);
    $prog->phases()->create([
        'label' => 'Work',
        'duration' => 5,
        'repetitions' => 2,
        'pause' => 2,
        'cooldown' => 3,
        'color' => '#3b82f6',
        'sort_order' => 0,
    ]);
    $prog->phases()->create([
        'label' => 'Rest',
        'duration' => 8,
        'repetitions' => 1,
        'pause' => 0,
        'cooldown' => 0,
        'color' => '#22c55e',
        'sort_order' => 1,
    ]);
    return $prog->load('phases');
}

// ── idle state ────────────────────────────────────────────────────────────────

test('runner starts in idle state', function (): void {
    expect(freshRunner()->cursor()->isIdle())->toBeTrue();
});

test('tick on idle cursor is a no-op', function (): void {
    $runner = freshRunner();
    $runner->load(onePhaseProgram());
    $runner->tick();
    expect($runner->cursor()->isIdle())->toBeTrue();
});

test('start without load throws RuntimeException', function (): void {
    expect(fn() => freshRunner()->start())->toThrow(\RuntimeException::class);
});

test('start with empty program throws RuntimeException', function (): void {
    $prog = Program::create(['name' => 'Empty']);
    $runner = freshRunner();
    $runner->load($prog);
    expect(fn() => $runner->start())->toThrow(\RuntimeException::class);
});

// ── idle → prepare ────────────────────────────────────────────────────────────

test('start transitions to prepare state with 5s remaining', function (): void {
    $runner = freshRunner();
    $runner->load(onePhaseProgram(10));
    $runner->start();

    expect($runner->cursor()->state)->toBe(StateMachine::prepare)
        ->and($runner->cursor()->remaining)->toBe(5);
});

test('after 5 prepare ticks runner enters running state', function (): void {
    $runner = freshRunner();
    $runner->load(onePhaseProgram(10));
    $runner->start();
    skipPrepare($runner);

    expect($runner->cursor()->state)->toBe(StateMachine::running)
        ->and($runner->cursor()->remaining)->toBe(10);
});

test('pause is a no-op during prepare', function (): void {
    $runner = freshRunner();
    $runner->load(onePhaseProgram(10));
    $runner->start();
    $runner->pause();

    expect($runner->cursor()->state)->toBe(StateMachine::prepare);
});

// ── running → pause (inter-rep) ───────────────────────────────────────────────

test('tick at rep end with pause configured enters pause state', function (): void {
    $runner = freshRunner();
    $runner->load(onePhaseProgram(duration: 5, reps: 2, pause: 3));
    $runner->start();
    skipPrepare($runner);

    for ($i = 0; $i < 5; $i++) $runner->tick();

    expect($runner->cursor()->state)->toBe(StateMachine::pause)
        ->and($runner->cursor()->remaining)->toBe(3);
});

// ── pause → running (next rep) ────────────────────────────────────────────────

test('ticking through pause advances to next rep', function (): void {
    $runner = freshRunner();
    $runner->load(onePhaseProgram(duration: 5, reps: 2, pause: 3));
    $runner->start();
    skipPrepare($runner);

    for ($i = 0; $i < 5; $i++) $runner->tick();
    for ($i = 0; $i < 3; $i++) $runner->tick();

    expect($runner->cursor()->state)->toBe(StateMachine::running)
        ->and($runner->cursor()->repIndex)->toBe(1);
});

// ── running → cooldown ────────────────────────────────────────────────────────

test('last rep with cooldown does not enter cooldown state', function (): void {
    $runner = freshRunner();
    $runner->load(onePhaseProgram(duration: 5, cooldown: 4));
    $runner->start();
    skipPrepare($runner);

    for ($i = 0; $i < 5; $i++) $runner->tick();

    expect($runner->cursor()->state)->toBe(StateMachine::completed)
        ->and($runner->cursor()->remaining)->toBe(0);
});

// ── cooldown → completed (single phase) ───────────────────────────────────────

test('ticking through cooldown on last phase completes program', function (): void {
    $completed = false;

    $runner = freshRunner();
    $prog = onePhaseProgram(duration: 3, reps: 1, cooldown: 2);
    $runner->load($prog);
    $runner->start();
    skipPrepare($runner);

    $runner->onTick(function (TimerCursor $c) use (&$completed): void {
        if ($c->isCompleted()) $completed = true;
    });

    for ($i = 0; $i < 5; $i++) $runner->tick();

    expect($completed)->toBeTrue()
        ->and($runner->cursor()->state)->toBe(StateMachine::completed);
});

// ── multi-phase transition ────────────────────────────────────────────────────

test('completing all reps+cooldown of phase 0 advances to phase 1', function (): void {
    $runner = freshRunner();
    $runner->load(twoPhaseProgram());
    $runner->start();
    skipPrepare($runner);

    for ($i = 0; $i < 5; $i++) $runner->tick();  // rep 0
    for ($i = 0; $i < 2; $i++) $runner->tick();  // pause
    for ($i = 0; $i < 5; $i++) $runner->tick();  // rep 1
    for ($i = 0; $i < 3; $i++) $runner->tick();  // cooldown

    expect($runner->cursor()->phaseIndex)->toBe(1)
        ->and($runner->cursor()->state)->toBe(StateMachine::running);
});

// ── pause / resume ────────────────────────────────────────────────────────────

test('pause sets cursor to paused', function (): void {
    $runner = freshRunner();
    $runner->load(onePhaseProgram(10));
    $runner->start();
    skipPrepare($runner);
    $runner->tick();
    $runner->pause();

    expect($runner->cursor()->state)->toBe(StateMachine::paused)
        ->and($runner->cursor()->remaining)->toBe(9);
});

test('resume restores running state with same remaining', function (): void {
    $runner = freshRunner();
    $runner->load(onePhaseProgram(10));
    $runner->start();
    skipPrepare($runner);
    $runner->tick();
    $runner->pause();
    $runner->resume();

    expect($runner->cursor()->state)->toBe(StateMachine::running)
        ->and($runner->cursor()->remaining)->toBe(9);
});

test('pause during pause-segment restores to pause after resume', function (): void {
    $runner = freshRunner();
    $runner->load(onePhaseProgram(duration: 5, reps: 2, pause: 5));
    $runner->start();
    skipPrepare($runner);

    for ($i = 0; $i < 5; $i++) $runner->tick(); // into pause state
    $runner->pause();
    $runner->resume();

    expect($runner->cursor()->state)->toBe(StateMachine::pause);
});

// ── 10-phase limit ────────────────────────────────────────────────────────────

test('program rejects 11th phase', function (): void {
    $prog = Program::create(['name' => 'Overflow']);
    for ($i = 0; $i < 10; $i++) {
        $prog->addPhase(['label' => "P{$i}", 'duration' => 5, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6']);
    }
    expect(fn() => $prog->addPhase(['label' => 'P11', 'duration' => 5, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6']))
        ->toThrow(\OverflowException::class);
});

// ── discard ───────────────────────────────────────────────────────────────────

test('discard resets cursor to idle and clears program', function (): void {
    $runner = freshRunner();
    $runner->load(onePhaseProgram(10));
    $runner->start();
    $runner->tick();
    $runner->discard();

    expect($runner->cursor()->isIdle())->toBeTrue()
        ->and($runner->program())->toBeNull();
});
