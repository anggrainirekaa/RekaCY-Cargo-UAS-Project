@extends('layouts.app')
@section('title', 'Command Center')

@section('content')
@include('partials.map-assets')
@php
    $iconTone = ['critical' => 'text-red-300', 'warning' => 'text-gold', 'info' => 'text-cyan-300'];
    $sevBadge = fn ($s) => \App\Enums\Severity::from($s)->badgeClasses();
    $ind = $d['indicators'];
@endphp

{{-- KPI strip --}}
<div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
    @foreach ($d['kpis'] as $k)
        <x-kpi :data-kpi="$k['key']" :label="$k['label']" :value="$k['value']" :tone="$k['tone']" :icon="$k['icon']" :trend="$k['trend']" />
    @endforeach
</div>


{{-- Live operations (updated through WebSocket, or polling when offline) --}}
<section class="card mt-3 !p-3">
    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <h2 class="card-title">Live operations</h2>
        <span id="liveStamp" class="text-[11px] text-slate-500">snapshot from database</span>
    </div>
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
        @foreach ([['waiting', 'Waiting', 'text-slate-800'], ['processing', 'Picked up', 'text-sky-300'], ['in_transit', 'In transit', 'text-cyan-300'], ['delivered', 'Delivered', 'text-emerald-300'], ['failed', 'Failed delivery', 'text-red-300'], ['couriers_live', 'Couriers live GPS', 'text-gold'], ['couriers_available', 'Couriers available', 'text-slate-800']] as [$key, $label, $tone])
            <div class="rounded-lg bg-deep p-2.5 ring-1 ring-line" data-live="{{ $key }}"><p data-val class="text-xl font-extrabold {{ $tone }}">{{ $d['live'][$key] }}</p><p class="text-[10px] uppercase tracking-wider text-slate-500">{{ $label }}</p></div>
        @endforeach
    </div>
    <ul id="liveFeed" class="mt-2 space-y-1 text-[11px] text-slate-500" aria-live="polite"></ul>
</section>

