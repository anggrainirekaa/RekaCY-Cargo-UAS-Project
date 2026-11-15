<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $tz = config('rekacy.timezone', 'UTC');
        config(['app.timezone' => $tz]);
        date_default_timezone_set($tz);

        // GPS endpoint: a courier device normally reports every ~10 s; 40/min leaves room for queue flushes.
        RateLimiter::for('gps', fn (Request $r) => Limit::perMinute(40)->by($r->user()?->id ?: $r->ip()));

        Gate::define('operate', fn (User $u) => $u->isStaff());
        Gate::define('manage-master-data', fn (User $u) => $u->isAdmin());
        Gate::define('manage-users', fn (User $u) => $u->isAdmin());
        Gate::define('view-audit-log', fn (User $u) => $u->isAdmin());
    }
}
