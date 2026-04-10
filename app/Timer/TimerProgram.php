<?php

declare(strict_types=1);

namespace App\Timer;

use App\Enum\BeepLeadIn;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use NoDiscard;
use OverflowException;
use RuntimeException;

/**
 * Plain PHP model representing a saved interval-timer program.
 *
 * Persistence: JSON files at storage/app/programs/{id}.json
 *
 * PHP 8.5 FEATURES:
 *   • Pipe operator |> — load() chains Storage::get → json_decode → hydrate.
 *                          Shown in comments; falls back to explicit nesting for
 *                          PHP < 8.5 host tooling.
 *   • #[\NoDiscard] — on totalDuration(); callers must use the return value.
 *   • array_first_value() — used in TimerRunner to grab the first phase.
 */
class TimerProgram
{
    public readonly string $id;
    public string $name;
    public string $createdAt;
    public ?string $lastUsedAt;
    public BeepLeadIn $beepLeadIn;    // 3 | 5
    public string $endSound;      // 'triple' | 'chime'
    /** @var Phase[] */
    public array $phases;        // max 10

    private function __construct(
        string     $id,
        string     $name,
        string     $createdAt,
        ?string    $lastUsedAt,
        BeepLeadIn $beepLeadIn,
        string     $endSound,
        array      $phases,
    )
    {
        $this->id = $id;
        $this->name = $name;
        $this->createdAt = $createdAt;
        $this->lastUsedAt = $lastUsedAt;
        $this->beepLeadIn = $beepLeadIn;
        $this->endSound = $endSound;
        $this->phases = $phases;
    }

    /** Return all saved programs, newest first. */
    public static function all(): array
    {
        return collect(Storage::files('programs'))
            ->filter(fn(string $p) => str_ends_with($p, '.json'))
            ->map(fn(string $p) => self::load(basename($p, '.json')))
            ->sortByDesc(fn(self $prog) => $prog->createdAt)
            ->values()
            ->all();
    }

    /** Create a brand-new program seeded with global defaults. */
    public static function create(string $name): self
    {
        $settings = AppSettings::load();

        return new self(
            id: (string)Str::uuid(),
            name: $name,
            createdAt: now()->toISOString(),
            lastUsedAt: null,
            beepLeadIn: $settings->defaultBeepLeadIn,
            endSound: $settings->defaultEndSound,
            phases: [],
        );
    }

    /**
     * Load from a JSON file.
     *
     * PHP 8.5 pipe-operator version (requires NativePHP's PHP 8.5 runtime):
     *
     *   return Storage::get("programs/{$id}.json")
     *       |> json_decode($$, true, 512, JSON_THROW_ON_ERROR)
     *       |> self::hydrate($$);
     *
     * Equivalent without the pipe operator (PHP 8.4-safe):
     * @throws JsonException
     */
    public static function load(string $id): self
    {
        $path = "programs/$id.json";

        if (!Storage::exists($path)) {
            throw new RuntimeException("Program not found: $id");
        }

        $json = Storage::get($path);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return self::hydrate($data);
    }

    private static function hydrate(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            createdAt: $data['created_at'],
            lastUsedAt: $data['last_used_at'] ?? null,
            beepLeadIn: BeepLeadIn::from($data['beep_lead_in'] ?? 3),
            endSound: $data['end_sound'] ?? 'triple',
            phases: array_map(
                static fn(array $p) => Phase::fromArray($p),
                $data['phases'] ?? [],
            ),
        );
    }

    /** Add a phase; max 10 per program. */
    public function addPhase(Phase $phase): void
    {
        if (count($this->phases) >= 10) {
            throw new OverflowException('A program may have at most 10 phases.');
        }
        $this->phases[] = $phase;
    }

    public function delete(): void
    {
        Storage::delete("programs/$this->id.json");
    }

    /** Human-readable total duration, e.g. "12:34". */
    public function formattedDuration(): string
    {
        $total = $this->totalDuration();
        $minutes = intdiv($total, 60);
        $seconds = $total % 60;
        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function touch(): void
    {
        $this->lastUsedAt = now()->toISOString();
        $this->save();
    }

    /** Save the program to its JSON file.
     */
    public function save(): void
    {
        try {
            Storage::put(
                "programs/$this->id.json",
                json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            );
        } catch (JsonException $e) {
            Storage::put(
                "programs/$this->id.json",
                json_encode([], JSON_PRETTY_PRINT),
            );
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->createdAt,
            'last_used_at' => $this->lastUsedAt,
            'beep_lead_in' => $this->beepLeadIn,
            'end_sound' => $this->endSound,
            'total_duration' => $this->totalDuration(),
            'phases' => array_map(
                static fn(Phase $p) => $p->toArray(),
                $this->phases,
            ),
        ];
    }

    /**
     * Computed total duration in seconds.
     *
     * Formula per phase:
     *   (duration × reps) + (pause × (reps − 1)) + cooldown
     *
     */
    #[NoDiscard]
    public function totalDuration(): int
    {
        $totalDuration = array_reduce(
            $this->phases,
            static function (int $carry, Phase $phase): int {
                $repTime = $phase->duration * $phase->repetitions;
                $pauses = $phase->pause * max(0, $phase->repetitions - 1);
                return $carry + $repTime + $pauses + $phase->cooldown;
            },
            0,
        );

        return $totalDuration - array_last($this->phases)->cooldown;
    }
}