{{-- Command grid: left monitors | global map | right alerts --}}
<div class="mt-3 grid gap-3 xl:grid-cols-[17rem_minmax(0,1fr)_19rem] 2xl:grid-cols-[18rem_minmax(0,1fr)_20rem]">

    {{-- LEFT --}}
    <div class="order-2 space-y-3 xl:order-1">
        <section class="card">
            <h2 class="card-title">Shipment activity</h2><p class="card-sub mb-2">Created vs. exceptions · 14 days</p>
            <div class="h-32"><canvas id="activityChart"></canvas></div>
        </section>
        <section class="card">
            <h2 class="card-title">Status distribution</h2>
            <div class="mt-2 flex items-center gap-3">
                <div class="h-24 w-24 shrink-0"><canvas id="statusChart"></canvas></div>
                <ul class="min-w-0 flex-1 space-y-1 text-[11px]">
                    @foreach ($d['by_status'] as $s)
                        <li class="flex items-center justify-between gap-2"><span class="flex items-center gap-1.5 truncate"><span class="h-2 w-2 rounded-sm" style="background: {{ $s['color'] }}"></span>{{ $s['label'] }}</span><b class="text-slate-900">{{ $s['count'] }}</b></li>
                    @endforeach
                </ul>
            </div>
        </section>
        <section class="card">
            <h2 class="card-title">Regional summary</h2>
            @foreach (['destination' => 'Destination', 'origin' => 'Origin'] as $key => $title)
                @php $rows = $d['regions'][$key]; $max = max(1, collect($rows)->max('count')); @endphp
                <p class="mb-1 mt-2.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ $title }} country</p>
                @forelse ($rows as $r)
                    <div class="mb-1.5">
                        <div class="flex justify-between text-[11px]"><span class="truncate">{{ $r['country'] }}</span><b class="text-slate-900">{{ $r['count'] }}</b></div>
                        <div class="mt-0.5 h-1 rounded bg-deep"><div class="h-1 rounded bg-gradient-to-r from-cyan-400 to-electric" style="width: {{ $r['count'] / $max * 100 }}%"></div></div>
                    </div>
                @empty<p class="text-xs text-slate-500">No data.</p>@endforelse
            @endforeach
        </section>
        <section class="card">
            <h2 class="card-title">Carrier statistics</h2><p class="card-sub mb-2">On-time · delay · exception rate</p>
            <div class="space-y-2.5">
                @forelse ($d['carriers'] as $c)
                    <div>
                        <div class="flex items-center justify-between text-[11px]"><span class="truncate font-medium text-slate-800">{{ $c['carrier'] }}</span><span class="text-slate-500">{{ $c['total'] }} ship.</span></div>
                        <div class="mt-1 grid grid-cols-3 gap-1 text-center text-[10px]">
                            <span class="rounded bg-emerald-400/10 py-0.5 text-emerald-300">{{ $c['on_time_rate'] === null ? '—' : $c['on_time_rate'].'%' }} on-time</span>
                            <span class="rounded bg-gold/10 py-0.5 text-gold">{{ $c['delay_rate'] }}% delay</span>
                            <span class="rounded bg-red-500/10 py-0.5 text-red-300">{{ $c['exception_rate'] }}% exc.</span>
                        </div>
                    </div>
                @empty<p class="text-xs text-slate-500">No carriers yet.</p>@endforelse
            </div>
        </section>
        <section class="card">
            <h2 class="card-title">Operational indicators</h2>
            <dl class="mt-2 grid grid-cols-2 gap-2 text-center">
                @foreach ([
                    ['On-time', $ind['on_time_rate'] === null ? '—' : $ind['on_time_rate'].'%'],
                    ['Avg delay', $ind['avg_delay_hours'].' h'],
                    ['Exception rate', $ind['exception_rate'].'%'],
                    ['Avg resolution', $ind['avg_resolution_hours'] === null ? '—' : $ind['avg_resolution_hours'].' h'],
                ] as [$l, $v])
                    <div class="rounded-lg bg-deep p-2 ring-1 ring-line"><dd class="text-lg font-extrabold text-cyan-300">{{ $v }}</dd><dt class="text-[10px] uppercase tracking-wider text-slate-500">{{ $l }}</dt></div>
                @endforeach
            </dl>
        </section>
    </div>

    {{-- CENTER: global map --}}
    <section class="card order-1 flex min-h-[34rem] flex-col !p-0 xl:order-2">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
            <div class="flex items-center gap-2"><h2 class="card-title">Global shipment map</h2> @include('partials.demo-label')</div>
            <div class="flex flex-wrap items-center gap-1.5 text-[11px]" id="mapFilters">
                @foreach (['all' => 'All', 'normal' => 'Normal', 'attention' => 'Attention', 'critical' => 'Critical', 'completed' => 'Delivered'] as $k => $l)
                    <button type="button" data-f="{{ $k }}" class="rounded-md px-2 py-1 font-semibold ring-1 ring-line transition {{ $k === 'all' ? 'bg-cyan-400/15 text-cyan-300 ring-cyan-400/40' : 'bg-deep text-slate-500 hover:text-slate-900' }}">{{ $l }}</button>
                @endforeach
                <select id="carrierFilter" class="rounded-md border-0 bg-deep py-1 pl-2 pr-7 text-[11px] text-slate-700 ring-1 ring-line focus:ring-cyan-400" aria-label="Filter by carrier">
                    <option value="">All carriers</option>
                    @foreach (collect($d['map']['routes'])->unique('carrier_id') as $r)<option value="{{ $r['carrier_id'] }}">{{ $r['carrier'] }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="relative min-h-[28rem] flex-1">
            <div id="gmap" class="absolute inset-0 h-full w-full" role="img" aria-label="World map of shipment routes"></div>
            <div id="mapLoading" class="skeleton absolute inset-0 flex items-center justify-center text-sm text-slate-500">Loading map…</div>
            @if (! count($d['map']['routes']))
                <p class="absolute left-1/2 top-1/2 z-[500] -translate-x-1/2 -translate-y-1/2 rounded-lg border border-line bg-panel/90 px-4 py-3 text-sm text-slate-500">No shipments to plot yet.</p>
            @endif
        </div>
        <div class="grid grid-cols-4 divide-x divide-line border-t border-line text-center text-[11px]">
            @php $cnt = collect($d['map']['routes'])->countBy('state'); @endphp
            <div class="py-2"><b class="text-cyan-300">{{ $cnt['normal'] ?? 0 }}</b> <span class="text-slate-500">normal</span></div>
            <div class="py-2"><b class="text-gold">{{ $cnt['attention'] ?? 0 }}</b> <span class="text-slate-500">attention</span></div>
            <div class="py-2"><b class="text-red-400">{{ $cnt['critical'] ?? 0 }}</b> <span class="text-slate-500">critical</span></div>
            <div class="py-2"><b class="text-emerald-300">{{ $cnt['completed'] ?? 0 }}</b> <span class="text-slate-500">delivered</span></div>
        </div>
    </section>

    {{-- RIGHT --}}
    <div class="order-3 space-y-3">
        <section class="card">
            <div class="flex items-center justify-between"><h2 class="card-title">Critical alerts</h2><a href="{{ route('alerts.index') }}" class="text-[11px] link">All</a></div>
            <ul class="mt-2 space-y-1.5">
                @forelse ($d['critical_alerts'] as $a)
                    <li><a href="{{ $a['url'] }}" class="flex gap-2 rounded-lg bg-red-500/5 p-2 ring-1 ring-red-400/20 hover:bg-red-500/10">
                        <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0 text-red-400" />
                        <span class="min-w-0"><span class="block truncate text-[12px] font-semibold text-slate-900">{{ $a['title'] }}</span><span class="block truncate text-[11px] text-slate-500">{{ $a['shipment_number'] }} · {{ $a['at']->diffForHumans() }}</span></span>
                    </a></li>
                @empty<li class="py-3 text-center text-xs text-slate-500">No critical alerts.</li>@endforelse
            </ul>
        </section>
        <section class="card">
            <div class="flex items-center justify-between"><h2 class="card-title">High-priority exceptions</h2><a href="{{ route('exceptions.index') }}" class="text-[11px] link">Center</a></div>
            <ul class="mt-2 divide-y divide-line">
                @forelse ($d['priority_exceptions'] as $e)
                    <li class="py-2"><a href="{{ route('exceptions.show', $e) }}" class="block hover:opacity-90">
                        <div class="flex items-center justify-between gap-2"><span class="truncate text-[12px] font-semibold text-slate-900">{{ $e->type->label() }}</span><span class="badge {{ $e->severity->badgeClasses() }}">{{ $e->severity->value }}</span></div>
                        <p class="truncate text-[11px] text-slate-500">{{ $e->shipment->shipment_number }} · {{ $e->assignee?->name ?? 'Unassigned' }}</p>
                    </a></li>
                @empty<li class="py-3 text-center text-xs text-slate-500">No open exceptions.</li>@endforelse
            </ul>
        </section>
        <section class="card">
            <h2 class="card-title">Latest tracking events</h2>
            <ol class="mt-2 space-y-2">
                @forelse ($d['latest_events'] as $e)
                    <li class="flex gap-2"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $e->is_exception ? 'bg-gold' : 'bg-cyan-400' }}"></span>
                        <div class="min-w-0 text-[11px]"><a href="{{ route('shipments.show', $e->shipment_id) }}" class="font-semibold text-slate-900 hover:text-cyan-300">{{ $e->shipment->shipment_number }}</a>
                        <p class="truncate text-slate-500">{{ $e->status?->label() ?? str_replace('_', ' ', $e->event_type) }}{{ $e->location_name ? ' · '.$e->location_name : '' }}</p>
                        <p class="text-slate-400">{{ $e->occurred_at->diffForHumans() }}</p></div></li>
                @empty<li class="text-xs text-slate-500">No events.</li>@endforelse
            </ol>
        </section>
        <section class="card">
            <h2 class="card-title">Recent activities</h2>
            <ul class="mt-2 space-y-1.5 text-[11px]">
                @forelse ($d['audit'] as $a)
                    <li class="flex justify-between gap-2"><span class="truncate"><code class="text-cyan-300">{{ $a->action }}</code> <span class="text-slate-500">{{ $a->user?->name ?? 'System' }}</span></span><span class="shrink-0 text-slate-400">{{ $a->created_at->diffForHumans(null, true) }}</span></li>
                @empty<li class="text-slate-500">No activity yet.</li>@endforelse
            </ul>
        </section>
    </div>
