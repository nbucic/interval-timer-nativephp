<?php

namespace Nbucic\AudioTts\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed execute(array $options = [])
 * @method static object|null getStatus()
 *
 * @see \Nbucic\AudioTts\AudioTTS
 */
class AudioTTS extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Nbucic\AudioTts\AudioTTS::class;
    }
}