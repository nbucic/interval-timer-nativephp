<?php

declare(strict_types=1);

use App\Enum\BeepLeadIn;
use App\Models\Program;
use App\Timer\TimerRunner;

// ── Helper ─────────────────────────────────────────────────────────────────────
// Returns an object so mutations from the closure are visible to the caller.
// (PHP array destructuring copies values; stdClass passes by handle.)

function createProgram(string $name, array $phases = [], int $leadIn = 3): Program
{
    $prog = Program::create([
        'name' => $name,
        'beep_lead_in' => BeepLeadIn::from($leadIn),
    ]);

    foreach ($phases as $index => $phase) {
        $prog->phases()->create(array_merge($phase, ['sort_order' => $index]));
    }

    return $prog;
}

function createBeepRunner(array $phases = [], int $leadIn = 3): object
{
    $prog = createProgram('Beep Test', $phases, $leadIn);

    $ctx = new stdClass();
    $ctx->beeps = [];
    $ctx->runner = new TimerRunner();
    $ctx->runner->load($prog->load('phases'));

    $ctx->runner->onBeep(function (string $reason) use ($ctx): void {
        $ctx->beeps[] = $reason;
    });

    $ctx->runner->start();

    // Advance through the 5-second PREPARE countdown before each test begins.
    for ($i = 0; $i < 5; $i++) $ctx->runner->tick();
    $ctx->beeps = []; // discard prepare countdown beeps

    return $ctx;
}

function createPhase(string $name, int $duration, int $reps = 1, int $pause = 0, int $cooldown = 0, string $color = "#3b82f6"): array
{
    return [
        'label' => $name,
        'duration' => $duration,
        'repetitions' => $reps,
        'pause' => $pause,
        'cooldown' => $cooldown,
        'color' => $color,
    ];
}

// ── Prepare beep ──────────────────────────────────────────────────────────────

test('prepare beep fires once per tick during the 5s prepare countdown', function (): void {
    $prog = createProgram('Prepare beep test', [
        createPhase('Work', duration: 10),
    ]);

    $beeps = [];
    $runner = new TimerRunner();
    $runner->load($prog->load('phases'));
    $runner->onBeep(function (string $reason) use (&$beeps): void {
        $beeps[] = $reason;
    });
    $runner->start(); // enters PREPARE (remaining = 5)

    for ($i = 0; $i < 5; $i++) $runner->tick();

    $prepareCount = count(array_filter($beeps, fn($r) => $r === 'prepare'));
    // Ticks bring remaining 5→4→3→2→1→0 (beginFirstRep); beep fires while remaining > 0
    expect($prepareCount)->toBe(4)
        ->and($beeps)->not->toContain('countdown');
});

// ── Lead-in 3s ────────────────────────────────────────────────────────────────

test('beep fires during last 3 seconds of a 10s rep (3s lead-in)',
    /**
     * @throws JsonException
     */
    function (): void {
        $ctx = createBeepRunner([
            createPhase(name: 'Work', duration: 10),
        ],
        );

        // 7 ticks bring remaining from 10 → 3 (the lead-in window)
        for ($i = 0; $i < 7; $i++) $ctx->runner->tick();

        expect($ctx->beeps)->toContain('countdown');
    });

test('beep fires exactly 3 times during last 3 seconds of 10s rep',
    function (): void {
        $ctx = createBeepRunner([
            createPhase(name: 'Work', duration: 10),
        ],
        );

        for ($i = 0; $i < 10; $i++) $ctx->runner->tick();

        $countdownCount = count(array_filter($ctx->beeps, fn($r) => $r === 'countdown'));
        expect($countdownCount)->toBe(3);
    });

// ── Lead-in 5s ────────────────────────────────────────────────────────────────
test('beep fires 5 times during last 5 seconds with 5s lead-in',
    /**
     * @throws JsonException
     */
    function (): void {
        $ctx = createBeepRunner([
            createPhase(name: 'Work', duration: 15),
        ], leadIn: 5,
        );

        for ($i = 0; $i < 15; $i++) $ctx->runner->tick();

        $countdownCount = count(array_filter($ctx->beeps, fn($r) => $r === 'countdown'));
        expect($countdownCount)->toBe(5);
    });

// ── Short segment fallback ────────────────────────────────────────────────────