</div>

{{-- Bottom monitoring cards --}}
<div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
    <section class="card">
        <div class="flex items-center gap-2"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gold/10 text-gold"><x-icon name="bell" class="h-4 w-4" /></span><h2 class="card-title">Alerts</h2></div>
        @php $al = $d['alerts']; @endphp
        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-lg bg-red-500/10 py-1.5"><b class="text-lg text-red-300">{{ $al->where('level', 'critical')->count() }}</b><p class="text-[10px] uppercase text-slate-500">Critical</p></div>
            <div class="rounded-lg bg-gold/10 py-1.5"><b class="text-lg text-gold">{{ $al->where('level', 'warning')->count() }}</b><p class="text-[10px] uppercase text-slate-500">Warning</p></div>
            <div class="rounded-lg bg-cyan-400/10 py-1.5"><b class="text-lg text-cyan-300">{{ $al->where('level', 'info')->count() }}</b><p class="text-[10px] uppercase text-slate-500">Info</p></div>
        </div>
        <p class="mt-2 text-[11px] text-slate-500">{{ $d['unread'] }} unread notification(s) for you.</p>
    </section>
    <section class="card">
        <div class="flex items-center gap-2"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gold/10 text-gold"><x-icon name="route" class="h-4 w-4" /></span><h2 class="card-title">Route deviations</h2></div>
        <p class="mt-2 text-[11px] text-slate-500">Open <b>MISROUTED</b> exceptions.</p>
        <ul class="mt-2 space-y-1.5 text-[12px]">
            @forelse ($d['deviations'] as $x)
                <li><a href="{{ route('exceptions.show', $x) }}" class="flex justify-between gap-2 hover:text-cyan-300"><span>{{ $x->shipment->shipment_number }}</span><span class="text-slate-500">{{ $x->detected_at->diffForHumans(null, true) }}</span></a></li>
            @empty<li class="text-slate-500">No route deviations reported.</li>@endforelse
        </ul>
    </section>
    <section class="card">
        <div class="flex items-center gap-2"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-cyan-400/10 text-cyan-300"><x-icon name="clock" class="h-4 w-4" /></span><h2 class="card-title">Delay monitoring</h2></div>
        @php $dm = $d['delay_monitor']; $dmax = max(1, $dm['low'], $dm['medium'], $dm['high']); @endphp
        <div class="mt-3 space-y-1.5 text-[11px]">
            @foreach (['low' => ['LOW', 'bg-gold/70'], 'medium' => ['MEDIUM', 'bg-orange-400'], 'high' => ['HIGH', 'bg-red-500']] as $k => [$l, $c])
                <div class="flex items-center gap-2"><span class="w-14 text-slate-500">{{ $l }}</span><div class="h-1.5 flex-1 rounded bg-deep"><div class="h-1.5 rounded {{ $c }}" style="width: {{ $dm[$k] / $dmax * 100 }}%"></div></div><b class="w-5 text-right text-slate-900">{{ $dm[$k] }}</b></div>
            @endforeach
        </div>
        <p class="mt-2 text-[11px] text-slate-500">{{ $dm['escalated'] }} escalated (≥ {{ config('rekacy.delay.escalate_hours') + 0 }} h) · <a href="{{ route('operations.eta') }}" class="link">ETA monitor</a></p>
    </section>
    <section class="card">
        <div class="flex items-center gap-2"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-500/10 text-red-400"><x-icon name="shield" class="h-4 w-4" /></span><h2 class="card-title">Risk indicators</h2></div>
        <div class="mt-3 flex flex-wrap gap-1.5">
            @foreach ($d['risk']['distribution'] as $lvl => $n)@if ($lvl !== 'NORMAL')<span class="badge {{ \App\Services\RiskService::levelClasses($lvl) }}">{{ $lvl }} {{ $n }}</span>@endif @endforeach
        </div>
        <ul class="mt-2 space-y-1 text-[12px]">
            @forelse ($d['risk']['top']->take(3) as $r)
                <li class="flex justify-between gap-2"><a href="{{ route('shipments.show', $r['shipment']) }}" class="hover:text-cyan-300">{{ $r['shipment']->shipment_number }}</a><span class="badge {{ \App\Services\RiskService::levelClasses($r['level']) }}">{{ $r['score'] }}</span></li>
            @empty<li class="text-slate-500">No shipment at risk.</li>@endforelse
        </ul>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(() => {
    const D = @json(['activity' => $d['activity'], 'status' => $d['by_status'], 'map' => $d['map']]);
    Chart.defaults.font.family = 'Inter, ui-sans-serif, system-ui'; Chart.defaults.color = '#94a3b8';
    const grid = { color: 'rgba(38,55,77,.6)' };

    new Chart(document.getElementById('activityChart'), {
        type: 'line',
        data: { labels: D.activity.map(a => a.label), datasets: [
            { label: 'Shipments', data: D.activity.map(a => a.shipments), borderColor: '#22d3ee', backgroundColor: 'rgba(34,211,238,.12)', fill: true, tension: .35, pointRadius: 0, borderWidth: 2 },
            { label: 'Exceptions', data: D.activity.map(a => a.exceptions), borderColor: '#f5be42', tension: .35, pointRadius: 0, borderWidth: 2 },
        ] },
        options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { display: false } },
            scales: { x: { ticks: { maxTicksLimit: 4, font: { size: 9 } }, grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0, font: { size: 9 }, maxTicksLimit: 4 }, grid } } }
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: { labels: D.status.map(s => s.label), datasets: [{ data: D.status.map(s => s.count), backgroundColor: D.status.map(s => s.color), borderWidth: 0 }] },
        options: { maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false } } }
    });

    const ctl = rkMap('gmap', D.map, { mode: 'full' });
    document.getElementById('mapLoading').remove();
    document.querySelectorAll('#mapFilters button').forEach(b => b.addEventListener('click', () => {
        ctl.setFilter(b.dataset.f);
        document.querySelectorAll('#mapFilters button').forEach(x => x.className = 'rounded-md px-2 py-1 font-semibold ring-1 transition ' + (x === b ? 'bg-cyan-400/15 text-cyan-300 ring-cyan-400/40' : 'bg-deep text-slate-500 ring-line hover:text-slate-900'));
    }));
    document.getElementById('carrierFilter').addEventListener('change', (e) => ctl.setCarrier(e.target.value));

    // ---------- Live updates ----------
    const fmt = (n) => Number(n).toLocaleString();
    const applyMetrics = (m) => {
        Object.entries(m).forEach(([k, v]) => {
            document.querySelectorAll(`[data-kpi="${k}"] [data-val], [data-live="${k}"] [data-val]`).forEach(el => { if (el.textContent !== fmt(v)) { el.textContent = fmt(v); el.animate([{ opacity: .3 }, { opacity: 1 }], { duration: 600 }); } });
        });
        document.getElementById('liveStamp').textContent = 'updated ' + new Date().toLocaleTimeString('en-GB');
    };
    const feed = (text) => {
        const ul = document.getElementById('liveFeed'), li = document.createElement('li');
        li.textContent = new Date().toLocaleTimeString('en-GB') + ' · ' + text; ul.prepend(li); while (ul.children.length > 4) ul.lastChild.remove();
    };
    const place = (c) => ctl.setLive(c.shipment_id, c.latitude ?? c.location?.latitude, c.longitude ?? c.location?.longitude, {
        number: c.shipment_number, courier: c.courier ?? c.name, source: (c.source ?? c.location?.source), at: c.recorded_at ?? c.location?.recorded_at,
    });
    const poll = async () => {
        if (window.rkState === 'live') return;
        try {
            const [m, c] = await Promise.all([fetch(@json(route('live.metrics')), { headers: { Accept: 'application/json' } }), fetch(@json(route('live.couriers')), { headers: { Accept: 'application/json' } })]);
            if (m.ok) applyMetrics(await m.json());
            if (c.ok) (await c.json()).data.forEach(place);
        } catch (e) { /* offline */ }
    };
    fetch(@json(route('live.couriers')), { headers: { Accept: 'application/json' } }).then(r => r.ok ? r.json() : { data: [] }).then(j => j.data.forEach(place)).catch(() => {});
    window.addEventListener('load', () => {
        if (!window.Echo) return;
        const ch = window.Echo.private('staff.live');
        ch.listen('.shipment.updated', (e) => { applyMetrics(e.metrics); feed(`${e.shipment.number} → ${e.message || e.shipment.status_label}`); });
        ch.listen('.courier.location', (e) => { place(e); });
    });
    setInterval(poll, 15000);
})();
</script>
@endpush
