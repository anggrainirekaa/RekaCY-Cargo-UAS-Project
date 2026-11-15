<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ReportService
{
    /** @param array<string, mixed> $f */
    public function query(User $user, array $f): Builder
    {
        return Shipment::query()->visibleTo($user)
            ->with(['customer:id,name', 'carrier:id,name', 'origin:id,city,country', 'destination:id,city,country'])
            ->withCount(['exceptions'])
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('shipments.status', $v))
            ->when($f['carrier_id'] ?? null, fn ($q, $v) => $q->where('shipments.carrier_id', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('shipments.created_at', '>=', $v.' 00:00:00'))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->where('shipments.created_at', '<=', $v.' 23:59:59'))
            ->orderBy('shipments.created_at', 'desc');
    }

    /** @return array<string, mixed> */
    public function summary(Builder $q): array
    {
        $all = (clone $q)->get();
        $delivered = $all->where('status.value', 'DELIVERED');

        return [
            'total' => $all->count(),
            'delivered' => $delivered->count(),
            'delayed' => $all->where('is_delayed', true)->count(),
            'exceptions' => (int) $all->sum('exceptions_count'),
            'on_time_rate' => $delivered->count() ? round($delivered->filter(fn ($s) => $s->delivered_at <= $s->expected_arrival_at)->count() / $delivered->count() * 100, 1) : null,
            'avg_delay' => round((float) $all->where('is_delayed', true)->avg('delay_hours'), 1),
        ];
    }

    /** Spreadsheet-injection safe cell. */
    public static function cell(mixed $v): string
    {
        $s = (string) $v;

        return preg_match('/^[=+\-@\t\r]/', $s) ? "'".$s : $s;
    }
}
