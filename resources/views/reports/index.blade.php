@extends('layouts.app')
@section('title', 'Reports')

@section('content')
<form method="GET" class="card mb-3 grid gap-3 sm:grid-cols-5" role="search">
    <div><label class="label" for="status">Status</label><select id="status" name="status" class="input"><option value="">All</option>@foreach (\App\Enums\ShipmentStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
    <div><label class="label" for="carrier_id">Carrier</label><select id="carrier_id" name="carrier_id" class="input"><option value="">All</option>@foreach ($carriers as $c)<option value="{{ $c->id }}" @selected(($filters['carrier_id'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div><label class="label" for="from">Created from</label><input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input"></div>
    <div><label class="label" for="to">to</label><input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input"></div>
    <div class="flex items-end gap-2"><button class="btn-navy">Apply</button><a href="{{ route('reports.index') }}" class="btn-ghost">Reset</a></div>
</form>
<div class="mb-3 flex flex-wrap items-center justify-between gap-3">
    <div class="grid flex-1 grid-cols-2 gap-3 sm:grid-cols-6">
        @foreach ([['Shipments', $summary['total']], ['Delivered', $summary['delivered']], ['Delayed', $summary['delayed']], ['Exceptions', $summary['exceptions']], ['On-time', $summary['on_time_rate'] === null ? '—' : $summary['on_time_rate'].'%'], ['Avg delay', $summary['avg_delay'].' h']] as [$l, $v])
            <div class="card !p-3"><p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ $l }}</p><p class="text-xl font-extrabold text-cyan-300">{{ $v }}</p></div>
        @endforeach
    </div>
    <div class="flex gap-2">
        <a href="{{ route('reports.csv', $filters) }}" class="btn-primary">Export CSV</a>
        <a href="{{ route('reports.print', $filters) }}" target="_blank" class="btn-ghost">Print / PDF</a>
    </div>
</div>
@include('reports._table')
<div class="mt-3">{{ $rows->links() }}</div>
@endsection
