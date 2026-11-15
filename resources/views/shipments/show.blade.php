@extends('layouts.app')
@section('title', $shipment->shipment_number)
@section('subtitle', $shipment->origin->city.' → '.$shipment->destination->city.' · '.$shipment->carrier->name)

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
@php
    $staff = auth()->user()->isStaff();
    $steps = \App\Enums\ShipmentStatus::lifecycle();
    $currentIdx = array_search($shipment->status, $steps, true);
    $terminal = $shipment->status->isTerminal();
@endphp

{{-- Header --}}
<div class="card mb-4">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-extrabold text-slate-900">{{ $shipment->shipment_number }}</h1>
                <x-status-badge :status="$shipment->status" /><x-delay-badge :shipment="$shipment" />
                <span class="badge {{ ($shipment->priority ?? \App\Enums\ShipmentPriority::Normal)->badgeClasses() }}">{{ $shipment->priority?->value ?? 'NORMAL' }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500">{{ $shipment->cargo_type }} · {{ number_format($shipment->weight_kg, 0) }} kg @if($shipment->volume_cbm)· {{ $shipment->volume_cbm }} m³ @endif· Customer: {{ $shipment->customer->name }}</p>
        </div>
        @unless ($staff)<a href="{{ route('support.create', ['shipment' => $shipment->id]) }}" class="btn-ghost">Report an issue</a>@endunless
        @if ($staff)
        <div class="flex gap-2">
            @if (! $terminal)<a href="{{ route('shipments.edit', $shipment) }}" class="btn-ghost">Edit</a>@endif
            @can('delete', $shipment)
                @if (in_array($shipment->status, [\App\Enums\ShipmentStatus::Created, \App\Enums\ShipmentStatus::Cancelled]))
                <form method="POST" action="{{ route('shipments.destroy', $shipment) }}" onsubmit="return confirm('Delete this shipment?')">@csrf @method('DELETE')<button class="btn-danger">Delete</button></form>
                @endif
            @endcan
        </div>
        @endif
    </div>

    {{-- Lifecycle stepper --}}
    @if ($shipment->status === \App\Enums\ShipmentStatus::Cancelled)
        <p class="mt-5 rounded-xl bg-rose-500/10 px-4 py-3 text-sm font-medium text-rose-300 ring-1 ring-rose-400/30">This shipment was cancelled and can no longer change.</p>
    @else
        <ol class="mt-6 grid grid-cols-5 gap-2" aria-label="Shipment lifecycle">
            @foreach ($steps as $i => $step)
                @php $done = $i <= $currentIdx; @endphp
                <li class="relative">
                    <div class="h-1.5 rounded-full {{ $done ? 'bg-cyan-500' : 'bg-slate-200' }}"></div>
                    <p class="mt-2 text-xs font-semibold {{ $done ? 'text-cyan-300' : 'text-slate-400' }}">{{ $step->label() }}</p>
                </li>
            @endforeach
        </ol>
    @endif
</div>

<div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        {{-- Map --}}
        <section class="card">
            <div class="mb-3 flex items-center justify-between">
                <div><h2 class="card-title">Route & current position</h2>
                    <p class="card-sub">@if($shipment->latestLocatedEvent) Last position: {{ $shipment->latestLocatedEvent->location_name ?? 'coordinates' }} · {{ $shipment->latestLocatedEvent->occurred_at->diffForHumans() }} @else No located tracking event yet @endif</p></div>
            </div>
            <div id="map" class="h-80 w-full bg-slate-100"></div>
        </section>

        {{-- Timeline --}}
        <section class="card">
            <h2 class="card-title">Tracking timeline</h2><p class="card-sub mb-4">Chronological · events are append-only history</p>
            <ol class="relative ml-2 space-y-5 border-l-2 border-slate-200 pl-6">
                @foreach ($shipment->trackingEvents as $e)
                    @php $last = $loop->last; @endphp
                    <li class="relative">
                        <span class="absolute -left-[33px] top-1 flex h-4 w-4 items-center justify-center rounded-full ring-4 ring-panel {{ $e->is_exception ? 'bg-rose-500' : ($e->status ? 'bg-cyan-500' : 'bg-slate-400') }}"></span>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-slate-900">{{ $e->status?->label() ?? ucwords(strtolower(str_replace('_', ' ', $e->event_type))) }}</p>
                            @if ($e->is_exception)<span class="badge bg-rose-500/10 text-rose-300 ring-rose-400/30">Exception</span>@endif
                            @if ($last)<span class="badge bg-cyan-500/10 text-cyan-300 ring-cyan-400/30">Latest</span>@endif
                        </div>
                        <p class="text-xs text-slate-500">{{ $e->occurred_at->format('d M Y, H:i') }}@if($e->location_name) · {{ $e->location_name }}@endif @if($e->hasCoordinates())<span class="text-slate-400">({{ number_format($e->latitude, 3) }}, {{ number_format($e->longitude, 3) }})</span>@endif</p>
                        @if ($e->description)<p class="mt-1 text-sm text-slate-600">{{ $e->description }}</p>@endif
                        @if ($staff && $e->creator)<p class="mt-0.5 text-[11px] text-slate-400">by {{ $e->creator->name }}</p>@endif
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Exceptions --}}
        <section class="card">
            <h2 class="card-title">Exceptions</h2><p class="card-sub mb-3">Customs holds, damage, misrouting and other disruptions</p>
            <div class="space-y-3">
                @forelse ($shipment->exceptions as $ex)
                    <div class="rounded-xl p-4 ring-1 {{ $ex->isOpen() ? 'bg-rose-500/10 ring-rose-400/30' : 'bg-slate-50 ring-slate-200' }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-slate-900">{{ $ex->type->label() }}</p>
                            <span class="badge {{ $ex->severity->badgeClasses() }}">{{ $ex->severity->value }}</span>
                            <span class="badge {{ $ex->status->badgeClasses() }}">{{ $ex->status->value }}</span>
                            @if ($staff)<a class="link ml-auto text-xs" href="{{ route('exceptions.show', $ex) }}">Open case →</a>@endif
                        </div>
                        <p class="mt-1 text-sm text-slate-600">{{ $ex->description }}</p>
                        <p class="mt-1 text-xs text-slate-400">Detected {{ $ex->detected_at->format('d M Y H:i') }}@if($ex->resolved_at) · Resolved {{ $ex->resolved_at->format('d M Y H:i') }}@if($ex->resolver) by {{ $ex->resolver->name }}@endif @endif</p>
                        @if ($ex->resolution_note)<p class="mt-1 text-xs text-slate-500">Resolution: {{ $ex->resolution_note }}</p>@endif

                        @if ($staff && $ex->status->allowedTransitions())
                            <form method="POST" action="{{ route('exceptions.update', $ex) }}" class="mt-3 flex flex-wrap items-end gap-2">
                                @csrf @method('PATCH')
                                <div><label class="label" for="st{{ $ex->id }}">Move to</label>
                                    <select id="st{{ $ex->id }}" name="status" class="input py-1.5">
                                        @foreach ($ex->status->allowedTransitions() as $to)<option value="{{ $to->value }}">{{ $to->value }}</option>@endforeach
                                    </select></div>
                                <div class="min-w-48 flex-1"><label class="label" for="rn{{ $ex->id }}">Resolution note (required to resolve)</label><input id="rn{{ $ex->id }}" name="resolution_note" class="input py-1.5"></div>
                                <button class="btn-navy py-1.5">Update</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="rounded-xl bg-slate-50 py-6 text-center text-sm text-slate-500">No exceptions recorded.</p>
                @endforelse
            </div>

            @if ($staff && $shipment->status !== \App\Enums\ShipmentStatus::Cancelled)
                <form method="POST" action="{{ route('shipments.exceptions.store', $shipment) }}" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-4">
                    @csrf
                    <div><label class="label" for="ex_type">Type</label>
                        <select id="ex_type" name="type" class="input">@foreach (\App\Enums\ExceptionType::cases() as $t)<option value="{{ $t->value }}" @selected($t->value === config('rekacy.group.main_exception'))>{{ $t->label() }}</option>@endforeach</select></div>
                    <div><label class="label" for="ex_sev">Severity</label>
                        <select id="ex_sev" name="severity" class="input"><option value="">Auto (by type)</option>@foreach (\App\Enums\Severity::cases() as $sv)<option value="{{ $sv->value }}">{{ $sv->value }}</option>@endforeach</select></div>
                    <div class="sm:col-span-2"><label class="label" for="ex_desc">Description</label><input id="ex_desc" name="description" required class="input"></div>
                    <div class="sm:col-span-4 text-right"><button class="btn-danger">Open exception</button></div>
                </form>
            @endif
        </section>
    </div>

    <div class="space-y-4">
        {{-- Delay --}}
        <section class="card">
            <h2 class="card-title">ETA &amp; delay rule</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Expected arrival</dt><dd class="font-medium">{{ $shipment->expected_arrival_at->format('d M Y H:i') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Current ETA</dt><dd class="font-medium">{{ $shipment->estimated_arrival_at?->format('d M Y H:i') ?? '—' }}</dd></div>
                @if ($shipment->delivered_at)<div class="flex justify-between"><dt class="text-slate-500">Delivered</dt><dd class="font-medium">{{ $shipment->delivered_at->format('d M Y H:i') }}</dd></div>@endif
                <div class="flex justify-between"><dt class="text-slate-500">Departure</dt><dd class="font-medium">{{ $shipment->expected_departure_at->format('d M Y H:i') }}</dd></div>
            </dl>
            <div class="mt-4 rounded-xl p-3 text-sm ring-1 {{ $delay->isDelayed ? 'bg-amber-500/10 text-amber-300 ring-amber-400/30' : 'bg-emerald-500/10 text-emerald-300 ring-emerald-400/30' }}">
                <p class="font-semibold">{{ $delay->isDelayed ? 'Delayed' : 'On time' }}@if($delay->escalate) · escalated @endif</p>
                <p class="mt-1 text-xs leading-relaxed">{{ $delay->reason }}</p>
            </div>
        </section>

        {{-- Courier assignment (staff) --}}
        @if ($staff)
        <section class="card">
            <h2 class="card-title">Courier assignment</h2>
            @php $current = $shipment->assignments->first(fn ($x) => $x->status->isActive()); @endphp
            @if ($current)
                <div class="mt-2 rounded-lg bg-deep p-3 ring-1 ring-line"><div class="flex items-center justify-between gap-2"><p class="font-semibold text-slate-900">{{ $current->courier->user->name }}</p><span class="badge {{ $current->status->badgeClasses() }}">{{ $current->status->label() }}</span></div>
                    <p class="text-xs text-slate-500">Assigned {{ $current->assigned_at->diffForHumans() }} · {{ $current->courier->vehicle_type }}</p>
                    <form method="POST" action="{{ route('assignments.destroy', $current) }}" class="mt-2" onsubmit="return confirm('Cancel this assignment?')">@csrf @method('DELETE')<button class="btn-danger py-1 text-xs">Cancel assignment</button></form></div>
            @else
                <p class="mt-2 text-sm text-slate-500">No active courier. {{ in_array($shipment->status, [\App\Enums\ShipmentStatus::Booked, \App\Enums\ShipmentStatus::PickedUp, \App\Enums\ShipmentStatus::InTransit], true) ? '' : 'Book the shipment first (status BOOKED) to assign a courier.' }}</p>
            @endif
            @if (in_array($shipment->status, [\App\Enums\ShipmentStatus::Booked, \App\Enums\ShipmentStatus::PickedUp, \App\Enums\ShipmentStatus::InTransit], true))
            <form method="POST" action="{{ route('shipments.assign', $shipment) }}" class="mt-3 space-y-2">@csrf
                <label class="label" for="courier_id">{{ $current ? 'Re-assign to' : 'Assign courier' }}</label>
                <select id="courier_id" name="courier_id" required class="input"><option value="">Select courier…</option>@foreach ($couriers as $c)<option value="{{ $c->id }}">{{ $c->user->name }} · {{ $c->availability }}</option>@endforeach</select>
                <input name="notes" placeholder="Notes for the courier (optional)" class="input" maxlength="500">
                <button class="btn-primary w-full py-1.5">{{ $current ? 'Re-assign' : 'Assign' }}</button>
            </form>
            @endif
            @if ($shipment->assignments->count())
                <ul class="mt-3 space-y-1 border-t border-line pt-2 text-[11px] text-slate-500">@foreach ($shipment->assignments as $h)<li>{{ $h->assigned_at->format('d M H:i') }} · {{ $h->courier->user->name }} · {{ $h->status->label() }}@if($h->failure_reason) — {{ $h->failure_reason }}@endif</li>@endforeach</ul>
            @endif
        </section>
        @endif

        @include('shipments._live')

        {{-- Weather --}}
        <section class="card">
            <h2 class="card-title">Weather at last position</h2>
            <div id="weather" class="mt-2 text-sm text-slate-500">Loading…</div>
            <p class="mt-2 text-[11px] text-slate-400">Open-Meteo · context only, not a delay predictor.</p>
        </section>

        {{-- Add tracking event --}}
        @if ($staff && ! $terminal)
        <section class="card">
            <h2 class="card-title">Add tracking event</h2>
            <p class="card-sub">Next allowed statuses: {{ collect($nextStatuses)->map->label()->implode(', ') ?: 'none' }}</p>
            <form method="POST" action="{{ route('shipments.tracking.store', $shipment) }}" class="mt-3 space-y-3">
                @csrf
                <div><label class="label" for="t_status">Status change</label>
                    <select id="t_status" name="status" class="input"><option value="">No change (checkpoint only)</option>
                        @foreach ($nextStatuses as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
                    </select></div>
                <div><label class="label" for="t_loc">Location</label>
                    <select id="t_loc" name="location_id" class="input"><option value="">— custom / none —</option>
                        @foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }} ({{ $l->city }})</option>@endforeach
                    </select></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label" for="t_lat">Latitude</label><input id="t_lat" name="latitude" type="number" step="any" class="input" placeholder="optional"></div>
                    <div><label class="label" for="t_lng">Longitude</label><input id="t_lng" name="longitude" type="number" step="any" class="input" placeholder="optional"></div>
                </div>
                <div><label class="label" for="t_at">Occurred at</label><input id="t_at" name="occurred_at" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}" class="input"></div>
                <div><label class="label" for="t_eta">Update ETA (optional)</label><input id="t_eta" name="estimated_arrival_at" type="datetime-local" class="input"></div>
                <div><label class="label" for="t_desc">Description</label><textarea id="t_desc" name="description" rows="2" class="input"></textarea></div>
                <button class="btn-primary w-full">Record event</button>
            </form>
        </section>
        @endif

        {{-- Documents & proof of delivery --}}
        <section class="card">
            <h2 class="card-title">Documents &amp; proof of delivery</h2>
            <ul class="mt-2 divide-y divide-line text-sm">
                @forelse ($shipment->documents as $doc)
                    <li class="py-2"><div class="flex items-start justify-between gap-2"><div class="min-w-0"><p class="truncate font-semibold text-slate-900">{{ $doc->title }}</p>
                        <p class="text-xs text-slate-500">{{ $doc->type->label() }} · {{ $doc->humanSize() }}@if($doc->recipient_name) · received by {{ $doc->recipient_name }}@endif @if($doc->received_at) · {{ $doc->received_at->format('d M Y H:i') }}@endif</p></div>
                        <div class="shrink-0 text-xs"><a class="link" href="{{ route('documents.download', [$doc, 'inline' => 1]) }}" target="_blank">View</a> · <a class="link" href="{{ route('documents.download', $doc) }}">Download</a></div></div>
                        @if ($doc->notes)<p class="mt-1 text-xs text-slate-500">{{ $doc->notes }}</p>@endif</li>
                @empty<li class="py-4 text-center text-xs text-slate-500">No documents attached.</li>@endforelse
            </ul>
            @if ($staff)
            <form method="POST" action="{{ route('shipments.documents.store', $shipment) }}" enctype="multipart/form-data" class="mt-3 space-y-2 border-t border-line pt-3">@csrf
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label" for="d_type">Type</label><select id="d_type" name="type" class="input py-1.5">@foreach (\App\Enums\DocumentType::cases() as $dt)<option value="{{ $dt->value }}">{{ $dt->label() }}</option>@endforeach</select></div>
                    <div><label class="label" for="d_title">Title</label><input id="d_title" name="title" required class="input py-1.5"></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label" for="d_rec">Recipient (POD)</label><input id="d_rec" name="recipient_name" class="input py-1.5"></div>
                    <div><label class="label" for="d_at">Received at (POD)</label><input id="d_at" name="received_at" type="datetime-local" class="input py-1.5"></div>
                </div>
                <input name="notes" placeholder="Notes (optional)" class="input py-1.5" aria-label="Notes">
                <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-md file:border-0 file:bg-elev file:px-3 file:py-1.5 file:text-slate-700" aria-label="File">
                <p class="text-[11px] text-slate-500">PDF, JPG or PNG up to 5 MB. Recipient and time are required for proof of delivery.</p>
                <button class="btn-navy w-full py-1.5">Upload</button>
            </form>
            @endif
        </section>

        {{-- Cargo --}}
        <section class="card">
            <h2 class="card-title">Cargo items</h2>
            <ul class="mt-2 divide-y divide-slate-100 text-sm">
                @foreach ($shipment->items as $i)<li class="flex justify-between gap-3 py-2"><span>{{ $i->description }}</span><span class="text-slate-500">× {{ $i->quantity }}</span></li>@endforeach
            </ul>
            @if ($shipment->notes)<p class="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">{{ $shipment->notes }}</p>@endif
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(() => {
    const M = @json($mapData);
    const mapEl = document.getElementById('map'); mapEl.classList.add('rk-map');
    const map = L.map(mapEl, { scrollWheelZoom: false }).setView([M.origin.lat, M.origin.lng], 4);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' }).addTo(map);

    const dot = (lat, lng, color, r = 9) => L.circleMarker([lat, lng], { radius: r, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1 }).addTo(map);
    dot(M.origin.lat, M.origin.lng, '#6366f1').bindPopup('<b>Origin</b><br>' + esc(M.origin.name));
    dot(M.destination.lat, M.destination.lng, '#f59e0b').bindPopup('<b>Destination</b><br>' + esc(M.destination.name));

    const path = [[M.origin.lat, M.origin.lng]];
    M.points.forEach(p => {
        path.push([p.lat, p.lng]);
        dot(p.lat, p.lng, p.exception ? '#f43f5e' : '#22d3ee', 6).bindPopup(`<b>${esc(p.title)}</b><br>${esc(p.place)}<br><small>${esc(p.at)}</small>`);
    });
    L.polyline(path, { color: '#22d3ee', weight: 5, opacity: .15 }).addTo(map);
    L.polyline(path, { color: '#22d3ee', weight: 2, className: 'route-cyan' }).addTo(map);
    L.polyline([path[path.length - 1], [M.destination.lat, M.destination.lng]], { color: '#f5be42', weight: 1.6, dashArray: '2 8', className: 'route-flow route-gold' }).addTo(map);
    if (M.current) { L.circleMarker([M.current.latitude, M.current.longitude], { radius: 13, color: '#22d3ee', weight: 2, fill: false }).addTo(map); }
    map.fitBounds([...path, [M.destination.lat, M.destination.lng]], { padding: [30, 30] });
    window.rkShowMap = map;

    // Weather (external API; handles failure states)
    const w = document.getElementById('weather');
    fetch(@json(route('shipments.weather', $shipment)), { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(j => {
            if (!j.ok) { w.textContent = j.error || 'Weather unavailable.'; return; }
            const d = j.data;
            w.innerHTML = `<p class="text-2xl font-extrabold text-slate-900">${esc(d.temperature_c)}°C</p><p class="text-slate-600">${esc(d.summary)} · wind ${esc(d.wind_kmh)} km/h</p><p class="text-xs text-slate-400">Near ${esc(j.place || 'last position')}</p>`;
        })
        .catch(() => { w.textContent = 'Weather service could not be reached.'; });
})();
</script>
@endpush
