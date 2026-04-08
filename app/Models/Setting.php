<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\BeepLeadIn;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public $timestamps = false;

    protected $table = 'settings';

    protected $fillable = [
        'default_beep_lead_in',
        'default_end_sound',
        'sound_mode',
        'volume',
        'keep_screen_on',
    ];

    protected $casts = [
        'default_beep_lead_in' => BeepLeadIn::class,
        'keep_screen_on'       => 'boolean',
        'volume'               => 'float',
    ];

    /** Returns the single settings row, creating it with defaults on first run. */
    public static function current(): self
    {
        return self::first() ?? self::create([
            'default_beep_lead_in' => BeepLeadIn::Three->value,
            'default_end_sound'    => 'triple',
            'sound_mode'           => 'beep',
            'volume'               => 0.8,
            'keep_screen_on'       => true,
        ]);
    }
}
