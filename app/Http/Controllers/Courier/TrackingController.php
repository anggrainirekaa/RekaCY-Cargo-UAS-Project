<?php

namespace App\Http\Controllers\Courier;

use App\Events\ShipmentUpdated;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CourierLocationRequest;
use App\Models\DeliveryAssignment;
use App\Services\AuditLogger;
use App\Services\CourierLocationService;
use App\Support\Live;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GPS endpoints shared by the web courier page (session + CSRF) and the API (Sanctum token). */
class TrackingController extends Controller
{
    public function __construct(private CourierLocationService $gps, private AuditLogger $audit)
    {
    }

    public function start(Request $request): JsonResponse
    {
        $courier = $request->user()->courier;
        abort_unless($courier, 403);

        $active = DeliveryAssignment::query()->where('courier_id', $courier->id)->whereIn('status', ['PICKED_UP', 'IN_TRANSIT', 'ARRIVED'])->with('shipment')->first();
        if (! $active) {
            throw new BusinessRuleException('No delivery in progress. Pick up the cargo before starting GPS tracking.');
        }

        $courier->forceFill(['is_tracking' => true, 'availability' => 'ON_DELIVERY', 'last_seen_at' => $courier->last_seen_at])->save();
        $this->audit->log('courier.tracking_started', $courier, null, ['assignment_id' => $active->id], $request->user());
        Live::dispatch(new ShipmentUpdated($active->shipment, 'tracking', 'GPS tracking started'));

        return response()->json(['tracking' => true, 'assignment_id' => $active->id]);
    }

    public function stop(Request $request): JsonResponse
    {
        $courier = $request->user()->courier;
        abort_unless($courier, 403);

        $courier->forceFill(['is_tracking' => false])->save();
        $this->audit->log('courier.tracking_stopped', $courier, null, null, $request->user());

        $active = DeliveryAssignment::query()->where('courier_id', $courier->id)->active()->with('shipment')->first();
        if ($active) {
            Live::dispatch(new ShipmentUpdated($active->shipment, 'tracking', 'GPS tracking stopped'));
        }

        return response()->json(['tracking' => false]);
    }

    public function store(CourierLocationRequest $request): JsonResponse
    {
        $result = $this->gps->record($request->user()->courier, $request->validated());

        return response()->json([
            'stored' => $result['stored'],
            'eta' => $result['eta'],
            'received_at' => now()->toIso8601String(),
        ], $result['stored'] ? 201 : 200);
    }
}
