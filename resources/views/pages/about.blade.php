@extends('layouts.guest')
@section('title', 'About · RekaCy')
@section('content')
<div class="mx-auto max-w-3xl px-6 py-14">
    <a href="{{ route('home') }}" class="flex items-center gap-3"><x-logo :size="36" /><span class="text-xl font-extrabold text-white">Reka<span class="text-cyan-300">Cy</span></span></a>
    <h1 class="mt-10 text-3xl font-extrabold text-white">About RekaCy</h1>
    <p class="mt-4 text-slate-500">RekaCy is a Global Logistics Intelligence &amp; Exception Management System built for the Web Programming 2 final project. It combines shipment lifecycle management, tracking history, rule-based ETA and delay detection, exception handling, documents, support tickets and role-based dashboards.</p>
    <div class="card mt-8 space-y-2 text-sm text-slate-600"><p><b class="text-slate-900">Scenario:</b> {{ config('rekacy.group.name') }} — {{ config('rekacy.group.route') }}</p><p><b class="text-slate-900">Stack:</b> PHP 8.4, Laravel 12, SQLite/MySQL, Blade, Tailwind CSS, Leaflet, Chart.js, Sanctum.</p><p><b class="text-slate-900">Data honesty:</b> demo positions are simulated and labelled as such; RekaCy never invents live GPS.</p></div>
    <p class="mt-8"><a href="{{ route('login') }}" class="btn-primary">Sign in</a> <a href="{{ route('track.public') }}" class="btn-ghost">Track a shipment</a></p>
</div>
@endsection
