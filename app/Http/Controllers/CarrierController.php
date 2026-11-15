<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCarrierRequest;
use App\Models\Carrier;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CarrierController extends Controller
{
    public function index(): View
    {
        return view('carriers.index', ['carriers' => Carrier::withCount('shipments')->orderBy('name')->get()]);
    }

    public function store(StoreCarrierRequest $request, AuditLogger $audit): RedirectResponse
    {
        $carrier = Carrier::create($request->validated());
        $audit->log('carrier.created', $carrier, null, $carrier->only(['name', 'code']), $request->user());

        return back()->with('status', "Carrier {$carrier->name} added.");
    }
}
