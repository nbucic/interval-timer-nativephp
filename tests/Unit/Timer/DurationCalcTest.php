<?php

declare(strict_types=1);

use App\Timer\Phase;
use App\Timer\TimerProgram;
use Illuminate\Support\Facades\Storage;

// Helper: build a program with given phases without touching Storage
function makeProgram(array $phaseDefs): TimerProgram
{
    Storage::fake();

    $prog = TimerProgram::create('Test');
    foreach ($phaseDefs as $def) {
        $prog->addPhase(new Phase(...$def));
    }
    $prog->save();

    return TimerProgram::load($prog->id);
}

// ── Formula: (duration×reps) + (pause×(reps-1)) + cooldown ──────────────────

test('single phase no pause no cooldown', function (): void {
    $prog = makeProgram([
        ['Work', 30, 1, 0, 0, '#3b82f6'],
    ]);
    expect($prog->totalDuration())->toBe(30);
});

test('single phase multiple reps no pause', function (): void {
    // 20s × 3 reps = 60s
    $prog = makeProgram([
        ['label' => 'Sprint', 'duration' => 20, 'repetitions' => 3, 'pause' => 0, 'cooldown' => 0, 'color' => '#ef4444'],
    ]);
    expect($prog->totalDuration())->toBe(60);
});

test('pause accumulates between reps only', function (): void {
    // 10s × 3 reps + 5s × 2 pauses = 30 + 10 = 40
    $prog = makeProgram([
        ['label' => 'Work', 'duration' => 10, 'repetitions' => 3, 'pause' => 5, 'cooldown' => 0, 'color' => '#3b82f6'],
    ]);
    expect($prog->totalDuration())->toBe(40);
});

test('cooldown is added once after final rep', function (): void {
    // 10s × 2 + 5s pause × 1 + 8s cooldown = 20 + 5 + 8 = 33
    $prog = makeProgram([
        ['label' => 'Work', 'duration' => 10, 'repetitions' => 2, 'pause' => 5, 'cooldown' => 8, 'color' => '#3b82f6'],
    ]);
    expect($prog->totalDuration())->toBe(33);
});

test('multiple phases summed correctly', function (): void {
    // Phase 1: 30×1 + 0 + 0 = 30
    // Phase 2: 10×3 + 5×2 + 0 = 30 + 10 = 40
    // Phase 3: 20×1 + 0 + 10 = 30
    // Total = 100
    $prog = makeProgram([
        ['label' => 'Warmup', 'duration' => 30, 'repetitions' => 1, 'pause' => 0,  'cooldown' => 0,  'color' => '#22c55e'],
        ['label' => 'Work',   'duration' => 10, 'repetitions' => 3, 'pause' => 5,  'cooldown' => 0,  'color' => '#3b82f6'],
        ['label' => 'Cool',   'duration' => 20, 'repetitions' => 1, 'pause' => 0,  'cooldown' => 10, 'color' => '#6b7280'],
    ]);
    expect($prog->totalDuration())->toBe(100);
});

test('zero-duration program returns zero', function (): void {
    Storage::fake();
    $prog = TimerProgram::create('Empty');
    $prog->save();
    $loaded = TimerProgram::load($prog->id);
    expect($loaded->totalDuration())->toBe(0);
});

// ── formattedDuration ─────────────────────────────────────────────────────────

test('formatted duration formats minutes and seconds', function (): void {
    $prog = makeProgram([
        ['label' => 'Work', 'duration' => 90, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6'],
    ]);
    // 90s = 1:30
    expect($prog->formattedDuration())->toBe('1:30');
});

test('formatted duration pads seconds below 10', function (): void {
    $prog = makeProgram([
        ['label' => 'Work', 'duration' => 65, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6'],
    ]);
    expect($prog->formattedDuration())->toBe('1:05');
});

test('formatted duration shows zero minutes when under 60 seconds', function (): void {
    $prog = makeProgram([
        ['label' => 'Work', 'duration' => 45, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6'],
    ]);
    expect($prog->formattedDuration())->toBe('0:45');
});
