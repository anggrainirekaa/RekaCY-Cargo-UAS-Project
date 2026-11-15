<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentStatus;
use App\Http\Requests\ShipmentFilterRequest;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use App\Services\EtaService;
use App\Services\ShipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function __construct(private ShipmentService $shipments)
    {
    }

    public function index(ShipmentFilterRequest $request): View
    {
        Gate::authorize('viewAny', Shipment::class);
        $filters = $request->validated();

        return view('shipments.index', [
            'shipments' => Shipment::query()->visibleTo($request->user())->filter($filters)
                ->with(['customer:id,name', 'carrier:id,name', 'origin:id,city,country', 'destination:id,city,country'])
                ->paginate(12)->withQueryString(),
            'filters' => $filters,
            'carriers' => Carrier::orderBy('name')->get(['id', 'name']),
            'locations' => Location::orderBy('city')->get(['id', 'city', 'country']),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Shipment::class);

        return view('shipments.create', ['shipment' => new Shipment, ...$this->formData()]);
    }

    public function store(StoreShipmentRequest $request): RedirectResponse
    {
        $shipment = $this->shipments->create($request->validated(), $request->user());

        return redirect()->route('shipments.show', $shipment)->with('status', "Shipment {$shipment->shipment_number} created.");
    }

    public function show(Request $request, Shipment $shipment, EtaService $eta): View
    {
        Gate::authorize('view', $shipment);

        $shipment->load([
            'customer', 'carrier', 'origin', 'destination', 'items', 'creator',
            'trackingEvents.creator:id,name', 'exceptions.resolver:id,name', 'latestLocatedEvent', 'documents', 'assignments.courier.user',
        ]);

        return view('shipments.show', [
            'shipment' => $shipment,
            'delay' => $eta->evaluate($shipment),
            'couriers' => $request->user()->isStaff() ? \App\Models\Courier::with('user:id,name')->where('is_active', true)->get()->sortBy(fn ($c) => $c->user->name)->values() : collect(),
            'nextStatuses' => $shipment->status->allowedTransitions(),
            'locations' => Location::orderBy('name')->get(['id', 'name', 'city']),
            'mapData' => [
                'origin' => ['lat' => $shipment->origin->latitude, 'lng' => $shipment->origin->longitude, 'name' => $shipment->origin->label],
                'destination' => ['lat' => $shipment->destination->latitude, 'lng' => $shipment->destination->longitude, 'name' => $shipment->destination->label],
                'points' => $shipment->trackingEvents->filter->hasCoordinates()->map(fn ($e) => [
                    'lat' => $e->latitude, 'lng' => $e->longitude,
                    'title' => $e->status?->label() ?? ucwords(strtolower(str_replace('_', ' ', $e->event_type))),
                    'place' => $e->location_name, 'at' => $e->occurred_at->format('d M Y H:i'),
                    'exception' => $e->is_exception,
                ])->values()->all(),
                'current' => $shipment->latestLocatedEvent?->only(['latitude', 'longitude']),
            ],
        ]);
    }

    public function edit(Shipment $shipment): View
    {
        Gate::authorize('update', $shipment);
        $shipment->load('items');

        return view('shipments.edit', ['shipment' => $shipment, ...$this->formData()]);
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->shipments->update($shipment, $request->validated(), $request->user());

        return redirect()->route('shipments.show', $shipment)->with('status', 'Shipment updated.');
    }

    public function destroy(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('delete', $shipment);
        $this->shipments->delete($shipment, $request->user());

        return redirect()->route('shipments.index')->with('status', 'Shipment deleted.');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'customers' => User::where('role', 'customer')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'company']),
            'carriers' => Carrier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'locations' => Location::orderBy('city')->get(),
            'cargoTypes' => config('rekacy.cargo_types'),
        ];
    }
}
