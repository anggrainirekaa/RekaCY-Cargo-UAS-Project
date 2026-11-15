@extends('layouts.app')
@section('title', 'My Overview')

@section('content')
@include('partials.map-assets')
@php $t = $d['totals']; $k = $d['kpis']; @endphp
<div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
    <x-kpi label="My shipments" :value="$t['total']" icon="box" />
    <x-kpi label="In transit" :value="$t['in_transit']" icon="truck" />
    <x-kpi label="Delivered" :value="$t['delivered']" tone="green" icon="check" />
    <x-kpi label="Delayed" :value="$t['delayed']" tone="gold" icon="clock" />
    <x-kpi label="On-time rate" :value="$k['on_time_rate'] === null ? '—' : $k['on_time_rate'].'%'" icon="chart" sub="delivered shipments" />
</div>

<div class="mt-3 grid gap-3 lg:grid-cols-3">
    <section class="card !p-0 lg:col-span-2">
        <div class="flex items-center justify-between border-b border-line px-4 py-3"><h2 class="card-title">Where my cargo is</h2> @include('partials.demo-label')</div>
        <div class="relative h-[26rem]"><div id="gmap" class="absolute inset-0"></div></div>
    </section>
    <section class="card">
        <h2 class="card-title">Needs attention</h2><p class="card-sub mb-2">Delays and open issues on your shipments</p>
        <ul class="divide-y divide-line">
            @forelse ($d['priority'] as $s)
                <li><a href="{{ route('shipments.show', $s) }}" class="flex items-start justify-between gap-3 py-2.5 hover:opacity-90">
                    <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{{ $s->shipment_number }}</p><p class="truncate text-xs text-slate-500">{{ $s->origin->city }} → {{ $s->destination->city }}</p></div>
                    <x-delay-badge :shipment="$s" />
                </a></li>
            @empty<li class="py-8 text-center text-sm text-slate-500">All your shipments are on track.</li>@endforelse
        </ul>
    </section>
</div>

<section class="card mt-3 !p-0">
    <div class="flex items-center justify-between px-4 pt-4"><h2 class="card-title">Recent shipments</h2><a href="{{ route('shipments.index') }}" class="link text-sm">View all</a></div>
    <div class="mt-2 overflow-x-auto"><table class="min-w-full divide-y divide-line">
        <thead class="bg-slate-50"><tr><th class="th">Shipment</th><th class="th">Route</th><th class="th">Expected</th><th class="th">Status</th></tr></thead>
        <tbody class="divide-y divide-line">
        @forelse ($d['recent'] as $s)
            <tr class="hover:bg-slate-50"><td class="td"><a class="link" href="{{ route('shipments.show', $s) }}">{{ $s->shipment_number }}</a></td><td class="td whitespace-nowrap">{{ $s->origin->city }} → {{ $s->destination->city }}</td><td class="td">{{ $s->expected_arrival_at->format('d M Y') }}</td><td class="td"><div class="flex gap-1"><x-status-badge :status="$s->status" /><x-delay-badge :shipment="$s" /></div></td></tr>
        @empty<tr><td colspan="4" class="py-10 text-center text-sm text-slate-500">No shipments yet.</td></tr>@endforelse
        </tbody></table></div>
</section>
@endsection

@push('scripts')
<script>rkMap('gmap', @json($map), { mode: 'full' });</script>
@endpush
