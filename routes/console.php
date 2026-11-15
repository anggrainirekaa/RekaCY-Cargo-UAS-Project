<?php

use Illuminate\Support\Facades\Schedule;

// Re-evaluate delay rules for every active shipment (needs `php artisan schedule:work` or cron).
Schedule::command('rekacy:detect-delays')->everyFifteenMinutes()->withoutOverlapping();
