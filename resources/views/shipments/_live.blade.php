{{-- Live courier tracking: WebSocket (Reverb) with polling fallback. Shows nothing sensitive once the delivery is finished. --}}
<section class="card" id="livePanel">
    <div class="flex items-center justify-between"><h2 class="card-title">Live courier tracking</h2><span id="liveBadge" class="badge bg-slate-100 text-slate-600 ring-slate-200">—</span></div>
    <p id="liveMsg" class="mt-2 text-sm text-slate-500">Checking…</p>
    <dl class="mt-3 space-y-1.5 text-sm">
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Courier</dt><dd id="liveCourier" class="font-medium text-slate-800">—</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Last update</dt><dd id="liveAge">—</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Distance to destination</dt><dd id="liveDist">—</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Estimated arrival</dt><dd id="liveEta" class="font-medium text-cyan-300">—</dd></div>
    </dl>
    <p id="liveNote" class="mt-2 text-[11px] text-slate-400">ETA is an estimate: straight-line distance × 1.3 ÷ the courier’s measured (or default 30 km/h) speed.</p>
</section>

@push('scripts')
<script>
window.addEventListener('load', () => {
    const URL_STATE = @json(route('live.shipment', $shipment));
    const $ = (id) => document.getElementById(id);
    let marker = null, recordedAt = null, state = 'NONE';
    const B = { ACTIVE: ['LIVE GPS', 'bg-emerald-400/10 text-emerald-300 ring-emerald-400/30'], STALE: ['SIGNAL LOST', 'bg-gold/10 text-gold ring-gold/30'], NONE: ['NO GPS', 'bg-slate-100 text-slate-600 ring-slate-200'] };

    const draw = (lat, lng, source) => {
        const map = window.rkShowMap; if (!map || lat == null) return;
        if (!marker) {
            marker = L.marker([lat, lng], { zIndexOffset: 1500, icon: L.divIcon({ className: '', iconSize: [28, 28], iconAnchor: [14, 14], html: '<div class="rk-pin" style="--c:#ffffff"><span class="ring"></span><span class="core"></span></div>' }) }).addTo(map);
        } else { marker.setLatLng([lat, lng]); }
        marker.bindPopup(source === 'demo' ? 'Courier (DEMO point)' : 'Courier — live position');
    };
    const clear = () => { if (marker && window.rkShowMap) { window.rkShowMap.removeLayer(marker); marker = null; } };
    const eta = (e) => {
        $('liveDist').textContent = e ? e.distance_km + ' km (straight line)' : '—';
        $('liveEta').textContent = e ? new Date(e.eta_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) + ' (~' + e.minutes + ' min)' : '—';
        $('liveNote').textContent = e ? `ETA estimate based on ${e.basis} (${e.speed_kmh} km/h).` : 'ETA is shown while a courier is on the way.';
    };
    const render = (j) => {
        state = j.state; const [l, c] = B[j.state] || B.NONE;
        $('liveBadge').textContent = (j.location && j.location.source === 'demo' ? 'DEMO · ' : '') + l; $('liveBadge').className = 'badge ' + c;
        $('liveMsg').textContent = j.message || ''; $('liveCourier').textContent = j.courier || '—';
        if (j.location) { recordedAt = new Date(j.location.recorded_at); draw(j.location.latitude, j.location.longitude, j.location.source); } else { recordedAt = null; clear(); }
        eta(j.eta);
    };
    const load = () => fetch(URL_STATE, { headers: { Accept: 'application/json' } }).then(r => r.ok ? r.json() : null).then(j => j && render(j)).catch(() => {});
    load();

    setInterval(() => {
        if (!recordedAt) { $('liveAge').textContent = '—'; return; }
        const s = Math.max(0, Math.round((Date.now() - recordedAt.getTime()) / 1000));
        $('liveAge').textContent = s < 60 ? s + ' s ago' : Math.round(s / 60) + ' min ago';
        if (state === 'ACTIVE' && s > {{ \App\Models\Courier::LIVE_SECONDS }}) render({ state: 'STALE', message: 'Signal lost — showing last known position.', courier: $('liveCourier').textContent, location: { latitude: marker?.getLatLng().lat, longitude: marker?.getLatLng().lng, recorded_at: recordedAt.toISOString() }, eta: null });
    }, 1000);
    setInterval(() => { if (window.rkState !== 'live') load(); }, 15000);

    if (window.Echo) {
        const ch = window.Echo.private('shipment.{{ $shipment->id }}');
        ch.listen('.courier.location', (e) => render({ state: 'ACTIVE', message: 'Live GPS', courier: e.courier, location: { latitude: e.latitude, longitude: e.longitude, recorded_at: e.recorded_at, source: e.source }, eta: e.eta }));
        ch.listen('.shipment.updated', () => load()); // status/assignment changes: refresh state (and hide position when finished)
    }
});
</script>
@endpush
