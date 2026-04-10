<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\BeepLeadIn;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NoDiscard;

#[Table(name: 'programs', key: 'id')]
class Program extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'beep_lead_in', 'end_sound', 'last_used_at', 'id'];

    protected $casts = [
        'beep_lead_in' => BeepLeadIn::class,
        'last_used_at' => 'datetime',
    ];

    protected $appends = ['total_duration'];

    protected $attributes = [
        'beep_lead_in' => BeepLeadIn::Three,
    ];

    public function __construct(array $attributes = [])
    {
        $this->attributes['end_sound'] = Setting::current()->default_end_sound;
        parent::__construct($attributes);
    }

    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class)->orderBy('sort_order');
    }

    public function addPhase(array $attributes): Phase
    {
        if ($this->phases()->count() >= 10) {
            throw new \OverflowException('A program can have at most 10 phases.');
        }

        $attributes['sort_order'] ??= $this->phases()->count();

        return $this->phases()->create($attributes);
    }

    /** Also stamps last_used_at alongside updated_at. */
    public function touch($attribute = null): bool
    {
        $this->last_used_at = now();
        return parent::touch($attribute);
    }

    #[NoDiscard]
    public function totalDuration(): int
    {
        $phases = $this->phases->all();

        if (empty($phases)) {
            return 0;
        }

        $total = array_reduce(
            $phases,
            static function (int $carry, Phase $phase): int {
                $repTime = $phase->duration * $phase->repetitions;
                $pauses  = $phase->pause * max(0, $phase->repetitions - 1);
                return $carry + $repTime + $pauses + $phase->cooldown;
            },
            0,
        );

        $lastPhase = $phases[count($phases) - 1] ?? null;
        return $total - ($lastPhase?->cooldown ?? 0);
    }

    public function getTotalDurationAttribute(): int
    {
        return $this->totalDuration();
    }

    public function formattedDuration(): string
    {
        $total = $this->totalDuration();
        return sprintf('%d:%02d', intdiv($total, 60), $total % 60);
    }
}
