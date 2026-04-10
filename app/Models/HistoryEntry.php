<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoryEntry extends Model
{
    public $timestamps = false;

    protected $table = 'history';

    protected $fillable = [
        'program_id',
        'program_name',
        'completed_at',
        'total_duration',
    ];

    protected $casts = [
        'completed_at'   => 'datetime',
        'total_duration' => 'integer',
    ];
}
