<?php

declare(strict_types=1);

use App\Enum\StateMachine;
use App\Timer\Phase;
use App\Timer\TimerCursor;
use App\Timer\TimerProgram;
use App\Timer\TimerRunner;
use Illuminate\Support\Facades\Storage;

// ── Helpers ───────────────────────────────────────────────────────────────────

function freshRunner(): TimerRunner
{
    return new TimerRunner();
}

function onePhaseProgram(int $duration = 10, int $reps = 1, int $pause = 0, int $cooldown = 0): TimerProgram
{
    Storage::fake();
    $prog = TimerProgram::create('Single Phase');
    $prog->addPhase(new Phase('Work', $duration, $reps, $pause, $cooldown, '#3b82f6'));
    $prog->save();
    return TimerProgram::load($prog->id);
}

function twoPhaseProgram(): TimerProgram
{
    Storage::fake();
    $prog = TimerProgram::create('Two Phases');
    $prog->addPhase(new Phase('Work', 5, 2, 2, 3, '#3b82f6'));
    $prog->addPhase(new Phase('Rest', 8, 1, 0, 0, '#22c55e'));
    $prog->save();
    return TimerProgram::load($prog->id);
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
    expect(fn () => freshRunner()->start())->toThrow(\RuntimeException::class);
});

test('start with empty program throws RuntimeException', function (): void {
    Storage::fake();
    $prog = TimerProgram::create('Empty'); $prog->save();
    $runner = freshRunner();
    $runner->load(TimerProgram::load($prog->id));
    expect(fn () => $runner->start())->toThrow(\RuntimeException::class);
});

// ── idle → running ────────────────────────────────────────────────────────────

test('start transitions to running state', function (): void {
    $runner = freshRunner();
    $runner->load(onePhaseProgram(10));
    $runner->start();

    expect($runner->cursor()->state)->toBe(StateMachine::running)
        ->and($runner->cursor()->remaining)->toBe(10);
});

// ── running → pause (inter-rep) ───────────────────────────────────────────────

test('tick at rep end with pause configured enters pause state', function (): void {
    $runner = freshRunner();
    $runner->load(onePhaseProgram(duration: 5, reps: 2, pause: 3));
    $runner->start();

    for ($i = 0; $i < 5; $i++) $runner->tick();

    expect($runner->cursor()->state)->toBe(StateMachine::pause)
        ->and($runner->cursor()->remaining)->toBe(3);
});

// ── pause → running (next rep) ────────────────────────────────────────────────

test('ticking through pause advances to next rep', function (): void {
    $runner = freshRunner();
    $runner->load(onePhaseProgram(duration: 5, reps: 2, pause: 3));
    $runner->start();

    for ($i = 0; $i < 5; $i++) $runner->tick();
    for ($i = 0; $i < 3; $i++) $runner->tick();

    expect($runner->cursor()->state)->toBe(StateMachine::running)
        ->and($runner->cursor()->repIndex)->toBe(1);
});

// ── running → cooldown ────────────────────────────────────────────────────────

test('last rep with cooldown enters cooldown state', function (): void {
    $runner = freshRunner();
    $runner->load(onePhaseProgram(duration: 5, reps: 1, cooldown: 4));
    $runner->start();

    for ($i = 0; $i < 5; $i++) $runner->tick();

    expect($runner->cursor()->state)->toBe(StateMachine::cooldown)
        ->and($runner->cursor()->remaining)->toBe(4);
});

// ── cooldown → completed (single phase) ───────────────────────────────────────

test('ticking through cooldown on last phase completes program', function (): void {
    $completed = false;

    $runner = freshRunner();
    $prog   = onePhaseProgram(duration: 3, reps: 1, cooldown: 2);
    $runner->load($prog);
    $runner->start();

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
    $runner->tick();
    $runner->pause();

    expect($runner->cursor()->state)->toBe(StateMachine::paused)
        ->and($runner->cursor()->remaining)->toBe(9);
});

test('resume restores running state with same remaining', function (): void {
    $runner = freshRunner();
    $runner->load(onePhaseProgram(10));
    $runner->start();
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

    for ($i = 0; $i < 5; $i++) $runner->tick(); // into pause state
    $runner->pause();
    $runner->resume();

    expect($runner->cursor()->state)->toBe(StateMachine::pause);
});

// ── 10-phase limit ────────────────────────────────────────────────────────────

test('program rejects 11th phase', function (): void {
    Storage::fake();
    $prog = TimerProgram::create('Overflow');
    for ($i = 0; $i < 10; $i++) {
        $prog->addPhase(new Phase("P{$i}", 5, 1, 0, 0, '#3b82f6'));
    }
    expect(fn () => $prog->addPhase(new Phase('P11', 5, 1, 0, 0, '#3b82f6')))
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
