<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryAssignment extends Model
{
    protected $fillable = ['notes'];

    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'assigned_at' => 'datetime', 'accepted_at' => 'datetime', 'picked_up_at' => 'datetime',
            'started_at' => 'datetime', 'arrived_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(CourierLocation::class)->orderBy('recorded_at');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNotIn('status', ['COMPLETED', 'FAILED', 'CANCELLED']);
    }
}
