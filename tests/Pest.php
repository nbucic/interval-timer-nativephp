<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pest Bootstrap
|--------------------------------------------------------------------------
|
| All tests under Unit/Timer/ use the full Laravel TestCase so they get a
| real app container with Storage::fake(), Event::fake(), etc.
|
*/

uses(Tests\TestCase::class)->in('Unit/Timer');
