@extends('layouts.app')
@section('title', 'Exception Management')

@section('content')
@include('partials.map-assets')
<div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
    <x-kpi label="Total exceptions" :value="$kpi['total']" tone="gold" icon="alert" />
    <x-kpi label="Critical" :value="$kpi['critical']" tone="red" icon="shield" sub="open · HIGH severity" />
    <x-kpi label="High priority" :value="$kpi['high_priority']" tone="gold" icon="bell" sub="open · HIGH + MEDIUM" />
    <x-kpi label="Investigating" :value="$kpi['investigating']" icon="radar" />
    <x-kpi label="Resolved" :value="$kpi['resolved']" tone="green" icon="check" />
</div>

<div class="mt-3 grid gap-3 xl:grid-cols-[minmax(0,1fr)_20rem]">
    <div class="space-y-3">
        <section class="card !p-0">
            <div class="flex items-center justify-between border-b border-line px-4 py-3"><h2 class="card-title">Exception map</h2> @include('partials.demo-label')</div>
            <div class="relative h-[24rem]"><div id="emap" class="absolute inset-0"></div></div>
        </section>
        <section class="card"><h2 class="card-title">Exception activity</h2><p class="card-sub mb-2">Detected per day by severity · 30 days</p><div class="h-44"><canvas id="excChart"></canvas></div></section>
    </div>
    <div class="space-y-3">
        <section class="card"><h2 class="card-title">High-priority exceptions</h2>
            <ul class="mt-2 divide-y divide-line">
                @forelse ($priority as $e)
                    <li><a href="{{ route('exceptions.show', $e) }}" class="block py-2"><div class="flex items-center justify-between gap-2"><span class="text-[13px] font-semibold text-slate-900">{{ $e->type->label() }}</span><span class="badge {{ $e->severity->badgeClasses() }}">{{ $e->severity->value }}</span></div><p class="truncate text-[11px] text-slate-500">{{ $e->shipment->shipment_number }} · {{ $e->assignee?->name ?? 'Unassigned' }}</p></a></li>
                @empty<li class="py-5 text-center text-xs text-slate-500">No open exceptions.</li>@endforelse
            </ul></section>
        <section class="card"><h2 class="card-title">Recent alerts</h2>
            <ul class="mt-2 space-y-1.5">
                @forelse ($alerts as $a)<li><a href="{{ $a['url'] }}" class="flex items-center gap-2 text-[12px] hover:text-cyan-300"><span class="badge {{ \App\Services\AlertFeed::levelClasses($a['level']) }}">{{ strtoupper(substr($a['level'], 0, 4)) }}</span><span class="truncate">{{ $a['title'] }} · {{ $a['shipment_number'] }}</span></a></li>
                @empty<li class="text-xs text-slate-500">No alerts.</li>@endforelse
            </ul></section>
        <section class="card"><h2 class="card-title">Risk indicators</h2>
            <div class="mt-2 flex flex-wrap gap-1.5">@foreach ($risk['distribution'] as $lvl => $n)@if ($lvl !== 'NORMAL')<span class="badge {{ \App\Services\RiskService::levelClasses($lvl) }}">{{ $lvl }} {{ $n }}</span>@endif @endforeach</div>
            <ul class="mt-2 space-y-1 text-[12px]">@forelse ($risk['top'] as $r)<li class="flex justify-between"><a href="{{ route('shipments.show', $r['shipment']) }}" class="hover:text-cyan-300">{{ $r['shipment']->shipment_number }}</a><span class="badge {{ \App\Services\RiskService::levelClasses($r['level']) }}">score {{ $r['score'] }}</span></li>@empty<li class="text-slate-500">No shipment at risk.</li>@endforelse</ul>
            <p class="mt-2 text-[10px] leading-relaxed text-slate-500">Score = delay (1–3) + each open exception (1–3) + urgent priority (1).</p></section>
    </div>
</div>

