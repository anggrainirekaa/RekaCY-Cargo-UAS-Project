@extends('layouts.app')
@section('title', auth()->user()->isStaff() ? 'Global Tracking' : 'Live Map')

@section('content')
@include('partials.map-assets')
<div class="grid gap-3 lg:grid-cols-[20rem_minmax(0,1fr)]">
    <section class="card order-2 flex max-h-[38rem] flex-col !p-0 lg:order-1">
        <div class="border-b border-line p-3">
            <div class="flex items-center justify-between"><h2 class="card-title">Routes ({{ count($map['routes']) }})</h2> @include('partials.demo-label')</div>
            <input id="routeSearch" value="{{ $q }}" placeholder="Filter by number, route, carrier…" class="input mt-2 py-1.5" aria-label="Filter routes">
            <div class="mt-2 flex flex-wrap gap-1 text-[11px]" id="stateFilters">
                @foreach (['all' => 'All', 'normal' => 'Normal', 'attention' => 'Attention', 'critical' => 'Critical', 'completed' => 'Delivered'] as $k => $l)
                    <button type="button" data-f="{{ $k }}" class="rounded-md px-2 py-1 font-semibold ring-1 {{ $k === 'all' ? 'bg-cyan-400/15 text-cyan-300 ring-cyan-400/40' : 'bg-deep text-slate-500 ring-line' }}">{{ $l }}</button>
                @endforeach
            </div>
        </div>
        <ul id="routeList" class="flex-1 divide-y divide-line overflow-y-auto"></ul>
    </section>
    <section class="card order-1 !p-0 lg:order-2"><div class="relative h-[36rem]"><div id="gmap" class="absolute inset-0"></div></div></section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const M = @json($map);
    const ctl = rkMap('gmap', M, { mode: 'full' });
    const list = document.getElementById('routeList'), search = document.getElementById('routeSearch');
    const dot = { normal: '#22d3ee', attention: '#f5be42', critical: '#ef4444', completed: '#22c55e' };
    let state = 'all';
    const draw = () => {
        const q = search.value.trim().toLowerCase(); list.replaceChildren();
        const rows = M.routes.filter(r => (state === 'all' || r.state === state) && (!q || (r.number + ' ' + r.route + ' ' + r.carrier).toLowerCase().includes(q)));
        if (!rows.length) { const li = document.createElement('li'); li.className = 'p-6 text-center text-sm text-slate-500'; li.textContent = 'No routes match.'; list.appendChild(li); return; }
        rows.forEach(r => {
            const li = document.createElement('li'); li.className = 'cursor-pointer px-3 py-2 hover:bg-white/5';
            const a = document.createElement('div'); a.className = 'flex items-center gap-2 text-sm font-semibold text-slate-900';
            const s = document.createElement('span'); s.className = 'h-2 w-2 rounded-full'; s.style.background = dot[r.state];
            a.append(s, document.createTextNode(r.number));
            const b = document.createElement('p'); b.className = 'text-xs text-slate-500'; b.textContent = r.route + ' · ' + r.carrier + ' · ' + r.status;
            li.append(a, b); li.addEventListener('click', () => ctl.focus(r.id)); list.appendChild(li);
        });
    };
    search.addEventListener('input', draw);
    document.querySelectorAll('#stateFilters button').forEach(b => b.addEventListener('click', () => {
        state = b.dataset.f; ctl.setFilter(state); draw();
        document.querySelectorAll('#stateFilters button').forEach(x => x.className = 'rounded-md px-2 py-1 font-semibold ring-1 ' + (x === b ? 'bg-cyan-400/15 text-cyan-300 ring-cyan-400/40' : 'bg-deep text-slate-500 ring-line'));
    }));
    draw();
})();
</script>
@endpush
