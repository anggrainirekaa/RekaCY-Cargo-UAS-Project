<?php

namespace App\Enums;

enum Severity: string
{
    case Low = 'LOW';
    case Medium = 'MEDIUM';
    case High = 'HIGH';

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Low => 'bg-amber-500/10 text-amber-300 ring-amber-400/30',
            self::Medium => 'bg-orange-500/10 text-orange-300 ring-orange-400/30',
            self::High => 'bg-rose-500/10 text-rose-300 ring-rose-400/30',
        };
    }
}
