<?php

namespace App\Providers;

use App\Models\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // If your stub already has other boot logic (e.g. rate limiting), keep it.

        // Allow {file} to be resolved by either ID or slug
        Route::bind('file', function ($value) {
            return File::where('id', $value)
                ->orWhere('slug', $value)
                ->firstOrFail();
        });

        // ... existing routes() registration stays as-is
    }
}
