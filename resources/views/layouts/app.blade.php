<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Command Center') · RekaCy</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">
    @include('partials.realtime-config')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        #shell { --sw: 15rem; }
        #shell[data-collapsed="true"] { --sw: 4.25rem; }
        @media (min-width: 1024px) { #main { padding-left: var(--sw); } #sidebar { width: var(--sw); } }
        #shell[data-collapsed="true"] .nav-label, #shell[data-collapsed="true"] .side-extra { display: none; }
        #shell[data-collapsed="true"] .nav-link { justify-content: center; }
        .nav-link .tip { display: none; }
        #shell[data-collapsed="true"] .nav-link:hover .tip { display: block; }
    </style>
    @stack('head')
</head>
<body class="h-full bg-base font-sans text-slate-700 antialiased">
@php
    $u = auth()->user();
    $unread = $u->unreadNotifications()->count();
    $staff = $u->isStaff();
    $dbOk = rescue(fn () => \Illuminate\Support\Facades\DB::connection()->getPdo() !== null, false, false);
    $nav = $u->isCourier() ? [
        ['My tasks', 'courier.index', 'courier.*', 'truck'],
        ['Notifications', 'notifications.index', 'notifications.*', 'bell'],
        ['Profile', 'profile.edit', 'profile.*', 'user'],
    ] : ($staff ? [
        ['Command Center', 'dashboard', 'dashboard', 'tower'],
        ['Operations', 'operations.index', 'operations.*', 'ops'],
        ['Shipments', 'shipments.index', 'shipments.*', 'box'],
        ['Global Tracking', 'tracking.index', 'tracking.*', 'radar'],
        ['Exceptions', 'exceptions.index', 'exceptions.*', 'alert'],
        ['Alerts', 'alerts.index', 'alerts.*', 'bell'],
        ['Carriers', 'carriers.index', 'carriers.*', 'truck'],
        ['Locations', 'locations.index', 'locations.*', 'pin'],
        ['Analytics', 'analytics.index', 'analytics.*', 'chart'],
        ['Reports', 'reports.index', 'reports.*', 'list'],
        ['Documents', 'documents.index', 'documents.*', 'file'],
        ['Notifications', 'notifications.index', 'notifications.*', 'bell'],
        ['Couriers', 'couriers.index', 'couriers.*', 'user'],
        ['Support', 'support.index', 'support.*', 'ticket'],
    ] : [
        ['Overview', 'dashboard', 'dashboard', 'tower'],
        ['My Shipments', 'shipments.index', 'shipments.*', 'box'],
        ['Live Map', 'tracking.index', 'tracking.*', 'radar'],
        ['Documents', 'documents.index', 'documents.*', 'file'],
        ['Notifications', 'notifications.index', 'notifications.*', 'bell'],
        ['Support', 'support.index', 'support.*', 'ticket'],
        ['Profile', 'profile.edit', 'profile.*', 'user'],
    ]);
    if ($u->isAdmin()) {
        $nav[] = ['Users', 'users.index', 'users.*', 'users'];
        $nav[] = ['Audit Log', 'audit.index', 'audit.*', 'shield'];
        $nav[] = ['Settings', 'settings.index', 'settings.*', 'cog'];
    }
    $paletteItems = collect($nav)->map(fn ($n) => ['t' => $n[0], 'u' => route($n[1]), 'k' => 'Go to'])->values();
    if ($staff) { $paletteItems->push(['t' => 'New shipment', 'u' => route('shipments.create'), 'k' => 'Action']); }
    $paletteItems->push(['t' => 'Public tracking page', 'u' => route('track.public'), 'k' => 'Go to']);
@endphp
<div id="shell" data-collapsed="false" class="min-h-full">
    {{-- Sidebar --}}
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-60 -translate-x-full border-r border-line bg-deep transition-transform lg:translate-x-0">
        <div class="flex h-14 items-center gap-3 border-b border-line px-4">
            <x-logo :size="32" />
            <div class="side-extra leading-tight">
                <p class="text-[17px] font-extrabold tracking-tight text-white">Reka<span class="text-cyan-300">Cy</span></p>
                <p class="-mt-0.5 text-[9px] font-semibold uppercase tracking-[.18em] text-slate-500">Command Center</p>
            </div>
        </div>
        <nav class="mt-3 max-h-[calc(100vh-9rem)] space-y-0.5 overflow-y-auto px-2" aria-label="Main">
            @foreach ($nav as [$label, $route, $pattern, $icon])
                @php $active = request()->routeIs($pattern); @endphp
                <a href="{{ route($route) }}" class="nav-link group relative flex items-center gap-3 rounded-lg px-3 py-2 text-[13px] font-medium transition {{ $active ? 'bg-cyan-400/10 text-cyan-300 ring-1 ring-cyan-400/30' : 'text-slate-500 hover:bg-white/5 hover:text-slate-900' }}" @if($active) aria-current="page" @endif>
                    <x-icon :name="$icon" class="h-[18px] w-[18px] shrink-0 {{ $active ? 'text-cyan-300' : '' }}" />
                    <span class="nav-label truncate">{{ $label }}</span>
                    @if ($route === 'notifications.index' && $unread)<span class="nav-label ml-auto rounded bg-red-500/20 px-1.5 text-[10px] font-bold text-red-300">{{ $unread }}</span>@endif
                    <span class="tip pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded-md border border-line bg-panel px-2 py-1 text-xs text-slate-900 shadow-lg">{{ $label }}</span>
                </a>
            @endforeach
        </nav>
        <div class="side-extra absolute inset-x-0 bottom-0 border-t border-line p-3 text-[11px] text-slate-500">
            <p class="font-semibold text-slate-700">{{ config('rekacy.group.name') }}</p>
            <p>{{ config('rekacy.group.route') }}</p>
        </div>
    </aside>
    <div id="backdrop" class="fixed inset-0 z-30 hidden bg-black/60 lg:hidden"></div>

    <div id="main" class="flex min-h-full min-w-0 flex-col">
        {{-- Header --}}
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-line bg-base/85 px-3 backdrop-blur sm:px-5">
            <button id="menuBtn" class="rounded-lg p-2 text-slate-500 hover:bg-white/5 lg:hidden" aria-label="Open menu"><x-icon name="menu" /></button>
            <button id="collapseBtn" class="hidden rounded-lg p-2 text-slate-500 hover:bg-white/5 hover:text-slate-900 lg:block" aria-label="Collapse sidebar"><x-icon name="collapse" class="h-5 w-5" /></button>
            <div class="min-w-0">
                <nav class="hidden text-[11px] text-slate-500 sm:block" aria-label="Breadcrumb">RekaCy <span class="mx-1 text-slate-400">/</span> <span class="text-slate-600">@yield('title', 'Command Center')</span></nav>
                <h1 class="truncate text-[15px] font-bold leading-tight text-slate-900">@yield('title', 'Command Center')</h1>
            </div>

            <button id="paletteBtn" type="button" class="ml-auto hidden w-72 items-center gap-2 rounded-lg bg-deep px-3 py-1.5 text-left text-sm text-slate-500 ring-1 ring-line hover:ring-cyan-400/50 md:flex" aria-label="Search (Ctrl+K)">
                <x-icon name="search" class="h-4 w-4" /> <span class="flex-1">Search shipments, carriers…</span> <span class="kbd">Ctrl K</span>
            </button>
            <button id="paletteBtn2" type="button" class="ml-auto rounded-lg p-2 text-slate-500 hover:bg-white/5 md:hidden" aria-label="Search"><x-icon name="search" /></button>

            <span id="rtPill" class="hidden sm:inline-flex"></span>
            <span class="hidden items-center gap-2 rounded-lg px-2.5 py-1.5 text-[11px] font-semibold ring-1 xl:flex {{ $dbOk ? 'bg-emerald-400/10 text-emerald-300 ring-emerald-400/30' : 'bg-red-500/10 text-red-300 ring-red-400/30' }}" title="Database connectivity">
                <span class="h-2 w-2 rounded-full {{ $dbOk ? 'bg-emerald-400' : 'bg-red-500' }}"></span> {{ $dbOk ? 'SYSTEM ONLINE' : 'DATABASE DOWN' }}
            </span>
            <span id="clock" class="hidden font-mono text-xs text-slate-500 lg:block" data-tz="{{ config('rekacy.timezone') }}"></span>

            <a href="{{ route('notifications.index') }}" class="relative rounded-lg p-2 text-slate-500 hover:bg-white/5 hover:text-slate-900" aria-label="Notifications">
                <x-icon name="bell" />
                <span id="bellBadge" class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white {{ $unread ? '' : 'hidden' }}">{{ $unread > 99 ? '99+' : $unread }}</span>
            </a>
            <details class="relative">
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg p-1 hover:bg-white/5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-cyan-400/15 text-xs font-bold text-cyan-300 ring-1 ring-cyan-400/40">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                    <span class="hidden text-left leading-tight sm:block"><span class="block text-[13px] font-semibold text-slate-900">{{ $u->name }}</span><span class="block text-[10px] text-slate-500">{{ $u->role->label() }}</span></span>
                </summary>
                <div class="absolute right-0 mt-2 w-48 rounded-lg border border-line bg-panel p-1 shadow-xl">
                    <a href="{{ route('profile.edit') }}" class="block rounded-md px-3 py-2 text-sm hover:bg-white/5">Profile settings</a>
                    <a href="{{ route('support.index') }}" class="block rounded-md px-3 py-2 text-sm hover:bg-white/5">Support</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="block w-full rounded-md px-3 py-2 text-left text-sm text-red-300 hover:bg-red-500/10">Sign out</button></form>
                </div>
            </details>
        </header>

        <main class="flex-1 p-3 sm:p-5">
            <x-flash />
            @yield('content')
        </main>
        <footer class="px-5 pb-4 text-[11px] text-slate-400">RekaCy · Global visibility. Intelligent logistics. Complete control. · Map data © OpenStreetMap contributors</footer>
    </div>
</div>

{{-- Command palette --}}
<div id="palette" class="fixed inset-0 z-50 hidden items-start justify-center bg-black/70 p-4 pt-[12vh]" role="dialog" aria-modal="true" aria-label="Command palette">
    <div class="w-full max-w-xl overflow-hidden rounded-xl border border-line bg-panel shadow-2xl">
        <div class="flex items-center gap-2 border-b border-line px-4">
            <x-icon name="search" class="h-4 w-4 text-slate-500" />
            <input id="paletteInput" autocomplete="off" placeholder="Type a page, shipment number, carrier, customer…" class="w-full border-0 bg-transparent py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0" aria-label="Search">
            <span class="kbd">Esc</span>
        </div>
        <ul id="paletteList" class="max-h-80 overflow-y-auto p-1.5 text-sm" role="listbox"></ul>
        <p class="border-t border-line px-4 py-2 text-[11px] text-slate-500">↑ ↓ to move · Enter to open · Ctrl K to toggle</p>
    </div>
</div>

<script>
(() => {
    const shell = document.getElementById('shell'), sb = document.getElementById('sidebar'), bd = document.getElementById('backdrop');
    try { if (localStorage.getItem('rk-collapsed') === '1') shell.dataset.collapsed = 'true'; } catch (e) {}
    document.getElementById('collapseBtn').addEventListener('click', () => {
        const c = shell.dataset.collapsed !== 'true'; shell.dataset.collapsed = String(c);
        try { localStorage.setItem('rk-collapsed', c ? '1' : '0'); } catch (e) {}
        setTimeout(() => window.dispatchEvent(new Event('resize')), 250);
    });
    const toggle = (o) => { sb.classList.toggle('-translate-x-full', !o); bd.classList.toggle('hidden', !o); };
    document.getElementById('menuBtn').addEventListener('click', () => toggle(true));
    bd.addEventListener('click', () => toggle(false));

    window.esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    // Clock (configured timezone)
    const clock = document.getElementById('clock');
    const fmt = new Intl.DateTimeFormat('en-GB', { timeZone: clock.dataset.tz, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
    const tick = () => { clock.textContent = fmt.format(new Date()); }; tick(); setInterval(tick, 1000);

    // Command palette
    const pal = document.getElementById('palette'), inp = document.getElementById('paletteInput'), list = document.getElementById('paletteList');
    const NAV = @json($paletteItems);
    const SEARCH_URL = @json(route('search.json'));
    let items = [], sel = 0, timer = null;
    const render = () => {
        list.replaceChildren();
        if (!items.length) { const li = document.createElement('li'); li.className = 'px-3 py-6 text-center text-slate-500'; li.textContent = 'No results'; list.appendChild(li); return; }
        items.forEach((it, i) => {
            const li = document.createElement('li'); li.setAttribute('role', 'option');
            li.className = 'flex cursor-pointer items-center justify-between gap-3 rounded-md px-3 py-2 ' + (i === sel ? 'bg-cyan-400/10 text-cyan-300' : 'text-slate-700 hover:bg-white/5');
            const l = document.createElement('div'); const a = document.createElement('p'); a.className = 'font-medium'; a.textContent = it.t; l.appendChild(a);
            if (it.s) { const b = document.createElement('p'); b.className = 'text-xs text-slate-500'; b.textContent = it.s; l.appendChild(b); }
            const k = document.createElement('span'); k.className = 'kbd'; k.textContent = it.k;
            li.append(l, k); li.addEventListener('mousedown', (e) => { e.preventDefault(); location.href = it.u; });
            list.appendChild(li);
        });
    };
    const base = (q) => NAV.filter(n => n.t.toLowerCase().includes(q.toLowerCase()));
    const open = () => { pal.classList.remove('hidden'); pal.classList.add('flex'); inp.value = ''; items = NAV.slice(0, 8); sel = 0; render(); inp.focus(); };
    const close = () => { pal.classList.add('hidden'); pal.classList.remove('flex'); };
    ['paletteBtn', 'paletteBtn2'].forEach(id => document.getElementById(id)?.addEventListener('click', open));
    pal.addEventListener('mousedown', (e) => { if (e.target === pal) close(); });
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); pal.classList.contains('hidden') ? open() : close(); }
        else if (e.key === 'Escape' && !pal.classList.contains('hidden')) close();
    });
    inp.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(sel + 1, items.length - 1); render(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(sel - 1, 0); render(); }
        else if (e.key === 'Enter' && items[sel]) { location.href = items[sel].u; }
    });
    inp.addEventListener('input', () => {
        const q = inp.value.trim(); sel = 0; clearTimeout(timer);
        if (q.length < 2) { items = q ? base(q) : NAV.slice(0, 8); render(); return; }
        items = base(q); render();
        timer = setTimeout(async () => {
            try {
                const r = await fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
                if (!r.ok) return; const j = await r.json();
                const map = (arr, k) => (arr || []).map(x => ({ t: x.title, s: x.sub, u: x.url, k }));
                if (inp.value.trim() !== q) return;
                items = [...base(q), ...map(j.shipments, 'Shipment'), ...map(j.customers, 'Customer'), ...map(j.carriers, 'Carrier'), ...map(j.locations, 'Location')]; sel = 0; render();
            } catch (e) { /* keep local results */ }
        }, 200);
    });
})();
</script>
@include('partials.realtime')
@stack('scripts')
</body>
</html>
