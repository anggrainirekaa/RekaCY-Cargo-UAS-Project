<?php

namespace App\Enums;

/**
 * Shipment lifecycle. The transition table below is the single source of truth;
 * ShipmentService::transition() refuses anything that is not listed here.
 */
enum ShipmentStatus: string
{
    case Created = 'CREATED';
    case Booked = 'BOOKED';
    case PickedUp = 'PICKED_UP';
    case InTransit = 'IN_TRANSIT';
    case Delivered = 'DELIVERED';
    case Cancelled = 'CANCELLED';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Created => [self::Booked, self::Cancelled],
            self::Booked => [self::PickedUp, self::Cancelled],
            self::PickedUp => [self::InTransit],
            self::InTransit => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function label(): string
    {
        return match ($this) {
            self::PickedUp => 'Picked up',
            self::InTransit => 'In transit',
            default => ucfirst(strtolower($this->value)),
        };
    }

    /** @return list<self> */
    public static function lifecycle(): array
    {
        return [self::Created, self::Booked, self::PickedUp, self::InTransit, self::Delivered];
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Created => 'bg-slate-100 text-slate-700 ring-slate-200',
            self::Booked => 'bg-indigo-500/10 text-indigo-300 ring-indigo-400/30',
            self::PickedUp => 'bg-sky-500/10 text-sky-300 ring-sky-400/30',
            self::InTransit => 'bg-cyan-500/10 text-cyan-300 ring-cyan-400/30',
            self::Delivered => 'bg-emerald-500/10 text-emerald-300 ring-emerald-400/30',
            self::Cancelled => 'bg-rose-500/10 text-rose-300 ring-rose-400/30',
        };
    }

    public function mapColor(): string
    {
        return match ($this) {
            self::Created, self::Booked => '#6366f1',
            self::PickedUp => '#0ea5e9',
            self::InTransit => '#14b8a6',
            self::Delivered => '#10b981',
            self::Cancelled => '#f43f5e',
        };
    }
}
