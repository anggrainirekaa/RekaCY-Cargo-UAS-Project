@extends('layouts.courier')
@section('title', $a->shipment->shipment_number)

@section('content')
@php
    $s = $a->shipment; $st = $a->status;
    $steps = [\App\Enums\AssignmentStatus::Assigned, \App\Enums\AssignmentStatus::Accepted, \App\Enums\AssignmentStatus::PickedUp, \App\Enums\AssignmentStatus::InTransit, \App\Enums\AssignmentStatus::Arrived, \App\Enums\AssignmentStatus::Completed];
    $idx = array_search($st, $steps, true);
    $btn = 'btn-primary w-full py-4 text-base';
    $act = fn ($name) => route('courier.act', [$a, $name]);
@endphp

<a href="{{ route('courier.index') }}" class="mb-3 inline-flex items-center gap-1 text-sm text-slate-500">← Semua tugas</a>

<section class="card">
    <div class="flex items-start justify-between gap-3">
        <div><h1 class="text-xl font-extrabold text-slate-900">{{ $s->shipment_number }}</h1>
            <p class="text-sm text-slate-500">{{ $s->cargo_type }} · {{ number_format($s->weight_kg, 0) }} kg</p></div>
        <span class="badge {{ $st->badgeClasses() }}">{{ $st->labelId() }}</span>
    </div>
    <dl class="mt-3 space-y-2 text-sm">
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Ambil di</dt><dd class="text-right font-medium text-slate-800">{{ $s->origin->name }}, {{ $s->origin->city }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Antar ke</dt><dd class="text-right font-medium text-slate-800">{{ $s->destination->name }}, {{ $s->destination->city }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Penerima / pelanggan</dt><dd class="text-right">{{ $s->customer->name }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Perkiraan tiba</dt><dd>{{ ($s->estimated_arrival_at ?? $s->expected_arrival_at)->format('d M Y H:i') }}</dd></div>
    </dl>
    @if ($a->notes)<p class="mt-3 rounded-lg bg-deep p-3 text-xs text-slate-500">Catatan: {{ $a->notes }}</p>@endif

    @if ($idx !== false)
        <ol class="mt-4 grid grid-cols-6 gap-1" aria-label="Progres tugas">
            @foreach ($steps as $i => $step)<li><div class="h-1.5 rounded-full {{ $i <= $idx ? 'bg-cyan-400' : 'bg-slate-200' }}"></div><p class="mt-1 text-[9px] leading-tight {{ $i <= $idx ? 'text-cyan-300' : 'text-slate-400' }}">{{ $step->labelId() }}</p></li>@endforeach
        </ol>
    @endif
</section>

{{-- Actions --}}
<section class="mt-3 space-y-3">
    @if ($st === \App\Enums\AssignmentStatus::Assigned)
        <form method="POST" action="{{ $act('accept') }}">@csrf<button class="{{ $btn }}">Terima tugas</button></form>
    @elseif ($st === \App\Enums\AssignmentStatus::Accepted)
        <form method="POST" action="{{ $act('pickup') }}">@csrf<button class="{{ $btn }}">Barang sudah diambil</button></form>
    @elseif ($st === \App\Enums\AssignmentStatus::PickedUp)
        <form method="POST" action="{{ $act('start') }}">@csrf<button class="{{ $btn }}">Mulai pengiriman</button></form>
    @elseif ($st === \App\Enums\AssignmentStatus::InTransit)
        <form method="POST" action="{{ $act('arrive') }}">@csrf<button class="{{ $btn }}">Saya sudah sampai di tujuan</button></form>
    @elseif ($st === \App\Enums\AssignmentStatus::Arrived)
        <form method="POST" action="{{ $act('complete') }}" enctype="multipart/form-data" class="card space-y-3">@csrf
            <h2 class="card-title">Selesaikan pengiriman</h2>
            <div><label class="label" for="recipient_name">Nama penerima</label><input id="recipient_name" name="recipient_name" required maxlength="120" value="{{ old('recipient_name') }}" class="input py-3 text-base"></div>
            <div><label class="label" for="photo">Foto bukti pengiriman</label><input id="photo" type="file" name="photo" accept="image/*" capture="environment" required class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-elev file:px-4 file:py-3 file:text-slate-700"></div>
            <div><label class="label" for="notes">Catatan (opsional)</label><input id="notes" name="notes" maxlength="500" class="input py-3 text-base"></div>
            <button class="{{ $btn }}">Selesaikan &amp; kirim bukti</button>
        </form>
    @elseif ($st === \App\Enums\AssignmentStatus::Completed)
        <p class="rounded-xl bg-emerald-500/10 p-4 text-center text-sm font-semibold text-emerald-300 ring-1 ring-emerald-400/30">Pengiriman selesai{{ $a->recipient_name ? ' — diterima oleh '.$a->recipient_name : '' }}.</p>
    @elseif ($st === \App\Enums\AssignmentStatus::Failed)
        <p class="rounded-xl bg-red-500/10 p-4 text-sm text-red-300 ring-1 ring-red-400/30">Pengiriman dilaporkan gagal: {{ $a->failure_reason }}</p>
    @elseif ($st === \App\Enums\AssignmentStatus::Cancelled)
        <p class="rounded-xl bg-slate-100 p-4 text-center text-sm text-slate-600">Tugas ini dibatalkan atau dialihkan ke kurir lain.</p>
    @endif

    @if ($st->tracksGps())
        <details class="card">
            <summary class="cursor-pointer text-sm font-semibold text-red-300">Laporkan pengiriman gagal</summary>
            <form method="POST" action="{{ $act('fail') }}" class="mt-3 space-y-3">@csrf
                <label class="label" for="reason">Alasan (min. 5 karakter)</label>
                <textarea id="reason" name="reason" rows="3" required minlength="5" maxlength="500" class="input text-base"></textarea>
                <button class="btn-danger w-full py-3">Kirim laporan gagal</button>
            </form>
        </details>
    @endif
</section>

{{-- GPS --}}
@if ($st->tracksGps())
<section class="card mt-3" id="gps">
    <div class="flex items-center justify-between"><h2 class="card-title">Pelacakan GPS</h2><span id="gpsBadge" class="badge bg-slate-100 text-slate-600 ring-slate-200">belum aktif</span></div>
    <p id="gpsMsg" class="mt-2 text-sm text-slate-500" aria-live="polite">Lokasi hanya dikirim saat kamu menekan <b>Mulai pelacakan</b> dan hanya selama pengiriman berlangsung.</p>
    <dl class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
        <div class="rounded-lg bg-deep p-2"><dd id="gpsSent" class="text-lg font-extrabold text-cyan-300">{{ $points }}</dd><dt class="text-slate-500">terkirim</dt></div>
        <div class="rounded-lg bg-deep p-2"><dd id="gpsQueue" class="text-lg font-extrabold text-gold">0</dd><dt class="text-slate-500">antre</dt></div>
        <div class="rounded-lg bg-deep p-2"><dd id="gpsAcc" class="text-lg font-extrabold text-slate-800">—</dd><dt class="text-slate-500">akurasi (m)</dt></div>
    </dl>
    <p id="gpsLast" class="mt-2 text-xs text-slate-500"></p>
    <div class="mt-3 grid grid-cols-2 gap-2">
        <button id="gpsStart" type="button" class="btn-primary py-3.5">Mulai pelacakan</button>
        <button id="gpsStop" type="button" class="btn-ghost py-3.5" disabled>Hentikan</button>
    </div>
    <p class="mt-2 text-[11px] text-slate-400">Biarkan layar tetap menyala dan halaman ini terbuka. Di HP, browser dapat menghentikan GPS saat layar mati.</p>
    @if ($demo)
        <div class="mt-3 rounded-lg border border-dashed border-gold/40 p-3">
            <p class="text-xs font-semibold text-gold">MODE DEMO (bukan GPS asli)</p>
            <p class="text-[11px] text-slate-500">Mengirim titik uji yang bergerak ke tujuan, ditandai <b>demo</b> di sistem. Untuk presentasi di laptop tanpa GPS.</p>
            <button id="gpsDemo" type="button" class="btn-ghost mt-2 w-full py-2.5 text-xs">Simulasikan perjalanan (DEMO)</button>
        </div>
    @endif
</section>
@endif
@endsection

@push('scripts')
@if ($st->tracksGps())
<script>
(() => {
    const C = @json([
        'assignmentId' => $a->id,
        'locationUrl' => route('courier.location'),
        'startUrl' => route('courier.tracking.start'),
        'stopUrl' => route('courier.tracking.stop'),
        'serverTracking' => (bool) $a->courier->is_tracking,
        'dest' => ['lat' => $s->destination->latitude, 'lng' => $s->destination->longitude],
        'origin' => ['lat' => $s->origin->latitude, 'lng' => $s->origin->longitude],
        'last' => $last ? ['lat' => $last->latitude, 'lng' => $last->longitude] : null,
        'demo' => $demo,
    ]);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (id) => document.getElementById(id);
    const INTERVAL_MS = 10000, MIN_GAP_MS = 3000, MIN_MOVE_M = 15, MAX_QUEUE = 200, MAX_AGE_MS = 9 * 60 * 1000;
    let watchId = null, lastSent = 0, lastPos = null, sent = parseInt($('gpsSent').textContent) || 0, queue = [], demoTimer = null, running = false;

    const STATES = {
        off: ['belum aktif', 'bg-slate-100 text-slate-600 ring-slate-200', 'Lokasi hanya dikirim saat kamu menekan Mulai pelacakan dan hanya selama pengiriman berlangsung.'],
        searching: ['mencari sinyal', 'bg-gold/10 text-gold ring-gold/30', 'Mencari sinyal GPS… keluar ruangan atau dekat jendela membantu.'],
        active: ['GPS aktif', 'bg-emerald-400/10 text-emerald-300 ring-emerald-400/30', 'GPS aktif — lokasi dikirim ke server secara berkala.'],
        denied: ['izin ditolak', 'bg-red-500/10 text-red-300 ring-red-400/30', 'Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini di pengaturan browser, lalu coba lagi.'],
        unavailable: ['GPS tidak tersedia', 'bg-red-500/10 text-red-300 ring-red-400/30', 'Perangkat atau browser ini tidak menyediakan GPS (butuh HTTPS atau localhost).'],
        offline: ['koneksi terputus', 'bg-gold/10 text-gold ring-gold/30', 'Koneksi internet terputus. Lokasi disimpan sementara dan dikirim otomatis saat online.'],
    };
    const setStatus = (k, extra) => { const [l, c, m] = STATES[k]; $('gpsBadge').textContent = l; $('gpsBadge').className = 'badge ' + c; $('gpsMsg').textContent = extra || m; };
    const refresh = () => { $('gpsSent').textContent = sent; $('gpsQueue').textContent = queue.length; $('gpsStart').disabled = running; $('gpsStop').disabled = !running; };
    const post = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body || {}) });
    const metres = (a, b) => { const R = 6371000, r = Math.PI / 180, dLa = (b.latitude - a.latitude) * r, dLo = (b.longitude - a.longitude) * r; const x = Math.sin(dLa / 2) ** 2 + Math.cos(a.latitude * r) * Math.cos(b.latitude * r) * Math.sin(dLo / 2) ** 2; return 2 * R * Math.asin(Math.sqrt(x)); };

    // 'ok' | 'rejected' (server refused: do not retry) | 'fail' (network / server error: keep in queue)
    async function trySend(p) {
        try {
            const r = await post(C.locationUrl, p);
            if (r.status === 422 || r.status === 403) {
                const j = await r.json().catch(() => ({}));
                if (/not started|in progress|not assigned/i.test(j.message || '')) { await stop(true); setStatus('off', j.message); }
                return 'rejected';
            }
            if (!r.ok) return 'fail';
            sent++; $('gpsLast').textContent = 'Terakhir terkirim: ' + new Date().toLocaleTimeString('id-ID'); return 'ok';
        } catch (e) { return 'fail'; }
    }
    async function flush() {
        while (queue.length) {
            const p = queue[0];
            if (Date.now() - Date.parse(p.recorded_at) > MAX_AGE_MS) { queue.shift(); continue; }
            const res = await trySend(p);
            if (res === 'fail') break;
            queue.shift();
        }
        refresh();
        if (!queue.length && running && navigator.onLine) setStatus('active');
    }
    async function submit(p) {
        if (queue.length) { queue.push(p); await flush(); return; }
        const res = await trySend(p);
        if (res === 'fail') { queue.push(p); if (queue.length > MAX_QUEUE) queue.shift(); setStatus('offline'); }
        else if (res === 'ok') setStatus('active');
        refresh();
    }

    function onPosition(pos) {
        const c = pos.coords;
        const p = { assignment_id: C.assignmentId, latitude: c.latitude, longitude: c.longitude, accuracy: c.accuracy, recorded_at: new Date(pos.timestamp).toISOString(), source: 'device' };
        if (c.speed != null && !isNaN(c.speed)) p.speed_kmh = c.speed * 3.6;
        if (c.heading != null && !isNaN(c.heading)) p.heading = c.heading;
        $('gpsAcc').textContent = Math.round(c.accuracy);
        const now = Date.now(), moved = lastPos ? metres(lastPos, p) : Infinity;
        if (lastSent && (now - lastSent < MIN_GAP_MS || (now - lastSent < INTERVAL_MS && moved < MIN_MOVE_M))) return;
        lastSent = now; lastPos = p; submit(p);
    }
    function onError(e) {
        if (e.code === 1) { setStatus('denied'); stop(); }
        else if (e.code === 2) setStatus('unavailable', 'Posisi tidak tersedia saat ini. Coba pindah ke area terbuka.');
        else setStatus('searching');
    }

    async function start() {
        if (!('geolocation' in navigator)) { setStatus('unavailable'); return; }
        $('gpsStart').disabled = true;
        const r = await post(C.startUrl).catch(() => null);
        if (!r || !r.ok) { const j = r ? await r.json().catch(() => ({})) : {}; setStatus('offline', j.message || 'Tidak dapat memulai pelacakan. Periksa koneksi.'); refresh(); return; }
        running = true; setStatus('searching'); refresh();
        watchId = navigator.geolocation.watchPosition(onPosition, onError, { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 });
    }
    async function stop(silent) {
        if (watchId !== null) navigator.geolocation.clearWatch(watchId);
        watchId = null; running = false; clearInterval(demoTimer); demoTimer = null;
        if (!silent) { await post(C.stopUrl).catch(() => {}); setStatus('off'); }
        refresh();
    }

    // DEMO: clearly flagged, never presented as device GPS
    async function demo() {
        if (demoTimer) { clearInterval(demoTimer); demoTimer = null; $('gpsDemo').textContent = 'Simulasikan perjalanan (DEMO)'; return; }
        if (!running) { const r = await post(C.startUrl); if (!r.ok) return; running = true; refresh(); }
        let cur = lastPos ? { latitude: lastPos.latitude, longitude: lastPos.longitude } : (C.last ? { latitude: C.last.lat, longitude: C.last.lng } : { latitude: C.origin.lat, longitude: C.origin.lng });
        $('gpsDemo').textContent = 'Hentikan simulasi';
        demoTimer = setInterval(() => {
            const dest = { latitude: C.dest.lat, longitude: C.dest.lng }, d = metres(cur, dest);
            if (d < 50) { clearInterval(demoTimer); demoTimer = null; $('gpsDemo').textContent = 'Simulasikan perjalanan (DEMO)'; return; }
            const f = Math.min(1, Math.max(0.04, 300 / d));
            cur = { latitude: cur.latitude + (dest.latitude - cur.latitude) * f, longitude: cur.longitude + (dest.longitude - cur.longitude) * f };
            lastPos = cur; $('gpsAcc').textContent = '—';
            submit({ assignment_id: C.assignmentId, latitude: cur.latitude, longitude: cur.longitude, accuracy: 10, recorded_at: new Date().toISOString(), source: 'demo' });
        }, 3000);
    }

    $('gpsStart').addEventListener('click', start);
    $('gpsStop').addEventListener('click', () => stop());
    $('gpsDemo')?.addEventListener('click', demo);
    window.addEventListener('online', () => { if (queue.length) flush(); else if (running) setStatus('active'); });
    window.addEventListener('offline', () => { if (running) setStatus('offline'); });
    if (C.serverTracking) setStatus('off', 'Pelacakan tercatat aktif di server, tetapi perangkat ini belum mengirim. Tekan Mulai pelacakan untuk melanjutkan.');
    refresh();
})();
</script>
@endif
@endpush
