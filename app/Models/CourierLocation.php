<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierLocation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['latitude', 'longitude', 'accuracy', 'speed_kmh', 'heading', 'source', 'recorded_at'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float', 'longitude' => 'float', 'accuracy' => 'float',
            'speed_kmh' => 'float', 'heading' => 'float', 'recorded_at' => 'datetime',
        ];
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(DeliveryAssignment::class, 'delivery_assignment_id');
    }
}
