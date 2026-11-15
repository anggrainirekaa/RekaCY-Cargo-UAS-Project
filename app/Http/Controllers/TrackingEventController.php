<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTrackingEventRequest;
use App\Models\Shipment;
use App\Services\TrackingService;
use Illuminate\Http\RedirectResponse;

class TrackingEventController extends Controller
{
    public function store(StoreTrackingEventRequest $request, Shipment $shipment, TrackingService $tracking): RedirectResponse
    {
        $tracking->record($shipment, array_filter($request->validated(), fn ($v) => $v !== null && $v !== ''), $request->user());

        return redirect()->route('shipments.show', $shipment)->with('status', 'Tracking event recorded.');
    }
}
