<?php

namespace App\Enums;

enum ShipmentPriority: string
{
    case Normal = 'NORMAL';
    case High = 'HIGH';
    case Urgent = 'URGENT';

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Normal => 'bg-slate-100 text-slate-700 ring-slate-200',
            self::High => 'bg-amber-500/10 text-amber-300 ring-amber-400/30',
            self::Urgent => 'bg-red-500/10 text-red-300 ring-red-400/30',
        };
    }
}
