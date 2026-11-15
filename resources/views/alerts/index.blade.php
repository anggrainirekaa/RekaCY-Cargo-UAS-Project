@extends('layouts.app')
@section('title', 'Alerts')

@section('content')
<form method="GET" class="card mb-3 flex flex-wrap items-end gap-3">
    <div><label class="label" for="level">Level</label><select id="level" name="level" class="input"><option value="">All</option>@foreach (['critical', 'warning', 'info'] as $l)<option value="{{ $l }}" @selected(($filters['level'] ?? '') === $l)>{{ ucfirst($l) }}</option>@endforeach</select></div>
    <div><label class="label" for="kind">Type</label><select id="kind" name="kind" class="input"><option value="">All</option><option value="exception" @selected(($filters['kind'] ?? '') === 'exception')>Exceptions</option><option value="delay" @selected(($filters['kind'] ?? '') === 'delay')>Delays</option></select></div>
    <button class="btn-navy">Filter</button><a href="{{ route('alerts.index') }}" class="btn-ghost">Reset</a>
    <p class="ml-auto text-xs text-slate-500">Alerts are derived live from open exceptions and delayed active shipments.</p>
</form>
<div class="card divide-y divide-line !p-0">
    @forelse ($alerts as $a)
        <a href="{{ $a['url'] }}" class="flex items-start gap-3 px-4 py-3 hover:bg-white/5">
            <span class="badge mt-0.5 {{ \App\Services\AlertFeed::levelClasses($a['level']) }}">{{ strtoupper($a['level']) }}</span>
            <div class="min-w-0 flex-1"><p class="text-sm font-semibold text-slate-900">{{ $a['title'] }} <span class="font-normal text-slate-500">· {{ $a['shipment_number'] }}</span></p><p class="truncate text-sm text-slate-600">{{ $a['text'] }}</p></div>
            <span class="shrink-0 text-xs text-slate-500">{{ $a['at']->diffForHumans() }}</span>
        </a>
    @empty<p class="py-16 text-center text-sm text-slate-500">No alerts — everything is on track.</p>@endforelse
</div>
<div class="mt-3">{{ $alerts->links() }}</div>
@endsection
