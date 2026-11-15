<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'OPEN';
    case InProgress = 'IN_PROGRESS';
    case Resolved = 'RESOLVED';
    case Closed = 'CLOSED';

    public function label(): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $this->value)));
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-red-500/10 text-red-300 ring-red-400/30',
            self::InProgress => 'bg-amber-500/10 text-amber-300 ring-amber-400/30',
            self::Resolved => 'bg-emerald-500/10 text-emerald-300 ring-emerald-400/30',
            self::Closed => 'bg-slate-100 text-slate-700 ring-slate-200',
        };
    }
}
