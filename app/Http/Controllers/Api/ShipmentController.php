<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShipmentFilterRequest;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ShipmentController extends Controller
{
    private const WITH = ['customer:id,name', 'carrier:id,name,code', 'origin', 'destination', 'latestLocatedEvent'];

    public function __construct(private ShipmentService $shipments)
    {
    }

    public function index(ShipmentFilterRequest $request)
    {
        Gate::authorize('viewAny', Shipment::class);
        $filters = $request->validated();

        $page = Shipment::query()->visibleTo($request->user())->filter($filters)
            ->with(self::WITH)->paginate($filters['per_page'] ?? 15)->withQueryString();

        return ShipmentResource::collection($page);
    }

    public function store(StoreShipmentRequest $request): JsonResponse
    {
        $shipment = $this->shipments->create($request->validated(), $request->user());

        return (new ShipmentResource($shipment->load([...self::WITH, 'items'])))->response()->setStatusCode(201);
    }

    public function show(Request $request, Shipment $shipment): ShipmentResource
    {
        Gate::authorize('view', $shipment);

        return new ShipmentResource($shipment->load([...self::WITH, 'items']));
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment): ShipmentResource
    {
        $shipment = $this->shipments->update($shipment, $request->validated(), $request->user());

        return new ShipmentResource($shipment->load([...self::WITH, 'items']));
    }

    public function destroy(Request $request, Shipment $shipment): Response
    {
        Gate::authorize('delete', $shipment);
        $this->shipments->delete($shipment, $request->user());

        return response()->noContent();
    }
}
