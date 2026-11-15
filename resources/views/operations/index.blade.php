@extends('layouts.app')
@section('title', 'Operations Center')

@section('content')
@include('partials.map-assets')
<div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
    <x-kpi label="Active shipments" :value="$counts['active']" icon="truck" />
    <x-kpi label="Awaiting pickup" :value="$counts['pending_pickup']" icon="box" />
    <x-kpi label="Delayed" :value="$counts['delayed']" tone="gold" icon="clock" />
    <x-kpi label="Critical alerts" :value="$counts['alerts']" tone="red" icon="alert" />
    <x-kpi label="Assigned to me" :value="$mine" tone="gold" icon="user" sub="open exceptions" />
</div>

<div class="mt-3 grid gap-3 xl:grid-cols-3">
    <section class="card !p-0 xl:col-span-2">
        <div class="flex items-center justify-between px-4 pt-4"><div><h2 class="card-title">Shipment activity</h2><p class="card-sub">Active shipments, delayed first</p></div>
            <a href="{{ route('shipments.create') }}" class="btn-primary py-1.5"><x-icon name="plus" class="h-4 w-4" /> New shipment</a></div>
        <div class="mt-3 overflow-x-auto"><table class="min-w-full divide-y divide-line">
            <thead class="bg-slate-50"><tr><th class="th">Shipment</th><th class="th">Customer</th><th class="th">Route</th><th class="th">ETA</th><th class="th">Status</th></tr></thead>
            <tbody class="divide-y divide-line">
            @forelse ($active as $s)
                <tr class="hover:bg-slate-50">
                    <td class="td"><a class="link" href="{{ route('shipments.show', $s) }}">{{ $s->shipment_number }}</a></td>
                    <td class="td">{{ $s->customer->name }}</td>
                    <td class="td whitespace-nowrap">{{ $s->origin->city }} → {{ $s->destination->city }}</td>
                    <td class="td whitespace-nowrap">{{ ($s->estimated_arrival_at ?? $s->expected_arrival_at)->format('d M H:i') }}</td>
                    <td class="td"><div class="flex flex-wrap gap-1"><x-status-badge :status="$s->status" /><x-delay-badge :shipment="$s" /></div></td>
                </tr>
            @empty<tr><td colspan="5" class="py-10 text-center text-sm text-slate-500">No active shipments.</td></tr>@endforelse
            </tbody></table></div>
    </section>

    <section class="card !p-0">
        <div class="flex items-center justify-between border-b border-line px-4 py-3"><h2 class="card-title">Map preview</h2><a href="{{ route('tracking.index') }}" class="link text-xs">Open full map</a></div>
        <div class="relative h-72"><div id="pmap" class="absolute inset-0"></div></div>
        <p class="px-4 py-2">@include('partials.demo-label')</p>
    </section>
</div>

<div class="mt-3 grid gap-3 lg:grid-cols-3">
    <section class="card">
        <div class="flex items-center justify-between"><h2 class="card-title">Delayed shipments</h2><a href="{{ route('operations.eta') }}" class="link text-xs">ETA monitor</a></div>
        <ul class="mt-2 divide-y divide-line">
            @forelse ($delayed as $s)
                <li><a href="{{ route('shipments.show', $s) }}" class="flex items-center justify-between gap-2 py-2"><span class="min-w-0"><span class="block truncate text-sm font-semibold text-slate-900">{{ $s->shipment_number }}</span><span class="block truncate text-xs text-slate-500">{{ $s->carrier->name }} · {{ $s->origin->city }} → {{ $s->destination->city }}</span></span><x-delay-badge :shipment="$s" /></a></li>
            @empty<li class="py-6 text-center text-sm text-slate-500">No delayed shipments.</li>@endforelse
        </ul>
    </section>
    <section class="card">
        <div class="flex items-center justify-between"><h2 class="card-title">Exception queue</h2><a href="{{ route('exceptions.index') }}" class="link text-xs">Center</a></div>
        <ul class="mt-2 divide-y divide-line">
            @forelse ($queue as $e)
                <li><a href="{{ route('exceptions.show', $e) }}" class="block py-2"><div class="flex items-center justify-between gap-2"><span class="text-sm font-semibold text-slate-900">{{ $e->type->label() }}</span><span class="badge {{ $e->severity->badgeClasses() }}">{{ $e->severity->value }}</span></div><p class="truncate text-xs text-slate-500">{{ $e->shipment->shipment_number }} · {{ $e->assignee?->name ?? 'Unassigned' }}</p></a></li>
            @empty<li class="py-6 text-center text-sm text-slate-500">Queue is empty.</li>@endforelse
        </ul>
    </section>
    <section class="card">
        <h2 class="card-title">Recent tracking events</h2>
        <ol class="mt-2 space-y-2">
            @foreach ($events as $e)
                <li class="flex gap-2"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $e->is_exception ? 'bg-gold' : 'bg-cyan-400' }}"></span><div class="min-w-0 text-xs"><a href="{{ route('shipments.show', $e->shipment_id) }}" class="font-semibold text-slate-900 hover:text-cyan-300">{{ $e->shipment->shipment_number }}</a> <span class="text-slate-500">{{ $e->status?->label() ?? str_replace('_', ' ', $e->event_type) }}</span><p class="text-slate-400">{{ $e->occurred_at->diffForHumans() }}</p></div></li>
            @endforeach
        </ol>
    </section>
</div>
@endsection

@push('scripts')
<script>rkMap('pmap', @json($map), { mode: 'preview' });</script>
@endpush
