@extends('layouts.app')
@section('title', 'Locations')
@section('subtitle', 'Origins, destinations and waypoints with stored coordinates')
@section('content')
<div class="grid gap-4 lg:grid-cols-3">
    <div class="card overflow-hidden p-0 lg:col-span-2">
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50"><tr><th class="th">Name</th><th class="th">City</th><th class="th">Country</th><th class="th">Type</th><th class="th">Lat, Lng</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($locations as $l)
                <tr><td class="td font-semibold text-slate-900">{{ $l->name }}</td><td class="td">{{ $l->city }}</td><td class="td">{{ $l->country }}</td><td class="td capitalize">{{ $l->type }}</td><td class="td whitespace-nowrap text-xs text-slate-500">{{ number_format($l->latitude, 4) }}, {{ number_format($l->longitude, 4) }}</td></tr>
            @empty
                <tr><td colspan="5" class="py-12 text-center text-sm text-slate-500">No locations yet.</td></tr>
            @endforelse
            </tbody></table></div>
    </div>
    @can('manage-master-data')
    <form method="POST" action="{{ route('locations.store') }}" class="card space-y-3 self-start">
        @csrf
        <h2 class="card-title">Add location</h2>
        <div class="flex gap-2">
            <input id="geoq" class="input" placeholder="Look up a place (OpenStreetMap)…" aria-label="Geocoding search">
            <button type="button" id="geoBtn" class="btn-ghost shrink-0">Find</button>
        </div>
        <p id="geoMsg" class="text-xs text-slate-500" aria-live="polite"></p>
        <ul id="geoRes" class="space-y-1 text-xs"></ul>
        <div><label class="label" for="name">Name</label><input id="name" name="name" value="{{ old('name') }}" required class="input"></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="label" for="city">City</label><input id="city" name="city" value="{{ old('city') }}" required class="input"></div>
            <div><label class="label" for="country">Country</label><input id="country" name="country" value="{{ old('country') }}" required class="input"></div>
        </div>
        <div><label class="label" for="type">Type</label><select id="type" name="type" class="input">@foreach (['port','airport','warehouse','city'] as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach</select></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="label" for="latitude">Latitude</label><input id="latitude" name="latitude" type="number" step="any" value="{{ old('latitude') }}" required class="input"></div>
            <div><label class="label" for="longitude">Longitude</label><input id="longitude" name="longitude" type="number" step="any" value="{{ old('longitude') }}" required class="input"></div>
        </div>
        <button class="btn-primary w-full">Add location</button>
    </form>
    @endcan
</div>
@endsection

@push('scripts')
<script>
(() => {
    const btn = document.getElementById('geoBtn'); if (!btn) return;
    const msg = document.getElementById('geoMsg'), list = document.getElementById('geoRes');
    btn.addEventListener('click', async () => {
        const q = document.getElementById('geoq').value.trim();
        list.innerHTML = ''; msg.textContent = 'Searching…';
        try {
            const r = await fetch(@json(route('geocode')) + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
            if (r.status === 422) { msg.textContent = 'Enter at least 3 characters.'; return; }
            const j = await r.json();
            msg.textContent = j.error || '';
            (j.results || []).forEach(p => {
                const li = document.createElement('li');
                const b = document.createElement('button'); b.type = 'button';
                b.className = 'w-full rounded-lg bg-slate-50 px-2 py-1.5 text-left ring-1 ring-slate-200 hover:bg-cyan-500/10';
                b.textContent = p.name;
                b.onclick = () => { document.getElementById('latitude').value = p.lat; document.getElementById('longitude').value = p.lng; const parts = p.name.split(','); document.getElementById('name').value ||= parts[0].trim(); };
                li.appendChild(b); list.appendChild(li);
            });
        } catch (e) { msg.textContent = 'Geocoding is unavailable. Enter coordinates manually.'; }
    });
})();
</script>
@endpush
