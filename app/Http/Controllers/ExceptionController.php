<?php

namespace App\Http\Controllers;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\Role;
use App\Http\Requests\StoreExceptionRequest;
use App\Http\Requests\UpdateExceptionRequest;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use App\Services\AlertFeed;
use App\Services\ExceptionService;
use App\Services\MapService;
use App\Services\RiskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ExceptionController extends Controller
{
    public function __construct(private ExceptionService $exceptions)
    {
    }

    /** Exception control center (staff). */
    public function index(Request $request, MapService $maps): View
    {
        $f = $request->validate([
            'status' => ['nullable', 'in:OPEN,INVESTIGATING,RESOLVED'],
            'type' => ['nullable', 'in:'.implode(',', array_column(ExceptionType::cases(), 'value'))],
            'severity' => ['nullable', 'in:LOW,MEDIUM,HIGH'],
            'carrier_id' => ['nullable', 'integer', 'exists:carriers,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $openStatuses = [ExceptionStatus::Open->value, ExceptionStatus::Investigating->value];

        $list = ShipmentException::query()->with(['shipment:id,shipment_number,carrier_id,origin_id,destination_id', 'shipment.carrier:id,name', 'assignee:id,name'])
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($f['severity'] ?? null, fn ($q, $v) => $q->where('severity', $v))
            ->when($f['assigned_to'] ?? null, fn ($q, $v) => $q->where('assigned_to', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('detected_at', '>=', $v.' 00:00:00'))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->where('detected_at', '<=', $v.' 23:59:59'))
            ->when($f['carrier_id'] ?? null, fn ($q, $v) => $q->whereHas('shipment', fn ($s) => $s->where('carrier_id', $v)))
            ->when($f['location_id'] ?? null, fn ($q, $v) => $q->whereHas('shipment', fn ($s) => $s->where(fn ($w) => $w->where('origin_id', $v)->orWhere('destination_id', $v))))
            ->orderByRaw("case status when 'OPEN' then 0 when 'INVESTIGATING' then 1 else 2 end")
            ->orderByRaw("case severity when 'HIGH' then 0 when 'MEDIUM' then 1 else 2 end")
            ->latest('detected_at')->paginate(12)->withQueryString();

        // 30-day activity by severity (from detected_at)
        $from = now()->subDays(29)->startOfDay();
        $rows = ShipmentException::query()->where('detected_at', '>=', $from)->get(['detected_at', 'severity']);
        $chart = [];
        for ($i = 0; $i < 30; $i++) {
            $d = $from->copy()->addDays($i);
            $day = $rows->filter(fn ($r) => $r->detected_at->isSameDay($d));
            $chart[] = ['label' => $d->format('d M'), 'high' => $day->where('severity.value', 'HIGH')->count(), 'medium' => $day->where('severity.value', 'MEDIUM')->count(), 'low' => $day->where('severity.value', 'LOW')->count()];
        }

        $user = $request->user();

        return view('exceptions.index', [
            'list' => $list,
            'filters' => $f,
            'kpi' => [
                'total' => ShipmentException::count(),
                'critical' => ShipmentException::whereIn('status', $openStatuses)->where('severity', 'HIGH')->count(),
                'high_priority' => ShipmentException::whereIn('status', $openStatuses)->whereIn('severity', ['HIGH', 'MEDIUM'])->count(),
                'investigating' => ShipmentException::where('status', 'INVESTIGATING')->count(),
                'resolved' => ShipmentException::where('status', 'RESOLVED')->count(),
            ],
            'chart' => $chart,
            'priority' => ShipmentException::whereIn('status', $openStatuses)->orderByRaw("case severity when 'HIGH' then 0 when 'MEDIUM' then 1 else 2 end")
                ->latest('detected_at')->with(['shipment:id,shipment_number', 'assignee:id,name'])->limit(5)->get(),
            'alerts' => app(AlertFeed::class)->items($user, 5),
            'risk' => app(RiskService::class)->overview($user, 4),
            'map' => $maps->build($user, ['exceptions_only' => true, 'limit' => 80]),
            'carriers' => Carrier::orderBy('name')->get(['id', 'name']),
            'locations' => Location::orderBy('city')->get(['id', 'city']),
            'operators' => $this->operators(),
        ]);
    }

    public function show(ShipmentException $shipmentException): View
    {
        Gate::authorize('update', $shipmentException);
        $shipmentException->load(['shipment.origin', 'shipment.destination', 'shipment.carrier', 'shipment.customer', 'shipment.trackingEvents', 'notes.author:id,name', 'assignee:id,name', 'resolver:id,name']);

        return view('exceptions.show', ['ex' => $shipmentException, 'operators' => $this->operators()]);
    }

    public function store(StoreExceptionRequest $request, Shipment $shipment): RedirectResponse
    {
        $ex = $this->exceptions->open($shipment, $request->validated(), $request->user());

        return redirect()->route('exceptions.show', $ex)->with('status', 'Exception opened.');
    }

    public function update(UpdateExceptionRequest $request, ShipmentException $shipmentException): RedirectResponse
    {
        $this->exceptions->update($shipmentException, $request->validated(), $request->user());

        return back()->with('status', 'Exception updated.');
    }

    public function note(Request $request, ShipmentException $shipmentException): RedirectResponse
    {
        Gate::authorize('update', $shipmentException);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $this->exceptions->addNote($shipmentException, $data['body'], $request->user());

        return back()->with('status', 'Investigation note added.');
    }

    private function operators()
    {
        return User::whereIn('role', [Role::Admin->value, Role::Operator->value])->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
}
