@extends('layouts.app')
@section('title', 'Couriers')

@section('content')
@include('partials.map-assets')
<div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="space-y-3">
        <section class="card !p-0">
            <div class="flex items-center justify-between border-b border-line px-4 py-3"><h2 class="card-title">Live courier map</h2><span class="text-[11px] text-slate-500">positions come only from courier devices (or labelled DEMO points)</span></div>
            <div class="relative h-[22rem]"><div id="cmap" class="absolute inset-0"></div></div>
        </section>
        <section class="card !p-0"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-line">
            <thead class="bg-slate-50"><tr><th class="th">Courier</th><th class="th">Vehicle</th><th class="th">Availability</th><th class="th">GPS</th><th class="th">Active task</th><th class="th">Last signal</th></tr></thead>
            <tbody class="divide-y divide-line">
            @forelse ($couriers as $c)
                @php $gps = $c->gpsState(); $task = $c->assignments->first(); @endphp
                <tr class="hover:bg-slate-50">
                    <td class="td"><p class="font-semibold text-slate-900">{{ $c->user->name }}</p><p class="text-xs text-slate-500">{{ $c->user->email }}</p></td>
                    <td class="td">{{ ucfirst($c->vehicle_type) }}{{ $c->vehicle_plate ? ' · '.$c->vehicle_plate : '' }}</td>
                    <td class="td"><span class="badge {{ ['AVAILABLE' => 'bg-emerald-500/10 text-emerald-300 ring-emerald-400/30', 'ON_DELIVERY' => 'bg-cyan-500/10 text-cyan-300 ring-cyan-400/30', 'OFFLINE' => 'bg-slate-100 text-slate-600 ring-slate-200'][$c->availability] ?? '' }}">{{ $c->availability }}</span></td>
                    <td class="td"><span class="badge {{ ['ACTIVE' => 'bg-emerald-500/10 text-emerald-300 ring-emerald-400/30', 'STALE' => 'bg-amber-500/10 text-amber-300 ring-amber-400/30', 'OFF' => 'bg-slate-100 text-slate-600 ring-slate-200'][$gps] }}">{{ $gps }}</span></td>
                    <td class="td">@if ($task)<a class="link" href="{{ route('shipments.show', $task->shipment_id) }}">{{ $task->shipment->shipment_number }}</a> <span class="text-xs text-slate-500">{{ $task->status->label() }}</span>@else<span class="text-slate-400">—</span>@endif</td>
                    <td class="td whitespace-nowrap text-xs text-slate-500">{{ $c->last_seen_at?->diffForHumans() ?? 'never' }}</td>
                </tr>
            @empty<tr><td colspan="6" class="py-12 text-center text-sm text-slate-500">No couriers yet.</td></tr>@endforelse
            </tbody></table></div></section>
    </div>

    @can('manage-users')
    <form method="POST" action="{{ route('couriers.store') }}" class="card space-y-3 self-start">@csrf
        <h2 class="card-title">Add courier</h2>
        <div><label class="label" for="name">Name</label><input id="name" name="name" value="{{ old('name') }}" required class="input"></div>
        <div><label class="label" for="email">Email (login)</label><input id="email" type="email" name="email" value="{{ old('email') }}" required class="input"></div>
        <div><label class="label" for="password">Password</label><input id="password" type="password" name="password" required class="input"></div>
        <div class="grid grid-cols-2 gap-3"><div><label class="label" for="vehicle_type">Vehicle</label><select id="vehicle_type" name="vehicle_type" class="input">@foreach (['motorcycle', 'van', 'truck', 'bicycle'] as $v)<option>{{ $v }}</option>@endforeach</select></div>
            <div><label class="label" for="vehicle_plate">Plate</label><input id="vehicle_plate" name="vehicle_plate" class="input" maxlength="20"></div></div>
        <div><label class="label" for="phone">Phone</label><input id="phone" name="phone" class="input"></div>
        <button class="btn-primary w-full">Create courier</button>
    </form>
    @endcan
</div>
@endsection

@push('scripts')
<script>
window.addEventListener('load', () => {
    const ctl = rkMap('cmap', { routes: [], exceptions: [], ports: [] }, { mode: 'full' });
    const place = (c) => ctl.setLive(c.shipment_id, c.latitude ?? c.location?.latitude, c.longitude ?? c.location?.longitude, { number: c.shipment_number, courier: c.courier ?? c.name, source: c.source ?? c.location?.source, at: c.recorded_at ?? c.location?.recorded_at });
    const load = () => fetch(@json(route('live.couriers')), { headers: { Accept: 'application/json' } }).then(r => r.ok ? r.json() : { data: [] }).then(j => { j.data.forEach(place); const pts = j.data.map(c => [c.location.latitude, c.location.longitude]); if (pts.length) ctl.map.fitBounds(pts, { padding: [40, 40], maxZoom: 12 }); });
    load();
    if (window.Echo) window.Echo.private('staff.live').listen('.courier.location', place);
    setInterval(() => { if (window.rkState !== 'live') load(); }, 15000);
});
</script>
@endpush
