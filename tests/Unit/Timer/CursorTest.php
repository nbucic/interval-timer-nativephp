<?php

declare(strict_types=1);

use App\Enum\StateMachine;
use App\Timer\TimerCursor;

// ── Construction & idle ───────────────────────────────────────────────────────

test('idle cursor has zero values and idle state', function (): void {
    $c = TimerCursor::idle();

    expect($c->phaseIndex)->toBe(0)
        ->and($c->repIndex)->toBe(0)
        ->and($c->state)->toBe(StateMachine::idle)
        ->and($c->remaining)->toBe(0)
        ->and($c->totalRemaining)->toBe(0);
});

test('idle predicate returns true for idle cursor', function (): void {
    expect(TimerCursor::idle()->isIdle())->toBeTrue();
});

// ── Immutability (clone-with semantics) ───────────────────────────────────────

test('tick returns new cursor and leaves original unchanged', function (): void {
    $original = new TimerCursor(0, 0, StateMachine::running, 10, 60);
    $ticked   = $original->tick();

    expect($ticked)->not->toBe($original)
        ->and($ticked->remaining)->toBe(9)
        ->and($ticked->totalRemaining)->toBe(59)
        ->and($original->remaining)->toBe(10);
});

test('tick clamps remaining at zero', function (): void {
    $c = new TimerCursor(0, 0, StateMachine::running, 0, 5);
    expect($c->tick()->remaining)->toBe(0);
});

test('tick clamps totalRemaining at zero', function (): void {
    $c = new TimerCursor(0, 0, StateMachine::running, 5, 0);
    expect($c->tick()->totalRemaining)->toBe(0);
});

// ── State transitions ─────────────────────────────────────────────────────────

test('enterPause sets state to pause and overrides remaining', function (): void {
    $c = new TimerCursor(0, 2, StateMachine::running, 30, 120);
    $p = $c->enterPause(15, 105);

    expect($p->state)->toBe(StateMachine::pause)
        ->and($p->remaining)->toBe(15)
        ->and($p->totalRemaining)->toBe(105)
        ->and($p->phaseIndex)->toBe(0)
        ->and($p->repIndex)->toBe(2);
});

test('enterCooldown sets state to cooldown', function (): void {
    $c  = new TimerCursor(1, 4, StateMachine::running, 0, 20);
    $cd = $c->enterCooldown(10, 10);

    expect($cd->state)->toBe(StateMachine::cooldown)
        ->and($cd->remaining)->toBe(10)
        ->and($cd->phaseIndex)->toBe(1);
});

test('nextRep increments repIndex and resets remaining', function (): void {
    $c = new TimerCursor(0, 0, StateMachine::running, 0, 90);
    $n = $c->nextRep(30, 90);

    expect($n->repIndex)->toBe(1)
        ->and($n->remaining)->toBe(30)
        ->and($n->state)->toBe(StateMachine::running)
        ->and($n->phaseIndex)->toBe(0);
});

test('nextPhase resets repIndex and advances phaseIndex', function (): void {
    $c = new TimerCursor(0, 3, StateMachine::cooldown, 0, 80);
    $n = $c->nextPhase(1, 45, 80);

    expect($n->phaseIndex)->toBe(1)
        ->and($n->repIndex)->toBe(0)
        ->and($n->state)->toBe(StateMachine::running)
        ->and($n->remaining)->toBe(45);
});

test('pause sets state to paused and preserves remaining', function (): void {
    $c = new TimerCursor(0, 0, StateMachine::running, 12, 50);
    $p = $c->pause();

    expect($p->state)->toBe(StateMachine::paused)
        ->and($p->remaining)->toBe(12)
        ->and($p->totalRemaining)->toBe(50);
});

test('resumeAs restores given state', function (): void {
    $c = new TimerCursor(0, 0, StateMachine::paused, 8, 30);
    $r = $c->resumeAs(StateMachine::running);

    expect($r->state)->toBe(StateMachine::running)
        ->and($r->remaining)->toBe(8);
});

test('complete sets state to completed and zeroes remaining', function (): void {
    $c = new TimerCursor(2, 4, StateMachine::cooldown, 5, 5);
    $d = $c->complete();

    expect($d->state)->toBe(StateMachine::completed)
        ->and($d->remaining)->toBe(0)
        ->and($d->totalRemaining)->toBe(0);
});

// ── Predicates ────────────────────────────────────────────────────────────────

test('isActive returns true for running, pause, cooldown', function (): void {
    foreach ([StateMachine::running, StateMachine::pause, StateMachine::cooldown] as $state) {
        $c = new TimerCursor(0, 0, $state, 5, 5);
        expect($c->isActive())->toBeTrue("expected isActive for state={$state->value}");
    }
});

test('isActive returns false for paused, idle, completed', function (): void {
    foreach ([StateMachine::paused, StateMachine::idle, StateMachine::completed] as $state) {
        $c = new TimerCursor(0, 0, $state, 5, 5);
        expect($c->isActive())->toBeFalse("expected !isActive for state={$state->value}");
    }
});
