<?php

namespace App\Enums;

enum ExceptionStatus: string
{
    case Open = 'OPEN';
    case Investigating = 'INVESTIGATING';
    case Resolved = 'RESOLVED';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Investigating, self::Resolved],
            self::Investigating => [self::Resolved],
            self::Resolved => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-rose-500/10 text-rose-300 ring-rose-400/30',
            self::Investigating => 'bg-amber-500/10 text-amber-300 ring-amber-400/30',
            self::Resolved => 'bg-emerald-500/10 text-emerald-300 ring-emerald-400/30',
        };
    }
}
