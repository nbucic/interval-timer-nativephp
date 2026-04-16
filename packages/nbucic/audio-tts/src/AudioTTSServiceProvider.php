<?php

namespace Nbucic\AudioTts;

use Illuminate\Support\ServiceProvider;
use Nbucic\AudioTts\Commands\CopyAssetsCommand;

class AudioTTSServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AudioTTS::class, function () {
            return new AudioTTS();
        });
    }

    public function boot(): void
    {
        // Register plugin hook commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CopyAssetsCommand::class,
            ]);
        }
    }
}