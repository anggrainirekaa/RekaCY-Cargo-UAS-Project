<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExceptionRequest;
use App\Http\Requests\UpdateExceptionRequest;
use App\Http\Resources\ExceptionResource;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Services\ExceptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExceptionController extends Controller
{
    public function __construct(private ExceptionService $exceptions)
    {
    }

    public function index(Request $request, Shipment $shipment)
    {
        Gate::authorize('view', $shipment);

        return ExceptionResource::collection($shipment->exceptions()->get());
    }

    /** Cross-shipment exception list, scoped to what the caller may see. */
    public function all(Request $request)
    {
        $f = $request->validate([
            'status' => ['nullable', 'in:OPEN,INVESTIGATING,RESOLVED'], 'severity' => ['nullable', 'in:LOW,MEDIUM,HIGH'],
            'type' => ['nullable', 'string', 'max:30'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return ExceptionResource::collection(
            ShipmentException::query()->whereHas('shipment', fn ($q) => $q->visibleTo($request->user()))
                ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
                ->when($f['severity'] ?? null, fn ($q, $v) => $q->where('severity', $v))
                ->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
                ->latest('detected_at')->paginate($f['per_page'] ?? 15)->withQueryString()
        );
    }

    public function note(Request $request, ShipmentException $shipmentException): JsonResponse
    {
        Gate::authorize('update', $shipmentException);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $note = $this->exceptions->addNote($shipmentException, $data['body'], $request->user());

        return response()->json(['data' => ['id' => $note->id, 'body' => $note->body, 'created_at' => $note->created_at->toIso8601String()]], 201);
    }

    public function store(StoreExceptionRequest $request, Shipment $shipment): JsonResponse
    {
        $exception = $this->exceptions->open($shipment, $request->validated(), $request->user());

        return (new ExceptionResource($exception))->response()->setStatusCode(201);
    }

    public function update(UpdateExceptionRequest $request, ShipmentException $shipmentException): ExceptionResource
    {
        return new ExceptionResource($this->exceptions->update($shipmentException, $request->validated(), $request->user()));
    }
}
