<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteDeliveryRequest;
use App\Http\Requests\FailDeliveryRequest;
use App\Models\DeliveryAssignment;
use App\Services\CourierLocationService;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(private DeliveryService $delivery)
    {
    }

    private function mine(Request $request)
    {
        return DeliveryAssignment::query()->where('courier_id', $request->user()->courier?->id ?? 0)
            ->with(['shipment.origin', 'shipment.destination', 'shipment.customer:id,name,company', 'shipment.carrier:id,name']);
    }

    public function index(Request $request): View
    {
        return view('courier.index', [
            'courier' => $request->user()->courier,
            'tasks' => $this->mine($request)->active()->orderByRaw("case status when 'ARRIVED' then 0 when 'IN_TRANSIT' then 1 when 'PICKED_UP' then 2 when 'ACCEPTED' then 3 else 4 end")->latest('id')->get(),
            'doneToday' => $this->mine($request)->where('status', 'COMPLETED')->where('completed_at', '>=', now()->startOfDay())->count(),
        ]);
    }

    public function history(Request $request): View
    {
        return view('courier.history', ['tasks' => $this->mine($request)->whereIn('status', ['COMPLETED', 'FAILED', 'CANCELLED'])->latest('id')->paginate(15)]);
    }

    public function show(Request $request, DeliveryAssignment $assignment, CourierLocationService $live): View
    {
        Gate::authorize('act', $assignment);
        $assignment->load(['shipment.origin', 'shipment.destination', 'shipment.customer:id,name,company', 'shipment.carrier:id,name', 'courier']);
        $last = $assignment->locations()->reorder()->latest('recorded_at')->first();

        return view('courier.show', [
            'a' => $assignment,
            'last' => $last,
            'points' => $assignment->locations()->count(),
            'demo' => (bool) config('rekacy.demo_data') && ! app()->environment('production'),
        ]);
    }

    public function act(Request $request, DeliveryAssignment $assignment, string $action): RedirectResponse
    {
        Gate::authorize('act', $assignment);
        $user = $request->user();

        match ($action) {
            'accept' => $this->delivery->accept($assignment, $user),
            'pickup' => $this->delivery->pickup($assignment, $user),
            'start' => $this->delivery->start($assignment, $user),
            'arrive' => $this->delivery->arrive($assignment, $user),
            'complete' => $this->completeWith($request, $assignment),
            'fail' => $this->failWith($request, $assignment),
        };

        return redirect()->route('courier.show', $assignment)->with('status', 'Status diperbarui.');
    }

    private function completeWith(Request $request, DeliveryAssignment $assignment): void
    {
        $rules = new CompleteDeliveryRequest;
        $v = $request->validate($rules->rules(), $rules->messages());
        $this->delivery->complete($assignment, $request->user(), $v['recipient_name'], $request->file('photo'), $v['notes'] ?? null);
    }

    private function failWith(Request $request, DeliveryAssignment $assignment): void
    {
        $v = $request->validate((new FailDeliveryRequest)->rules());
        $this->delivery->fail($assignment, $request->user(), $v['reason']);
    }

    /** API: the courier's open tasks. */
    public function apiIndex(Request $request): JsonResponse
    {
        $rows = $this->mine($request)->active()->latest('id')->get()->map(fn ($a) => [
            'id' => $a->id, 'status' => $a->status->value, 'shipment_number' => $a->shipment->shipment_number,
            'origin' => $a->shipment->origin->city, 'destination' => $a->shipment->destination->city,
            'destination_latitude' => $a->shipment->destination->latitude, 'destination_longitude' => $a->shipment->destination->longitude,
        ]);

        return response()->json(['data' => $rows]);
    }
}
