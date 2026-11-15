<?php

namespace App\Enums;

/**
 * Courier task lifecycle (drives the shipment status through DeliveryService):
 *   ASSIGNED → ACCEPTED → PICKED_UP → IN_TRANSIT → ARRIVED → COMPLETED
 *   FAILED is possible once cargo is picked up; CANCELLED before pickup.
 */
enum AssignmentStatus: string
{
    case Assigned = 'ASSIGNED';
    case Accepted = 'ACCEPTED';
    case PickedUp = 'PICKED_UP';
    case InTransit = 'IN_TRANSIT';
    case Arrived = 'ARRIVED';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Assigned => [self::Accepted, self::Cancelled],
            self::Accepted => [self::PickedUp, self::Cancelled],
            self::PickedUp => [self::InTransit, self::Failed, self::Cancelled],
            self::InTransit => [self::Arrived, self::Failed, self::Cancelled],
            self::Arrived => [self::Completed, self::Failed, self::Cancelled],
            self::Completed, self::Failed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isActive(): bool
    {
        return $this->allowedTransitions() !== [];
    }

    /** GPS points are accepted only while the courier is carrying the cargo. */
    public function tracksGps(): bool
    {
        return in_array($this, [self::PickedUp, self::InTransit, self::Arrived], true);
    }

    public function labelId(): string
    {
        return match ($this) {
            self::Assigned => 'Ditugaskan', self::Accepted => 'Diterima', self::PickedUp => 'Barang diambil',
            self::InTransit => 'Dalam perjalanan', self::Arrived => 'Sampai di tujuan', self::Completed => 'Selesai',
            self::Failed => 'Gagal', self::Cancelled => 'Dibatalkan',
        };
    }

    public function label(): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $this->value)));
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Assigned => 'bg-slate-100 text-slate-700 ring-slate-200',
            self::Accepted => 'bg-indigo-500/10 text-indigo-300 ring-indigo-400/30',
            self::PickedUp => 'bg-sky-500/10 text-sky-300 ring-sky-400/30',
            self::InTransit, self::Arrived => 'bg-cyan-500/10 text-cyan-300 ring-cyan-400/30',
            self::Completed => 'bg-emerald-500/10 text-emerald-300 ring-emerald-400/30',
            self::Failed => 'bg-red-500/10 text-red-300 ring-red-400/30',
            self::Cancelled => 'bg-slate-100 text-slate-700 ring-slate-200',
        };
    }
}
