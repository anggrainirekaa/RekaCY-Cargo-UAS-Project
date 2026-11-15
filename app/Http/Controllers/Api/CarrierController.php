<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCarrierRequest;
use App\Models\Carrier;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class CarrierController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('operate');

        return JsonResource::collection(Carrier::query()->orderBy('name')->paginate(25));
    }

    public function store(StoreCarrierRequest $request, AuditLogger $audit): JsonResponse
    {
        $carrier = Carrier::create($request->validated());
        $audit->log('carrier.created', $carrier, null, $carrier->only(['name', 'code']), $request->user());

        return (new JsonResource($carrier))->response()->setStatusCode(201);
    }
}
