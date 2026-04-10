<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Phase extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'program_id',
        'sort_order',
        'label',
        'duration',
        'repetitions',
        'pause',
        'cooldown',
        'color',
    ];

    protected $casts = [
        'sort_order'  => 'integer',
        'duration'    => 'integer',
        'repetitions' => 'integer',
        'pause'       => 'integer',
        'cooldown'    => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(static function (Phase $phase): void {
            if ($phase->repetitions < 1 || $phase->repetitions > 50) {
                throw new \RangeException('Repetitions must be between 1 and 50.');
            }
        });
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
