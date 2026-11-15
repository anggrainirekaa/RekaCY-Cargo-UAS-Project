<?php

namespace App\Http\Controllers;

use App\Models\Courier;
use App\Models\Shipment;
use App\Services\CourierLocationService;
use App\Services\LiveMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Polling endpoints (used when the WebSocket is unavailable) and initial state for live widgets. */
class LiveController extends Controller
{
    public function shipment(Shipment $shipment, CourierLocationService $gps): JsonResponse
    {
        Gate::authorize('view', $shipment); // customers: own shipments only

        return response()->json($gps->liveState($shipment));
    }

    public function metrics(LiveMetrics $metrics): JsonResponse
    {
        Gate::authorize('operate');

        return response()->json($metrics->snapshot());
    }

    public function couriers(CourierLocationService $gps): JsonResponse
    {
        Gate::authorize('operate');

        $rows = Courier::query()->where('is_tracking', true)->with(['user:id,name', 'assignments' => fn ($q) => $q->active()->with('shipment:id,shipment_number')])->get()
            ->map(function (Courier $c) use ($gps) {
                $a = $c->assignments->first(fn ($x) => $x->status->tracksGps());
                $loc = $a?->locations()->reorder()->latest('recorded_at')->first();

                return $loc ? [
                    'courier_id' => $c->id, 'name' => $c->user->name, 'gps' => $c->gpsState(),
                    'shipment_id' => $a->shipment_id, 'shipment_number' => $a->shipment->shipment_number,
                    'location' => $gps->present($loc),
                ] : null;
            })->filter()->values();

        return response()->json(['data' => $rows]);
    }
}
