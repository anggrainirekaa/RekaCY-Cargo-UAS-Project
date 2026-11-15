<?php

namespace App\Services;

use App\Enums\ExceptionStatus;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Support\Collection;

/** Alerts are derived from live data (open exceptions + delayed active shipments), never stored separately. */
class AlertFeed
{
    private const WEIGHT = ['critical' => 3, 'warning' => 2, 'info' => 1];

    /** @return Collection<int, array<string, mixed>> */
    public function items(User $user, ?int $limit = null): Collection
    {
        $routeFor = fn (int $shipmentId) => route('shipments.show', $shipmentId);

        $exceptions = ShipmentException::query()
            ->where('status', '!=', ExceptionStatus::Resolved->value)
            ->whereHas('shipment', fn ($q) => $q->visibleTo($user))
            ->with('shipment:id,shipment_number')->get()
            ->map(fn (ShipmentException $e) => [
                'level' => ['HIGH' => 'critical', 'MEDIUM' => 'warning', 'LOW' => 'info'][$e->severity->value],
                'kind' => 'exception',
                'title' => $e->type->label(),
                'text' => $e->description,
                'shipment_id' => $e->shipment_id,
                'shipment_number' => $e->shipment->shipment_number,
                'at' => $e->detected_at,
                'url' => $user->isStaff() ? route('exceptions.show', $e) : $routeFor($e->shipment_id),
            ]);

        $delays = Shipment::query()->visibleTo($user)->active()->where('is_delayed', true)->get(['id', 'shipment_number', 'delay_hours', 'delay_severity', 'updated_at'])
            ->map(fn (Shipment $s) => [
                'level' => ['HIGH' => 'critical', 'MEDIUM' => 'warning', 'LOW' => 'info'][$s->delay_severity?->value ?? 'LOW'],
                'kind' => 'delay',
                'title' => 'Delay '.rtrim(rtrim(number_format($s->delay_hours, 1), '0'), '.').' h',
                'text' => "Shipment is running behind its expected arrival ({$s->delay_severity?->value}).",
                'shipment_id' => $s->id,
                'shipment_number' => $s->shipment_number,
                'at' => $s->updated_at,
                'url' => $routeFor($s->id),
            ]);

        $all = $exceptions->concat($delays)
            ->sortByDesc(fn ($a) => self::WEIGHT[$a['level']] * 1e11 + $a['at']->getTimestamp())->values();

        return $limit ? $all->take($limit)->values() : $all;
    }

    public static function levelClasses(string $level): string
    {
        return match ($level) {
            'critical' => 'bg-red-500/10 text-red-300 ring-red-400/30',
            'warning' => 'bg-amber-500/10 text-amber-300 ring-amber-400/30',
            default => 'bg-cyan-500/10 text-cyan-300 ring-cyan-400/30',
        };
    }
}
