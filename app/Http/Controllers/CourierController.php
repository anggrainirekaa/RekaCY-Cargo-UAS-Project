<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Courier;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class CourierController extends Controller
{
    public function index(): View
    {
        $couriers = Courier::query()->with(['user:id,name,email,is_active', 'assignments' => fn ($q) => $q->active()->with('shipment:id,shipment_number')])->get()
            ->sortBy(fn ($c) => $c->user->name)->values();

        return view('couriers.index', ['couriers' => $couriers]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-users');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'phone' => ['nullable', 'string', 'max:30'],
            'vehicle_type' => ['required', 'in:motorcycle,van,truck,bicycle'],
            'vehicle_plate' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'phone' => $data['phone'] ?? null]);
        $user->forceFill(['role' => Role::Courier])->save();
        $courier = Courier::create(['user_id' => $user->id, 'phone' => $data['phone'] ?? null, 'vehicle_type' => $data['vehicle_type'], 'vehicle_plate' => $data['vehicle_plate'] ?? null, 'availability' => 'AVAILABLE']);
        $audit->log('courier.created', $courier, null, ['email' => $user->email], $request->user());

        return back()->with('status', "Courier {$user->name} created.");
    }

    /** API/polling: active courier list. */
    public function apiIndex(): JsonResponse
    {
        Gate::authorize('operate');

        return response()->json(['data' => Courier::query()->with('user:id,name')->where('is_active', true)->get()->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->user->name, 'availability' => $c->availability, 'gps' => $c->gpsState(),
            'vehicle' => $c->vehicle_type, 'last_seen_at' => $c->last_seen_at?->toIso8601String(),
        ])]);
    }
}
