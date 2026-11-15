<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Courier extends Model
{
    public const LIVE_SECONDS = 90; // no GPS point for this long = signal lost

    protected $fillable = ['user_id', 'phone', 'vehicle_type', 'vehicle_plate', 'availability', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_tracking' => 'boolean', 'last_seen_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class)->latest('id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(CourierLocation::class);
    }

    /** ACTIVE = GPS flowing, STALE = tracking on but silent, OFF = not tracking. */
    public function gpsState(): string
    {
        if (! $this->is_tracking) {
            return 'OFF';
        }

        return $this->last_seen_at && $this->last_seen_at->gte(now()->subSeconds(self::LIVE_SECONDS)) ? 'ACTIVE' : 'STALE';
    }
}
