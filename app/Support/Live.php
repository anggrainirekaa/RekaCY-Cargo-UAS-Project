<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Broadcast helper. A WebSocket outage (Reverb not running) must never break an HTTP request,
 * so failures are logged and swallowed; clients fall back to polling.
 */
final class Live
{
    public static function dispatch(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            Log::warning('Live broadcast failed: '.$e->getMessage());
        }
    }
}