test('segment shorter than lead-in fires beep from second 1 (2s segment, 3s lead-in)',
    /**
     * @throws JsonException
     */
    function (): void {
        $ctx = createBeepRunner(
            [
                createPhase(name: 'Work', duration: 2),
            ],
        );

        for ($i = 0; $i < 2; $i++) $ctx->runner->tick();

        $countdownCount = count(array_filter($ctx->beeps, fn($r) => $r === 'countdown'));
        expect($countdownCount)->toBeGreaterThanOrEqual(1);
    });

// ── Fires on rep end ──────────────────────────────────────────────────────────

test('rep_end beep fires when rep expires',
    /**
     * @throws JsonException
     */
    function (): void {
        $ctx = createBeepRunner(
            [
                createPhase(name: 'Work', duration: 5),
            ],
        );

        for ($i = 0; $i < 5; $i++) $ctx->runner->tick();

        expect($ctx->beeps)->toContain('rep_end');
    });

// ── Fires on pause end ────────────────────────────────────────────────────────

test('pause_end beep fires when inter-rep pause expires',
    /**
     * @throws JsonException
     */
    function (): void {
        $ctx = createBeepRunner(
            [
                createPhase(name: 'Work', duration: 5, reps: 2, pause: 3),
            ],
        );

        // Rep 1 (5 ticks) then pause (3 ticks)
        for ($i = 0; $i < 8; $i++) $ctx->runner->tick();

        expect($ctx->beeps)->toContain('pause_end');
    });

// ── Fires on cooldown end ─────────────────────────────────────────────────────

test('cooldown_end beep fires when cooldown expires',
    /**
     * @throws JsonException
     */
    function (): void {
        $ctx = createBeepRunner(
            [
                createPhase(name: 'Work', duration: 5, cooldown: 3),
                createPhase(name: 'Rest', duration: 5, cooldown: 3),
            ],
        );

        // Rep (5 ticks) + cooldown (3 ticks)
        for ($i = 0; $i < 8; $i++) $ctx->runner->tick();

        expect($ctx->beeps)->toContain('cooldown_end');
    });

// ── rep_end does NOT fire during pause or cooldown segments ──────────────────

test('rep_end does not fire when pause segment expires',
    /**
     * @throws JsonException
     */
    function (): void {
        $ctx = createBeepRunner(
            [
                createPhase(name: 'Work', duration: 5, reps: 2, pause: 3),
            ],
        );

        // Only tick through the pause (ticks 6–8)
        for ($i = 0; $i < 8; $i++) $ctx->runner->tick();

        // rep_end fires once (end of rep 1). pause_end fires once (end of pause).
        // rep_end must NOT fire a second time at the end of the pause.
        $repEnds = count(array_filter($ctx->beeps, fn($r) => $r === 'rep_end'));
        $pauseEnds = count(array_filter($ctx->beeps, fn($r) => $r === 'pause_end'));

        expect($repEnds)->toBe(1)
            ->and($pauseEnds)->toBe(1);
    });

// ── Pause beep ────────────────────────────────────────────────────────────────

test('onPauseBeep fires exactly once when user pauses',
    /**
     * @throws JsonException
     */
    function (): void {
        $prog = Program::query()->create(['name' => 'Pause beep test']);
        $prog->phases()->create(['label' => 'Work', 'duration' => 20, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6', 'sort_order' => 0]);

        $runner = new TimerRunner();
        $runner->load($prog->load('phases'));

        $pauseBeepCount = 0;
        $runner->onPauseBeep(function () use (&$pauseBeepCount): void {
            $pauseBeepCount++;
        });

        $runner->start();
        for ($i = 0; $i < 5; $i++) $runner->tick(); // skip prepare
        $runner->tick();
        $runner->pause();

        expect($pauseBeepCount)->toBe(1);
    });

test('onPauseBeep does not fire on resume',
    /**
     * @throws JsonException
     */
    function (): void {
        $prog = Program::query()->create(['name' => 'Resume test']);
        $prog->phases()->create(['label' => 'Work', 'duration' => 20, 'repetitions' => 1, 'pause' => 0, 'cooldown' => 0, 'color' => '#3b82f6', 'sort_order' => 0]);

        $runner = new TimerRunner();
        $runner->load($prog->load('phases'));

        $pauseBeepCount = 0;
        $runner->onPauseBeep(function () use (&$pauseBeepCount): void {
            $pauseBeepCount++;
        });

        $runner->start();
        for ($i = 0; $i < 5; $i++) $runner->tick(); // skip prepare
        $runner->tick();
        $runner->pause();
        $runner->resume();  // must not fire another pause beep

        expect($pauseBeepCount)->toBe(1);
    });
