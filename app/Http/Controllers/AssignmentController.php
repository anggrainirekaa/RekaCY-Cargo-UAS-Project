<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignCourierRequest;
use App\Models\Courier;
use App\Models\DeliveryAssignment;
use App\Models\Shipment;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Staff assign / cancel courier tasks (web redirects, API returns JSON). */
class AssignmentController extends Controller
{
    public function __construct(private DeliveryService $delivery)
    {
    }

    public function store(AssignCourierRequest $request, Shipment $shipment): RedirectResponse|JsonResponse
    {
        $a = $this->delivery->assign($shipment, Courier::findOrFail($request->validated()['courier_id']), $request->user(), $request->validated()['notes'] ?? null);

        if ($request->is('api/*')) {
            return response()->json(['data' => ['id' => $a->id, 'status' => $a->status->value, 'shipment_id' => $shipment->id, 'courier_id' => $a->courier_id]], 201);
        }

        return back()->with('status', 'Courier assigned.');
    }

    public function destroy(Request $request, DeliveryAssignment $assignment): RedirectResponse|JsonResponse
    {
        Gate::authorize('manage', $assignment);
        $this->delivery->cancel($assignment, $request->user());

        return $request->is('api/*') ? response()->json(['message' => 'Assignment cancelled.']) : back()->with('status', 'Assignment cancelled.');
    }
}
