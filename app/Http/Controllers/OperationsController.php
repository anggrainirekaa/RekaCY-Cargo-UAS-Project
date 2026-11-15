<?php

namespace App\Http\Controllers;

use App\Enums\ExceptionStatus;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Services\AlertFeed;
use App\Services\MapService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationsController extends Controller
{
    /** Operator control center. */
    public function index(Request $request, MapService $maps): View
    {
        $user = $request->user();
        $with = ['customer:id,name', 'carrier:id,name', 'origin:id,city', 'destination:id,city'];

        return view('operations.index', [
            'active' => Shipment::query()->active()->with($with)->orderByDesc('is_delayed')->orderBy('expected_arrival_at')->limit(10)->get(),
            'delayed' => Shipment::query()->active()->where('is_delayed', true)->with($with)->orderByDesc('delay_hours')->limit(6)->get(),
            'queue' => ShipmentException::query()->where('status', '!=', ExceptionStatus::Resolved->value)
                ->orderByRaw("case severity when 'HIGH' then 0 when 'MEDIUM' then 1 else 2 end")->latest('detected_at')
                ->with(['shipment:id,shipment_number', 'assignee:id,name'])->limit(6)->get(),
            'events' => TrackingEvent::query()->with('shipment:id,shipment_number')->latest('occurred_at')->limit(8)->get(),
            'mine' => ShipmentException::query()->where('assigned_to', $user->id)->where('status', '!=', ExceptionStatus::Resolved->value)->count(),
            'counts' => [
                'active' => Shipment::query()->active()->count(),
                'delayed' => Shipment::query()->active()->where('is_delayed', true)->count(),
                'pending_pickup' => Shipment::query()->whereIn('status', ['CREATED', 'BOOKED'])->count(),
                'alerts' => app(AlertFeed::class)->items($user)->where('level', 'critical')->count(),
            ],
            'map' => $maps->build($user, ['limit' => 40]),
        ]);
    }

    /** ETA monitoring: every active shipment with the delay rule's reasoning. */
    public function eta(Request $request): View
    {
        $filters = $request->validate(['severity' => ['nullable', 'in:LOW,MEDIUM,HIGH,ON_TIME']]);

        $rows = Shipment::query()->active()->with(['carrier:id,name', 'origin:id,city', 'destination:id,city', 'customer:id,name'])
            ->when(($filters['severity'] ?? null) === 'ON_TIME', fn ($q) => $q->where('is_delayed', false))
            ->when(in_array($filters['severity'] ?? null, ['LOW', 'MEDIUM', 'HIGH'], true), fn ($q) => $q->where('delay_severity', $filters['severity']))
            ->orderByDesc('delay_hours')->orderBy('expected_arrival_at')->paginate(15)->withQueryString();

        return view('operations.eta', ['rows' => $rows, 'filters' => $filters]);
    }
}
