<?php

namespace App\Services;

use App\Enums\ExceptionStatus;
use App\Enums\ShipmentStatus;
use App\Models\Carrier;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\AuditLog;
use App\Models\TrackingEvent;
use App\Models\User;
use Carbon\Carbon;

/** Every number on the dashboard is computed from the database, scoped to the viewer. */
class DashboardService
{
    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        $base = fn () => Shipment::query()->visibleTo($user);
        $activeStatuses = [ShipmentStatus::Created->value, ShipmentStatus::Booked->value, ShipmentStatus::PickedUp->value, ShipmentStatus::InTransit->value];

        $total = $base()->count();
        $delayed = $base()->active()->where('is_delayed', true)->count();

        $openExceptions = ShipmentException::query()
            ->whereIn('status', [ExceptionStatus::Open->value, ExceptionStatus::Investigating->value])
            ->whereHas('shipment', fn ($q) => $q->visibleTo($user))
            ->count();

        $byStatus = [];
        $counts = $base()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        foreach (ShipmentStatus::cases() as $s) {
            $byStatus[] = ['status' => $s->value, 'label' => $s->label(), 'count' => (int) ($counts[$s->value] ?? 0), 'color' => $s->mapColor()];
        }

        $carrierNames = Carrier::pluck('name', 'id');
        $byCarrier = $base()
            ->selectRaw('carrier_id, count(*) as total, sum(case when is_delayed = 1 then 1 else 0 end) as delayed')
            ->groupBy('carrier_id')->get()
            ->map(fn ($r) => [
                'carrier' => $carrierNames[$r->carrier_id] ?? '—',
                'total' => (int) $r->total,
                'delayed' => (int) $r->delayed,
                'delay_rate' => $r->total ? round($r->delayed / $r->total * 100, 1) : 0.0,
            ])->sortByDesc('total')->values()->all();

        $delivered = $base()->where('status', ShipmentStatus::Delivered->value);
        $deliveredCount = (clone $delivered)->count();
        $onTime = (clone $delivered)->whereColumn('delivered_at', '<=', 'expected_arrival_at')->count();

        $resolved = ShipmentException::query()
            ->whereNotNull('resolved_at')
            ->whereHas('shipment', fn ($q) => $q->visibleTo($user))
            ->get(['detected_at', 'resolved_at']);

