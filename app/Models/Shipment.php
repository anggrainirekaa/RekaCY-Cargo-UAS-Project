<?php

namespace App\Models;

use App\Enums\Severity;
use App\Enums\ShipmentPriority;
use App\Enums\ShipmentStatus;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Only descriptive data is mass-assignable. status, delay fields, delivered_at,
     * shipment_number and created_by are set exclusively by services.
     */
    protected $fillable = [
        'customer_id', 'carrier_id', 'origin_id', 'destination_id', 'cargo_type',
        'weight_kg', 'volume_cbm', 'expected_departure_at', 'expected_arrival_at',
        'estimated_arrival_at', 'notes', 'priority',
    ];

    public const SORTABLE = [
        'shipment_number', 'status', 'expected_departure_at', 'expected_arrival_at', 'created_at', 'delay_hours',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'priority' => ShipmentPriority::class,
            'delay_severity' => Severity::class,
            'is_delayed' => 'boolean',
            'delay_hours' => 'float',
            'weight_kg' => 'float',
            'volume_cbm' => 'float',
            'expected_departure_at' => 'datetime',
            'expected_arrival_at' => 'datetime',
            'estimated_arrival_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    /** Chronological, oldest first. */
    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class)->latest('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ShipmentDocument::class)->latest();
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ShipmentException::class)->latest('detected_at');
    }

    /** Most recent tracking event that carries valid coordinates = current location. */
    public function latestLocatedEvent(): HasOne
    {
        return $this->hasOne(TrackingEvent::class)->ofMany(
            ['occurred_at' => 'max', 'id' => 'max'],
            fn (Builder $q) => $q->whereNotNull('latitude')->whereNotNull('longitude')
        );
    }

    /** Customers only see their own shipments; staff see everything. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isCourier()) {
            return $query->whereHas('assignments', fn (Builder $a) => $a->whereHas('courier', fn (Builder $c) => $c->where('user_id', $user->id)));
        }

        return $user->isCustomer() ? $query->where('shipments.customer_id', $user->id) : $query;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('shipments.status', [
            ShipmentStatus::Delivered->value, ShipmentStatus::Cancelled->value,
        ]);
    }

    /** @param array<string, mixed> $f */
    public function scopeFilter(Builder $query, array $f): Builder
    {
        $like = fn (string $v) => '%'.addcslashes($v, '%_\\').'%';

        $query
            ->when($f['q'] ?? null, fn (Builder $q, string $v) => $q->where(
                fn (Builder $w) => $w->where('shipments.shipment_number', 'like', $like($v))
                    ->orWhere('shipments.cargo_type', 'like', $like($v))
            ))
            ->when($f['status'] ?? null, fn (Builder $q, string $v) => $q->where('shipments.status', $v))
            ->when($f['carrier_id'] ?? null, fn (Builder $q, $v) => $q->where('shipments.carrier_id', $v))
            ->when($f['origin_id'] ?? null, fn (Builder $q, $v) => $q->where('shipments.origin_id', $v))
            ->when($f['destination_id'] ?? null, fn (Builder $q, $v) => $q->where('shipments.destination_id', $v))
            ->when($f['from'] ?? null, fn (Builder $q, string $v) => $q->where('shipments.expected_departure_at', '>=', $v.' 00:00:00'))
            ->when($f['to'] ?? null, fn (Builder $q, string $v) => $q->where('shipments.expected_departure_at', '<=', $v.' 23:59:59'));

        if (($f['delay'] ?? null) === 'delayed') {
            $query->where('shipments.is_delayed', true);
        } elseif (($f['delay'] ?? null) === 'on_time') {
            $query->where('shipments.is_delayed', false);
        }

        $sort = in_array($f['sort'] ?? null, self::SORTABLE, true) ? $f['sort'] : 'created_at';
        $dir = ($f['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy('shipments.'.$sort, $dir)->orderByDesc('shipments.id');
    }
}
