<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Models\Location;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('operate');

        return JsonResource::collection(Location::query()->orderBy('name')->paginate(50));
    }

    public function store(StoreLocationRequest $request, AuditLogger $audit): JsonResponse
    {
        $location = Location::create($request->validated());
        $audit->log('location.created', $location, null, $location->only(['name', 'city', 'country']), $request->user());

        return (new JsonResource($location))->response()->setStatusCode(201);
    }
}