<section class="card mt-3 !p-0">
    <form method="GET" class="grid gap-3 border-b border-line p-4 sm:grid-cols-4 xl:grid-cols-8" role="search">
        <div><label class="label" for="f_status">Status</label><select id="f_status" name="status" class="input"><option value="">All</option>@foreach (\App\Enums\ExceptionStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->value }}</option>@endforeach</select></div>
        <div><label class="label" for="f_sev">Severity</label><select id="f_sev" name="severity" class="input"><option value="">All</option>@foreach (\App\Enums\Severity::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['severity'] ?? '') === $s->value)>{{ $s->value }}</option>@endforeach</select></div>
        <div><label class="label" for="f_type">Type</label><select id="f_type" name="type" class="input"><option value="">All</option>@foreach (\App\Enums\ExceptionType::cases() as $t)<option value="{{ $t->value }}" @selected(($filters['type'] ?? '') === $t->value)>{{ $t->label() }}</option>@endforeach</select></div>
        <div><label class="label" for="f_car">Carrier</label><select id="f_car" name="carrier_id" class="input"><option value="">All</option>@foreach ($carriers as $c)<option value="{{ $c->id }}" @selected(($filters['carrier_id'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div><label class="label" for="f_loc">Location</label><select id="f_loc" name="location_id" class="input"><option value="">Any</option>@foreach ($locations as $l)<option value="{{ $l->id }}" @selected(($filters['location_id'] ?? '') == $l->id)>{{ $l->city }}</option>@endforeach</select></div>
        <div><label class="label" for="f_op">Assignee</label><select id="f_op" name="assigned_to" class="input"><option value="">Anyone</option>@foreach ($operators as $o)<option value="{{ $o->id }}" @selected(($filters['assigned_to'] ?? '') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
        <div><label class="label" for="f_from">Detected from</label><input id="f_from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input"></div>
        <div><label class="label" for="f_to">to</label><input id="f_to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input"></div>
        <div class="flex items-end gap-2 sm:col-span-4 xl:col-span-8"><button class="btn-navy">Apply filters</button><a href="{{ route('exceptions.index') }}" class="btn-ghost">Reset</a></div>
    </form>
    <div class="overflow-x-auto"><table class="min-w-full divide-y divide-line">
        <thead class="bg-slate-50"><tr><th class="th">Shipment</th><th class="th">Type</th><th class="th">Severity</th><th class="th">Status</th><th class="th">Carrier</th><th class="th">Assignee</th><th class="th">Detected</th><th class="th">Resolved</th></tr></thead>
        <tbody class="divide-y divide-line">
        @forelse ($list as $ex)
            <tr class="hover:bg-slate-50">
                <td class="td"><a class="link" href="{{ route('exceptions.show', $ex) }}">{{ $ex->shipment->shipment_number }}</a></td>
                <td class="td">{{ $ex->type->label() }}</td>
                <td class="td"><span class="badge {{ $ex->severity->badgeClasses() }}">{{ $ex->severity->value }}</span></td>
                <td class="td"><span class="badge {{ $ex->status->badgeClasses() }}">{{ $ex->status->value }}</span></td>
                <td class="td">{{ $ex->shipment->carrier->name }}</td>
                <td class="td">{{ $ex->assignee?->name ?? '—' }}</td>
                <td class="td whitespace-nowrap">{{ $ex->detected_at->format('d M Y H:i') }}</td>
                <td class="td whitespace-nowrap">{{ $ex->resolved_at?->format('d M Y H:i') ?? '—' }}</td>
            </tr>
        @empty<tr><td colspan="8" class="py-14 text-center text-sm text-slate-500">No exceptions match these filters.</td></tr>@endforelse
        </tbody></table></div>
    @if ($list->hasPages())<div class="border-t border-line px-4 py-3">{{ $list->links() }}</div>@endif
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(() => {
    const C = @json($chart);
    Chart.defaults.font.family = 'Inter, ui-sans-serif'; Chart.defaults.color = '#94a3b8';
    new Chart(document.getElementById('excChart'), {
        type: 'bar',
        data: { labels: C.map(c => c.label), datasets: [
            { label: 'HIGH', data: C.map(c => c.high), backgroundColor: '#ef4444' },
            { label: 'MEDIUM', data: C.map(c => c.medium), backgroundColor: '#f5be42' },
            { label: 'LOW', data: C.map(c => c.low), backgroundColor: '#22d3ee' },
        ] },
        options: { maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 10, font: { size: 10 } } } },
            scales: { x: { stacked: true, ticks: { maxTicksLimit: 8, font: { size: 9 } }, grid: { display: false } }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(38,55,77,.6)' } } } }
    });
    rkMap('emap', @json($map), { mode: 'exceptions' });
})();
</script>
@endpush
