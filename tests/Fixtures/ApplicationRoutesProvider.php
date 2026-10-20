<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Tests\Fixtures;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * An application that serves its own login page (registered before Sentinel's routes).
 */
final class ApplicationRoutesProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->get('/login', static fn (): string => 'Application login')->name('login');
    }
}