        return [
            'totals' => [
                'total' => $total,
                'in_transit' => $base()->where('status', ShipmentStatus::InTransit->value)->count(),
                'delivered' => $deliveredCount,
                'delayed' => $delayed,
                'open_exceptions' => $openExceptions,
            ],
            'rates' => [
                'delay_rate' => $total ? round($base()->where('is_delayed', true)->count() / $total * 100, 1) : 0.0,
                'exception_rate' => $total ? round(Shipment::query()->visibleTo($user)->whereHas('exceptions')->count() / $total * 100, 1) : 0.0,
            ],
            'kpis' => [
                'on_time_rate' => $deliveredCount ? round($onTime / $deliveredCount * 100, 1) : null,
                'avg_delay_hours' => round((float) ($base()->where('is_delayed', true)->avg('delay_hours') ?? 0), 1),
                'avg_resolution_hours' => $resolved->isEmpty() ? null : round($resolved->avg(fn ($e) => $e->detected_at->diffInMinutes($e->resolved_at, true) / 60), 1),
            ],
            'by_status' => $byStatus,
            'by_carrier' => $byCarrier,
            'weekly_volume' => $this->weeklyVolume($user),
            'priority' => $base()->whereIn('status', $activeStatuses)
                ->where(fn ($q) => $q->where('is_delayed', true)->orWhereHas('exceptions', fn ($e) => $e->where('status', '!=', ExceptionStatus::Resolved->value)))
                ->withCount(['exceptions as open_exceptions_count' => fn ($q) => $q->where('status', '!=', ExceptionStatus::Resolved->value)])
                ->with(['carrier:id,name', 'origin:id,city', 'destination:id,city'])
                ->orderByDesc('delay_hours')->limit(8)->get(),
            'recent' => $base()->with(['customer:id,name', 'carrier:id,name', 'origin:id,city,country', 'destination:id,city,country'])->latest()->limit(8)->get(),
            'activity' => TrackingEvent::query()
                ->whereHas('shipment', fn ($q) => $q->visibleTo($user))
                ->with('shipment:id,shipment_number')->latest('occurred_at')->limit(8)->get(),
            'map' => $this->mapPoints($user),
        ];
    }


    /** @return array<string, mixed> Staff command-center payload (all values come from the database). */
    public function commandCenter(User $user): array
    {
        $vis = fn () => Shipment::query()->visibleTo($user);
        $open = [ExceptionStatus::Open->value, ExceptionStatus::Investigating->value];
        $exc = fn () => ShipmentException::query()->whereHas('shipment', fn ($q) => $q->visibleTo($user));
        $w1 = now()->subDays(7);
        $w2 = now()->subDays(14);

        $trend = function (int $cur, int $prev): ?float {
            return $prev > 0 ? round(($cur - $prev) / $prev * 100) : null;
        };

        $criticalShipments = $exc()->whereIn('status', $open)->where('severity', 'HIGH')->pluck('shipment_id')
            ->merge($vis()->active()->where('delay_severity', 'HIGH')->pluck('id'))->unique()->count();

        $kpis = [
            ['key' => 'total', 'label' => 'Total shipments', 'value' => $vis()->count(), 'tone' => 'cyan', 'icon' => 'box',
                'trend' => $trend($vis()->where('created_at', '>=', $w1)->count(), $vis()->whereBetween('created_at', [$w2, $w1])->count())],
            ['key' => 'in_transit', 'label' => 'In transit', 'value' => $vis()->where('status', ShipmentStatus::InTransit->value)->count(), 'tone' => 'cyan', 'icon' => 'truck', 'trend' => null],
            ['key' => 'delivered', 'label' => 'Delivered', 'value' => $vis()->where('status', ShipmentStatus::Delivered->value)->count(), 'tone' => 'green', 'icon' => 'check',
                'trend' => $trend($vis()->where('delivered_at', '>=', $w1)->count(), $vis()->whereBetween('delivered_at', [$w2, $w1])->count())],
            ['key' => 'delayed', 'label' => 'Delayed', 'value' => $vis()->active()->where('is_delayed', true)->count(), 'tone' => 'gold', 'icon' => 'clock', 'trend' => null],
            ['key' => 'exceptions', 'label' => 'Total exceptions', 'value' => $exc()->count(), 'tone' => 'gold', 'icon' => 'alert',
                'trend' => $trend($exc()->where('detected_at', '>=', $w1)->count(), $exc()->whereBetween('detected_at', [$w2, $w1])->count())],
            ['key' => 'critical', 'label' => 'Critical alerts', 'value' => $criticalShipments, 'tone' => 'red', 'icon' => 'shield', 'trend' => null],
        ];

        // 14-day activity trend
        $from = now()->subDays(13)->startOfDay();
        $created = $vis()->where('created_at', '>=', $from)->pluck('created_at');
        $detected = $exc()->where('detected_at', '>=', $from)->pluck('detected_at');
        $activity = [];
        for ($i = 0; $i < 14; $i++) {
            $d = $from->copy()->addDays($i);
            $activity[] = [
                'label' => $d->format('d M'),
                'shipments' => $created->filter(fn ($c) => $c->isSameDay($d))->count(),
                'exceptions' => $detected->filter(fn ($c) => $c->isSameDay($d))->count(),
            ];
        }

        $byStatus = [];
        $counts = $vis()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        foreach (ShipmentStatus::cases() as $s) {
            $byStatus[] = ['label' => $s->label(), 'count' => (int) ($counts[$s->value] ?? 0), 'color' => $s->mapColor()];
        }

        $region = fn (string $col) => $vis()->join('locations', 'locations.id', '=', "shipments.{$col}")
            ->selectRaw('locations.country as country, count(*) as c')->groupBy('locations.country')->orderByDesc('c')->limit(5)->get()
            ->map(fn ($r) => ['country' => $r->country, 'count' => (int) $r->c])->all();

        $alerts = app(AlertFeed::class)->items($user);
        $risk = app(RiskService::class)->overview($user);
        $map = app(MapService::class)->build($user);

        $delayed = $vis()->active()->where('is_delayed', true);
        $delayMonitor = [
            'low' => (clone $delayed)->where('delay_severity', 'LOW')->count(),
            'medium' => (clone $delayed)->where('delay_severity', 'MEDIUM')->count(),
            'high' => (clone $delayed)->where('delay_severity', 'HIGH')->count(),
            'escalated' => (clone $delayed)->where('delay_hours', '>=', config('rekacy.delay.escalate_hours'))->count(),
            'worst' => (clone $delayed)->orderByDesc('delay_hours')->limit(3)->get(['id', 'shipment_number', 'delay_hours', 'delay_severity']),
        ];

        $deviations = $exc()->whereIn('status', $open)->where('type', 'MISROUTED')->with('shipment:id,shipment_number')->latest('detected_at')->limit(3)->get();

        return [
            'kpis' => $kpis,
            'activity' => $activity,
            'by_status' => $byStatus,
            'regions' => ['destination' => $region('destination_id'), 'origin' => $region('origin_id')],
            'carriers' => $this->carrierStats($user),
            'map' => $map,
            'alerts' => $alerts,
            'critical_alerts' => $alerts->where('level', 'critical')->take(5)->values(),
            'priority_exceptions' => $exc()->whereIn('status', $open)
                ->orderByRaw("case severity when 'HIGH' then 0 when 'MEDIUM' then 1 else 2 end")->latest('detected_at')
                ->with(['shipment:id,shipment_number', 'assignee:id,name'])->limit(5)->get(),
            'latest_events' => TrackingEvent::query()->whereHas('shipment', fn ($q) => $q->visibleTo($user))
                ->with('shipment:id,shipment_number')->latest('occurred_at')->limit(6)->get(),
            'audit' => $user->isStaff() ? AuditLog::query()->with('user:id,name')->latest('id')->limit(5)->get() : collect(),
            'deviations' => $deviations,
            'delay_monitor' => $delayMonitor,
            'risk' => $risk,
            'unread' => $user->unreadNotifications()->count(),
            'indicators' => $this->indicators($user),
            'live' => app(LiveMetrics::class)->snapshot(),
        ];
    }

    /** @return array<string, float|null> */
    private function indicators(User $user): array
    {
        $vis = fn () => Shipment::query()->visibleTo($user);
        $total = $vis()->count();
        $delivered = $vis()->where('status', ShipmentStatus::Delivered->value);
        $dc = (clone $delivered)->count();
        $resolved = ShipmentException::query()->whereNotNull('resolved_at')->whereHas('shipment', fn ($q) => $q->visibleTo($user))->get(['detected_at', 'resolved_at']);

        return [
            'on_time_rate' => $dc ? round((clone $delivered)->whereColumn('delivered_at', '<=', 'expected_arrival_at')->count() / $dc * 100, 1) : null,
            'avg_delay_hours' => round((float) ($vis()->where('is_delayed', true)->avg('delay_hours') ?? 0), 1),
            'exception_rate' => $total ? round($vis()->whereHas('exceptions')->count() / $total * 100, 1) : 0.0,
            'avg_resolution_hours' => $resolved->isEmpty() ? null : round($resolved->avg(fn ($e) => $e->detected_at->diffInMinutes($e->resolved_at, true) / 60), 1),
        ];
    }

    /** @return list<array<string, mixed>> Carrier performance = on-time rate, delay rate, exception rate. */
    public function carrierStats(User $user): array
    {
        $names = Carrier::pluck('name', 'id');

        $rows = Shipment::query()->visibleTo($user)
            ->selectRaw("carrier_id, count(*) as total,
                sum(case when is_delayed = 1 then 1 else 0 end) as delayed,
                sum(case when status = 'DELIVERED' then 1 else 0 end) as delivered,
                sum(case when status = 'DELIVERED' and delivered_at <= expected_arrival_at then 1 else 0 end) as on_time")
            ->groupBy('carrier_id')->get();

        $exc = ShipmentException::query()->join('shipments', 'shipments.id', '=', 'shipment_exceptions.shipment_id')
            ->whereNull('shipments.deleted_at')
            ->when($user->isCustomer(), fn ($q) => $q->where('shipments.customer_id', $user->id))
            ->selectRaw('shipments.carrier_id as carrier_id, count(*) as c')->groupBy('shipments.carrier_id')->pluck('c', 'carrier_id');

        return $rows->map(fn ($r) => [
            'carrier' => $names[$r->carrier_id] ?? '—',
            'total' => (int) $r->total,
            'delivered' => (int) $r->delivered,
            'on_time_rate' => $r->delivered ? round($r->on_time / $r->delivered * 100, 1) : null,
            'delay_rate' => $r->total ? round($r->delayed / $r->total * 100, 1) : 0.0,
            'exception_rate' => $r->total ? round(($exc[$r->carrier_id] ?? 0) / $r->total * 100, 1) : 0.0,
            'exceptions' => (int) ($exc[$r->carrier_id] ?? 0),
        ])->sortByDesc('total')->values()->all();
    }

    /** @return list<array{label: string, count: int}> */
    private function weeklyVolume(User $user): array
    {
        $start = now()->startOfWeek()->subWeeks(7);
        $dates = Shipment::query()->visibleTo($user)->where('created_at', '>=', $start)->pluck('created_at');

        $weeks = [];
        for ($i = 0; $i < 8; $i++) {
            $from = $start->copy()->addWeeks($i);
            $to = $from->copy()->addWeek();
            $weeks[] = [
                'label' => $from->format('d M'),
                'count' => $dates->filter(fn (Carbon $d) => $d->gte($from) && $d->lt($to))->count(),
            ];
        }

        return $weeks;
    }

    /** @return list<array<string, mixed>> */
    private function mapPoints(User $user): array
    {
        return Shipment::query()->visibleTo($user)->active()
            ->with(['latestLocatedEvent', 'origin', 'destination'])->limit(80)->get()
            ->filter(fn ($s) => $s->latestLocatedEvent)
            ->map(fn ($s) => [
                'id' => $s->id,
                'number' => $s->shipment_number,
                'status' => $s->status->label(),
                'color' => $s->is_delayed ? '#f59e0b' : $s->status->mapColor(),
                'delayed' => $s->is_delayed,
                'lat' => $s->latestLocatedEvent->latitude,
                'lng' => $s->latestLocatedEvent->longitude,
                'place' => $s->latestLocatedEvent->location_name,
                'at' => $s->latestLocatedEvent->occurred_at->format('d M Y H:i'),
                'route' => $s->origin->city.' → '.$s->destination->city,
                'url' => route('shipments.show', $s),
            ])->values()->all();
    }
}
