@extends('layouts.guest')
@section('title', 'RekaCy — Global Logistics Command Center')
@section('content')
<header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
    <a href="{{ route('home') }}" class="flex items-center gap-3"><x-logo :size="36" /><span class="text-xl font-extrabold text-white">Reka<span class="text-cyan-300">Cy</span></span></a>
    <nav class="flex items-center gap-4 text-sm"><a href="{{ route('about') }}" class="text-slate-500 hover:text-white">About</a><a href="{{ route('track.public') }}" class="text-slate-500 hover:text-white">Track shipment</a><a href="{{ route('login') }}" class="btn-primary py-1.5">Sign in</a></nav>
</header>
<section class="relative overflow-hidden">
    <svg class="absolute inset-0 -z-0 h-full w-full opacity-70" viewBox="0 0 1200 600" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><defs><pattern id="gg" width="48" height="48" patternUnits="userSpaceOnUse"><path d="M48 0H0V48" fill="none" stroke="#17243a"/></pattern></defs><rect width="1200" height="600" fill="url(#gg)"/>
        <path d="M120 480 C 300 360, 520 380, 640 280 S 960 140, 1100 120" stroke="#22d3ee" stroke-width="2.5" fill="none" stroke-dasharray="3 9" style="filter: drop-shadow(0 0 6px #22d3ee)"/><path d="M200 140 C 400 220, 700 260, 900 420" stroke="#f5be42" stroke-width="2" fill="none" stroke-dasharray="3 9"/></svg>
    <div class="relative mx-auto max-w-6xl px-6 pb-24 pt-20">
        <p class="text-[11px] font-semibold uppercase tracking-[.3em] text-cyan-300">Global logistics intelligence &amp; exception management</p>
        <h1 class="mt-4 max-w-3xl text-5xl font-extrabold leading-[1.05] text-white sm:text-6xl">Global visibility.<br>Intelligent logistics.<br><span class="text-cyan-300">Complete control.</span></h1>
        <p class="mt-6 max-w-xl text-lg text-slate-500">One command center for every shipment: validated lifecycle, transparent ETA and delay rules, live route map and a real exception workflow.</p>
        <div class="mt-8 flex flex-wrap gap-3"><a href="{{ route('login') }}" class="btn-primary px-6 py-3">Open command center</a><a href="{{ route('track.public') }}" class="btn-ghost px-6 py-3">Track a shipment</a></div>
    </div>
</section>
<section class="mx-auto grid max-w-6xl gap-4 px-6 pb-20 md:grid-cols-3">
    @foreach ([['Command center map', 'Cyan routes for normal shipments, gold for attention, red for critical — drawn from stored tracking events.'], ['Deterministic ETA engine', 'Delay = ETA − expected arrival. LOW, MEDIUM, HIGH tiers are configurable and always explained.'], ['Exception management', 'Customs holds, damage, misrouting: assign, investigate, resolve — with full audit trail.']] as [$t, $d])
        <div class="card"><h3 class="font-semibold text-white">{{ $t }}</h3><p class="mt-2 text-sm text-slate-500">{{ $d }}</p></div>
    @endforeach
</section>
<footer class="border-t border-line py-6 text-center text-xs text-slate-400">© RekaCy · Map data © OpenStreetMap contributors</footer>
@endsection
