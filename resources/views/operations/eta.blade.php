@extends('layouts.app')
@section('title', 'ETA Monitoring')

@section('content')
<div class="card mb-3 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-600">Delay = (current ETA, or now if past due) − expected arrival. <b class="text-gold">LOW</b> ≤ {{ config('rekacy.delay.low_max_hours') + 0 }} h · <b class="text-orange-300">MEDIUM</b> ≤ {{ config('rekacy.delay.medium_max_hours') + 0 }} h · <b class="text-red-300">HIGH</b> above. Thresholds come from <code>.env</code>.</p>
    <form method="GET" class="flex items-center gap-2">
        <select name="severity" class="input w-40 py-1.5" aria-label="Filter by delay"><option value="">All active</option>@foreach (['ON_TIME' => 'On time', 'LOW' => 'LOW', 'MEDIUM' => 'MEDIUM', 'HIGH' => 'HIGH'] as $k => $l)<option value="{{ $k }}" @selected(($filters['severity'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
        <button class="btn-navy py-1.5">Filter</button>
    </form>
</div>
<div class="card !p-0 overflow-hidden"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-line">
    <thead class="bg-slate-50"><tr><th class="th">Shipment</th><th class="th">Carrier</th><th class="th">Route</th><th class="th">Expected arrival</th><th class="th">Current ETA</th><th class="th">Delay</th><th class="th">Status</th></tr></thead>
    <tbody class="divide-y divide-line">
    @forelse ($rows as $s)
        <tr class="hover:bg-slate-50">
            <td class="td"><a href="{{ route('shipments.show', $s) }}" class="link">{{ $s->shipment_number }}</a></td>
            <td class="td">{{ $s->carrier->name }}</td>
            <td class="td whitespace-nowrap">{{ $s->origin->city }} → {{ $s->destination->city }}</td>
            <td class="td whitespace-nowrap">{{ $s->expected_arrival_at->format('d M Y H:i') }}</td>
            <td class="td whitespace-nowrap">{{ $s->estimated_arrival_at?->format('d M Y H:i') ?? '—' }}</td>
            <td class="td">@if ($s->is_delayed)<x-delay-badge :shipment="$s" />@else<span class="text-xs text-emerald-300">On time</span>@endif</td>
            <td class="td"><x-status-badge :status="$s->status" /></td>
        </tr>
    @empty<tr><td colspan="7" class="py-14 text-center text-sm text-slate-500">No shipments match.</td></tr>@endforelse
    </tbody></table></div>
    @if ($rows->hasPages())<div class="border-t border-line px-4 py-3">{{ $rows->links() }}</div>@endif
</div>
@endsection
