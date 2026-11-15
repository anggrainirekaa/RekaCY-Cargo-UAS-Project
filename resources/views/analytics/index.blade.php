@extends('layouts.app')
@section('title', 'Carrier Analytics')

@section('content')
<div class="grid gap-3 lg:grid-cols-2">
    <section class="card"><h2 class="card-title">Carrier performance</h2><p class="card-sub mb-2">Delivery performance, delay rate, exception rate (%)</p><div class="h-72"><canvas id="carrierChart"></canvas></div></section>
    <section class="card"><h2 class="card-title">Weekly shipment volume</h2><p class="card-sub mb-2">Created per week · 8 weeks</p><div class="h-72"><canvas id="volumeChart"></canvas></div></section>
</div>
<section class="card mt-3 !p-0"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-line">
    <thead class="bg-slate-50"><tr><th class="th">Carrier</th><th class="th">Shipments</th><th class="th">Delivered</th><th class="th">On-time rate</th><th class="th">Delay rate</th><th class="th">Exceptions</th><th class="th">Exception rate</th></tr></thead>
    <tbody class="divide-y divide-line">
    @forelse ($carriers as $c)
        <tr class="hover:bg-slate-50"><td class="td font-semibold text-slate-900">{{ $c['carrier'] }}</td><td class="td">{{ $c['total'] }}</td><td class="td">{{ $c['delivered'] }}</td>
            <td class="td text-emerald-300">{{ $c['on_time_rate'] === null ? '—' : $c['on_time_rate'].'%' }}</td><td class="td text-gold">{{ $c['delay_rate'] }}%</td><td class="td">{{ $c['exceptions'] }}</td><td class="td text-red-300">{{ $c['exception_rate'] }}%</td></tr>
    @empty<tr><td colspan="7" class="py-12 text-center text-sm text-slate-500">No data yet.</td></tr>@endforelse
    </tbody></table></div></section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(() => {
    const C = @json($carriers), V = @json($d['weekly_volume']);
    Chart.defaults.font.family = 'Inter, ui-sans-serif'; Chart.defaults.color = '#94a3b8';
    const grid = { color: 'rgba(38,55,77,.6)' };
    new Chart(document.getElementById('carrierChart'), { type: 'bar',
        data: { labels: C.map(c => c.carrier), datasets: [
            { label: 'On-time %', data: C.map(c => c.on_time_rate ?? 0), backgroundColor: '#22c55e' },
            { label: 'Delay %', data: C.map(c => c.delay_rate), backgroundColor: '#f5be42' },
            { label: 'Exception %', data: C.map(c => c.exception_rate), backgroundColor: '#ef4444' } ] },
        options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: 100, grid }, x: { grid: { display: false } } } } });
    new Chart(document.getElementById('volumeChart'), { type: 'line',
        data: { labels: V.map(v => v.label), datasets: [{ data: V.map(v => v.count), borderColor: '#22d3ee', backgroundColor: 'rgba(34,211,238,.12)', fill: true, tension: .35 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid }, x: { grid: { display: false } } } } });
})();
</script>
@endpush
