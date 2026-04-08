<?php

declare(strict_types=1);

namespace App\Timer;

use Illuminate\Support\Facades\Storage;
use JsonException;

/**
 * Reads and writes the run-history log at storage/app/history.json.
 *
 * The file holds an array of HistoryEntry objects (newest first), capped at
 * MAX_ENTRIES. All persistence goes through Laravel Storage, so NativePHP
 * manages the on-device path automatically.
 */
class HistoryLog
{
    private const string PATH = 'history.json';
    private const int MAX_ENTRIES = 20;

    /**
     * Prepend a new entry and trim to MAX_ENTRIES.
     */
    public static function append(HistoryEntry $entry): void
    {
        self::all()
            |> (fn($arr) => [$entry, ...$arr])
            |> (fn($arr) => array_slice($arr, 0, self::MAX_ENTRIES))
            |> (fn($arr) => array_map(static fn(HistoryEntry $e) => $e->toArray(), $arr))
            |> (fn($x) => json_encode($x, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))
            |> (fn($x) => Storage::put(self::PATH, $x));

    }

    /**
     * Return all history entries, newest first.
     *
     * @return HistoryEntry[]
     */
    public static function all(): array
    {
        if (!Storage::exists(self::PATH)) {
            return [];
        }

        try {
            return Storage::get(self::PATH)
                    |> (fn($x) => json_decode($x, true, 512, JSON_THROW_ON_ERROR))
                    |> (fn($x) => array_map(static fn(array $row) => HistoryEntry::fromArray($row),
                        $x));
        } catch (JsonException) {
            return [];
        }


    }

    public static function clear(): void
    {
        Storage::delete(self::PATH);
    }
}
