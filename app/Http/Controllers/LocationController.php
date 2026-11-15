<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationRequest;
use App\Models\Location;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(): View
    {
        return view('locations.index', ['locations' => Location::orderBy('country')->orderBy('city')->get()]);
    }

    public function store(StoreLocationRequest $request, AuditLogger $audit): RedirectResponse
    {
        $location = Location::create($request->validated());
        $audit->log('location.created', $location, null, $location->only(['name', 'city', 'country']), $request->user());

        return back()->with('status', "Location {$location->name} added.");
    }
}
