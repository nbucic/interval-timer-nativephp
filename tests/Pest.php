<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pest Bootstrap
|--------------------------------------------------------------------------
|
| All tests under Unit/Timer/ use the full Laravel TestCase so they get a
| real app container with Storage::fake(), Event::fake(), etc.
| RefreshDatabase resets SQLite between each test so Eloquent-backed tests
| don't bleed state into one another.
|
*/

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class)->in('Unit/Timer');
