# Interval Timer

Native Android interval timer — **Laravel 13 · PHP 8.5 · NativePHP Mobile v3.2**

Fully offline, JSON storage, up to 10 phases × 50 reps with per-phase pause, cooldown, and configurable beep/TTS countdowns.

| | |
|---|---|
| **Framework** | Laravel 13 |
| **PHP** | 8.5 (embedded) |
| **NativePHP** | Mobile v3.2 |
| **Storage** | JSON files (no SQLite) |
| **Platform** | Android only |
| **Min SDK** | Android 8 (API 26) |

---

## About

A demo app showcasing NativePHP Mobile — a native, fully offline interval timer running on real Laravel 13 with PHP 8.5, no web server. Users build multi-phase repeatable timers with per-phase pauses and cooldowns, configurable audio countdowns (beep or Android TTS voice), and a live total duration display.

**Core terminology:**

- **Program** — named collection of up to 10 phases + per-program settings
- **Phase** — a named timed block with repetitions, a pause between reps, and a cooldown after the final rep
- **Repetition** — one execution of the phase duration (max 50 per phase)
- **Pause** — dead-time between repetitions within the same phase
- **Cooldown** — dead-time after the last rep of a phase, before the next phase begins. The last phase's cooldown is editable but never executed or counted.
- **Beep Lead-in** — per-program (3s or 5s), seeded from the global default on program creation
- **Total Duration** — `(duration × reps) + (pause × (reps−1)) + cooldown` for every phase except the last (last phase cooldown excluded)

**Phase execution sequence (3 phases, 2 reps each):**

```
Phase 1: [REP 1] → [PAUSE] → [REP 2] → [COOLDOWN]
Phase 2: [REP 1] → [PAUSE] → [REP 2] → [COOLDOWN]
Phase 3: [REP 1] → [PAUSE] → [REP 2] → [COOLDOWN — skipped]
END
```

A program always ends on the final rep of the final phase.

### Tech Stack

| Layer | Technology | Notes |
|---|---|---|
| Runtime | NativePHP Mobile v3.2 | PHP 8.5 embedded in Kotlin shell, persistent runtime ~5–30ms/req |
| Framework | Laravel 13 | PHP attributes, typed config, zero breaking changes from L12 |
| Language | PHP 8.5 | Pipe operator `\|>`, `clone with`, `readonly class` |
| Storage | Laravel Storage (JSON) | One JSON file per program + `settings.json` |
| UI / Web | Livewire v3 + Alpine.js | `wire:poll.1000ms` for timer state, Alpine for 100ms visual tick |
| UI / Native | EDGE Components | Native Top Bar + Bottom Navigation outside the WebView |
| Styling | Tailwind CSS v4 | Dark theme, touch-optimised |
| Audio | Web Audio API + Android TTS | Beep (bundled mp3) or voice (feminine TTS) |
| Background | Android Foreground Service | Timer runs when backgrounded, auto-pause on incoming calls |
| Testing | Pest PHP | Fake clock for TimerRunner, temp storage for JSON I/O |

### Architecture

**TimerRunner state machine:**

```
idle
  → running       // executing a repetition
    → paused      // user hit pause (or phone call)
    → pause       // dead-time between reps
    → cooldown    // dead-time after final rep, breathing animation
  → completed     // all phases done, end sound fires
```

**Key files:**

```
app/Timer/TimerRunner.php         ← singleton, state machine, beep scheduling
app/Timer/TimerCursor.php         ← PHP 8.5 readonly class, clone with
app/Timer/TimerProgram.php        ← JSON ↔ PHP, pipe operator load/save
app/Livewire/Library.php          ← sorted by last_used_at
app/Livewire/ProgramEditor.php    ← phase CRUD, 10-phase / 50-rep caps
app/Livewire/ActiveTimer.php      ← wire:poll + Alpine tick + beep events
app/Livewire/Settings.php
app/Events/PhaseChanged.php       ← #[Broadcast] attribute (L13)
app/Events/ProgramCompleted.php
resources/views/layouts/app.blade.php   ← EDGE Bottom Nav + Top Bar
resources/audio/{beep,finish-triple,finish-chime}.mp3
storage/app/programs/*.json
storage/app/settings.json
tests/Feature/{TimerRunnerTest,BeepLogicTest,DurationCalcTest,...}.php
```

---

## Initial Setup

**Requirements:** PHP 8.5, Composer, Android SDK (for device/emulator builds)

```bash
# 1. Clone and install dependencies
git clone <repo-url> interval-timer
cd interval-timer
composer install

# 2. Install NativePHP Mobile
php artisan native:install

# 3. Copy environment file
cp .env.example .env
php artisan key:generate
```

> **Note:** `composer.json` specifies `"php": "^8.5"` — NativePHP auto-detects and matches the bundled runtime.
>
> No database setup required. This project uses JSON file storage only — there is no SQLite, no migrations.

### config/nativephp.php

Ensure the following values are set:

```php
'bundle_id'   => 'com.yourname.intervaltimer',
'min_sdk'     => 26,   // Android 8
'compile_sdk' => 35,
'target_sdk'  => 35,
```

---

## Build and Run

### Android Emulator

```bash
php artisan native:run --os=android
```

### Physical Device

Connect an Android device with USB debugging enabled, then:

```bash
php artisan native:run --os=android
```

NativePHP will detect the connected device automatically.

### Development (browser, for UI iteration)

```bash
php artisan serve
```

The spec file `interval-timer-spec-v4.html` includes a live JS playground that mirrors the timer logic. Open it directly in a browser to validate phase/beep behaviour without a device.

---

## Running the Test Suite

Tests use [Pest PHP](https://pestphp.com). Run all tests in parallel:

```bash
php artisan test --parallel
```

### Test Suites

| Suite | Coverage | Priority |
|---|---|---|
| `TimerRunnerTest` | State transitions `idle→running→pause→cooldown→completed`, user pause preserves cursor, `total_remaining` decrements, 10-phase limit | CRITICAL |
| `BeepLogicTest` | Lead-in 3s/5s, short segment fallback (fires from second 1), fires on rep/pause/cooldown end, no double-fire | CRITICAL |
| `DurationCalcTest` | Single phase, multi-rep with pause, cooldown on last phase excluded, all 10 phases, `formattedDuration()` mm:ss and h:mm:ss | CRITICAL |
| `CursorTest` | Advances through reps, skips pause/cooldown when 0, last phase last rep → completed, `clone with` immutability | CRITICAL |
| `TimerProgramTest` | JSON save/load, `last_used_at` updated on run, `totalDuration()` formula, 50-rep cap, pipe operator load chain | HIGH |
| `SettingsTest` | Defaults when `settings.json` missing, new program seeded from global defaults, per-program `beep_lead_in` override, volume clamped 0–1 | HIGH |
| `EndSoundTest` | `ProgramCompleted` fires exactly once, triple vs chime selection, not fired on mid-program phase change | HIGH |
| `LifecycleTest` | Pause state preserved on phone call, resume restores exact cursor position, kill discards state with no history entry | HIGH |

### Conventions

- Pest PHP throughout — `describe()` blocks per class
- `TimerRunner` uses a **fake clock** — inject tick count, no real `sleep()`
- `TimerProgram` writes to a **temp directory**, never real storage
- Every PR must include tests before merge

---

## License

MIT
