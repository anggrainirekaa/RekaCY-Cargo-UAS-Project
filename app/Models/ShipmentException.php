<?php

namespace App\Models;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\Severity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentException extends Model
{
    protected $table = 'shipment_exceptions';

    protected $fillable = [
        'type', 'severity', 'status', 'description', 'resolution_note',
        'detected_at', 'resolved_at', 'resolved_by', 'created_by', 'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExceptionType::class,
            'severity' => Severity::class,
            'status' => ExceptionStatus::class,
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ExceptionNote::class)->oldest();
    }

    public function isOpen(): bool
    {
        return $this->status !== ExceptionStatus::Resolved;
    }
}
