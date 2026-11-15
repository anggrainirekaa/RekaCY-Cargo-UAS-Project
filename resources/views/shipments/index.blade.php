@extends('layouts.app')
@section('title', auth()->user()->isCustomer() ? 'My Shipments' : 'Shipments')
@section('subtitle', $shipments->total().' shipments match the current filters')

@section('content')
@php
    $f = $filters;
    $dir = ($f['direction'] ?? 'desc') === 'asc' ? 'desc' : 'asc';
    $sortUrl = fn ($col) => request()->fullUrlWithQuery(['sort' => $col, 'direction' => (($f['sort'] ?? 'created_at') === $col) ? $dir : 'asc', 'page' => null]);
    $arrow = fn ($col) => ($f['sort'] ?? 'created_at') === $col ? (($f['direction'] ?? 'desc') === 'asc' ? '↑' : '↓') : '';
@endphp

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" class="card grid flex-1 grid-cols-2 gap-3 p-4 md:grid-cols-4 xl:grid-cols-8" role="search">
        <div class="col-span-2"><label class="label" for="q">Search</label><input id="q" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Shipment no. / cargo" class="input"></div>
        <div><label class="label" for="status">Status</label>
            <select id="status" name="status" class="input"><option value="">All</option>
                @foreach (\App\Enums\ShipmentStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($f['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach
            </select></div>
        <div><label class="label" for="carrier_id">Carrier</label>
            <select id="carrier_id" name="carrier_id" class="input"><option value="">All</option>
                @foreach ($carriers as $c)<option value="{{ $c->id }}" @selected(($f['carrier_id'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach
            </select></div>
        <div><label class="label" for="origin_id">Origin</label>
            <select id="origin_id" name="origin_id" class="input"><option value="">Any</option>
                @foreach ($locations as $l)<option value="{{ $l->id }}" @selected(($f['origin_id'] ?? '') == $l->id)>{{ $l->city }}</option>@endforeach
            </select></div>
        <div><label class="label" for="destination_id">Destination</label>
            <select id="destination_id" name="destination_id" class="input"><option value="">Any</option>
                @foreach ($locations as $l)<option value="{{ $l->id }}" @selected(($f['destination_id'] ?? '') == $l->id)>{{ $l->city }}</option>@endforeach
            </select></div>
        <div><label class="label" for="delay">Delay</label>
            <select id="delay" name="delay" class="input"><option value="">Any</option>
                <option value="delayed" @selected(($f['delay'] ?? '') === 'delayed')>Delayed</option>
                <option value="on_time" @selected(($f['delay'] ?? '') === 'on_time')>On time</option>
            </select></div>
        <div><label class="label" for="from">Departs from</label><input id="from" type="date" name="from" value="{{ $f['from'] ?? '' }}" class="input"></div>
        <div><label class="label" for="to">Departs to</label><input id="to" type="date" name="to" value="{{ $f['to'] ?? '' }}" class="input"></div>
        <div class="col-span-2 flex items-end gap-2 md:col-span-2 xl:col-span-2">
            <button class="btn-navy flex-1">Apply</button>
            <a href="{{ route('shipments.index') }}" class="btn-ghost">Reset</a>
        </div>
    </form>
    @can('create', \App\Models\Shipment::class)
        <a href="{{ route('shipments.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> New shipment</a>
    @endcan
</div>

<div class="card overflow-hidden p-0">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50">
                <tr>
                    <th class="th"><a href="{{ $sortUrl('shipment_number') }}">Shipment {{ $arrow('shipment_number') }}</a></th>
                    @unless (auth()->user()->isCustomer())<th class="th">Customer</th>@endunless
                    <th class="th">Carrier</th>
                    <th class="th">Route</th>
                    <th class="th"><a href="{{ $sortUrl('expected_arrival_at') }}">Expected arrival {{ $arrow('expected_arrival_at') }}</a></th>
                    <th class="th"><a href="{{ $sortUrl('status') }}">Status {{ $arrow('status') }}</a></th>
                    <th class="th"><a href="{{ $sortUrl('delay_hours') }}">Delay {{ $arrow('delay_hours') }}</a></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($shipments as $s)
                <tr class="hover:bg-slate-50">
                    <td class="td"><a href="{{ route('shipments.show', $s) }}" class="font-semibold text-cyan-300 hover:underline">{{ $s->shipment_number }}</a><p class="text-xs text-slate-400">{{ $s->cargo_type }}</p></td>
                    @unless (auth()->user()->isCustomer())<td class="td">{{ $s->customer->name }}</td>@endunless
                    <td class="td">{{ $s->carrier->name }}</td>
                    <td class="td whitespace-nowrap">{{ $s->origin->city }} <span class="text-slate-400">→</span> {{ $s->destination->city }}</td>
                    <td class="td whitespace-nowrap">{{ ($s->estimated_arrival_at ?? $s->expected_arrival_at)->format('d M Y H:i') }}</td>
                    <td class="td"><x-status-badge :status="$s->status" /></td>
                    <td class="td">@if ($s->is_delayed)<x-delay-badge :shipment="$s" />@else<span class="text-xs text-slate-400">On time</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-16 text-center">
                    <p class="text-sm font-semibold text-slate-700">No shipments found</p>
                    <p class="mt-1 text-sm text-slate-500">Try removing some filters.</p>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($shipments->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $shipments->links() }}</div>@endif
</div>
@endsection
