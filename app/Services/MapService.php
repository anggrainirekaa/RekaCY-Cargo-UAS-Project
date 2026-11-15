<?php

namespace App\Services;

use App\Enums\ExceptionStatus;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;

/**
 * Builds the JSON consumed by the Leaflet command-center map.
 * Positions come ONLY from stored tracking events (never invented); the UI labels the demo dataset as simulated.
 */
class MapService
{
    /**
     * @param  array{exceptions_only?: bool, limit?: int}  $opts
     * @return array{routes: list<array<string,mixed>>, exceptions: list<array<string,mixed>>, ports: list<array<string,mixed>>}
     */
    public function build(User $user, array $opts = []): array
    {
        $limit = $opts['limit'] ?? 120;

        $query = Shipment::query()->visibleTo($user)
            ->with([
                'origin', 'destination', 'carrier:id,name',
                'trackingEvents' => fn ($q) => $q->whereNotNull('latitude')->whereNotNull('longitude'),
                'exceptions' => fn ($q) => $q->where('status', '!=', ExceptionStatus::Resolved->value),
            ]);

        if (! empty($opts['exceptions_only'])) {
            $query->whereHas('exceptions', fn ($q) => $q->where('status', '!=', ExceptionStatus::Resolved->value));
        }

        $active = (clone $query)->active()->latest('id')->limit($limit)->get();
        $done = (clone $query)->where('status', ShipmentStatus::Delivered->value)->latest('delivered_at')->limit(25)->get();
        $shipments = empty($opts['exceptions_only']) ? $active->concat($done) : $active->concat($done)->unique('id');

        $routes = [];
        $exceptions = [];
        $ports = [];

        foreach ($shipments as $s) {
            $events = $s->trackingEvents;
            $path = [[$s->origin->latitude, $s->origin->longitude]];
            foreach ($events as $e) {
                $pt = [$e->latitude, $e->longitude];
                if ($pt !== end($path)) {
                    $path[] = $pt;
                }
            }

            $delivered = $s->status === ShipmentStatus::Delivered;
            $dest = [$s->destination->latitude, $s->destination->longitude];
            if ($delivered && end($path) !== $dest) {
                $path[] = $dest;
            }

            $current = $events->last();
            $openExc = $s->exceptions;
            $critical = $openExc->contains(fn ($e) => $e->severity->value === 'HIGH') || ($s->is_delayed && $s->delay_severity?->value === 'HIGH');
            $attention = $s->is_delayed || $openExc->isNotEmpty();
            $state = $delivered ? 'completed' : ($critical ? 'critical' : ($attention ? 'attention' : 'normal'));

            $routes[] = [
                'id' => $s->id,
                'number' => $s->shipment_number,
                'state' => $state,
                'status' => $s->status->label(),
                'carrier' => $s->carrier->name,
                'carrier_id' => $s->carrier_id,
                'route' => $s->origin->city.' → '.$s->destination->city,
                'eta' => ($s->delivered_at ?? $s->estimated_arrival_at ?? $s->expected_arrival_at)->format('d M Y H:i'),
                'delay' => $s->is_delayed ? round($s->delay_hours, 1) : 0,
                'path' => $path,
                'remaining' => (! $delivered && $current) ? [[$current->latitude, $current->longitude], $dest] : ((! $delivered) ? [$path[0], $dest] : null),
                'current' => $current ? ['lat' => $current->latitude, 'lng' => $current->longitude, 'place' => $current->location_name, 'at' => $current->occurred_at->format('d M Y H:i')] : null,
                'url' => route('shipments.show', $s),
            ];

            foreach ($openExc as $ex) {
                $at = $events->filter(fn ($e) => $e->occurred_at->lte($ex->detected_at))->last();
                $exceptions[] = [
                    'id' => $ex->id,
                    'type' => $ex->type->label(),
                    'severity' => $ex->severity->value,
                    'status' => $ex->status->value,
                    'shipment' => $s->shipment_number,
                    'lat' => $at?->latitude ?? $s->origin->latitude,
                    'lng' => $at?->longitude ?? $s->origin->longitude,
                    'place' => $at?->location_name ?? $s->origin->name,
                    'at' => $ex->detected_at->format('d M Y H:i'),
                    'url' => $user->isStaff() ? route('exceptions.show', $ex) : route('shipments.show', $s),
                ];
            }

            if (! $delivered) {
                foreach ([$s->origin, $s->destination] as $loc) {
                    $k = $loc->id;
                    $ports[$k] ??= ['name' => $loc->name, 'city' => $loc->city, 'lat' => $loc->latitude, 'lng' => $loc->longitude, 'count' => 0];
                    $ports[$k]['count']++;
                }
            }
        }

        return ['routes' => $routes, 'exceptions' => $exceptions, 'ports' => array_values($ports)];
    }
}
