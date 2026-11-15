<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrackingEventRequest;
use App\Http\Resources\TrackingEventResource;
use App\Models\Shipment;
use App\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TrackingController extends Controller
{
    public function __construct(private TrackingService $tracking)
    {
    }

    public function index(Request $request, Shipment $shipment)
    {
        Gate::authorize('view', $shipment);

        return TrackingEventResource::collection($shipment->trackingEvents()->with('creator:id,name')->get());
    }

    public function store(StoreTrackingEventRequest $request, Shipment $shipment): JsonResponse
    {
        $event = $this->tracking->record($shipment, $request->validated(), $request->user());

        return (new TrackingEventResource($event->load('creator:id,name')))->response()->setStatusCode(201);
    }
}
