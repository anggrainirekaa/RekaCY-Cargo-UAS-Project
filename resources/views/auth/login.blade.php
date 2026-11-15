@extends('layouts.guest')
@section('title', 'Sign in · RekaCy')
@section('content')
<div class="grid min-h-full lg:grid-cols-[1.15fr_1fr]">
    <div class="relative hidden overflow-hidden border-r border-line bg-deep p-12 lg:flex lg:flex-col lg:justify-between">
        <svg class="absolute inset-0 h-full w-full" viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <defs><pattern id="g" width="48" height="48" patternUnits="userSpaceOnUse"><path d="M48 0H0V48" fill="none" stroke="#17243a" stroke-width="1"/></pattern>
                <radialGradient id="glow" cx="60%" cy="35%" r="60%"><stop offset="0" stop-color="#22d3ee" stop-opacity=".16"/><stop offset="1" stop-color="#22d3ee" stop-opacity="0"/></radialGradient></defs>
            <rect width="800" height="900" fill="url(#g)"/><rect width="800" height="900" fill="url(#glow)"/>
            <path d="M60 760 C 200 620, 280 560, 400 500 S 620 360, 740 150" fill="none" stroke="#22d3ee" stroke-width="2.5" stroke-dasharray="3 9" style="filter: drop-shadow(0 0 6px #22d3ee)"/>
            <path d="M120 300 C 260 360, 420 340, 560 520" fill="none" stroke="#f5be42" stroke-width="2" stroke-dasharray="3 9" opacity=".8"/>
            <circle cx="60" cy="760" r="8" fill="#22d3ee"/><circle cx="400" cy="500" r="6" fill="#168bff"/><circle cx="740" cy="150" r="8" fill="#f5be42"/><circle cx="560" cy="520" r="6" fill="#ef4444"/>
        </svg>
        <div class="relative flex items-center gap-3"><x-logo :size="44" /><span class="text-2xl font-extrabold tracking-tight text-white">Reka<span class="text-cyan-300">Cy</span></span></div>
        <div class="relative max-w-lg">
            <p class="text-[11px] font-semibold uppercase tracking-[.25em] text-cyan-300">Global logistics command center</p>
            <p class="mt-3 text-4xl font-extrabold leading-tight text-white">GLOBAL VISIBILITY.<br>INTELLIGENT LOGISTICS.<br><span class="text-cyan-300">COMPLETE CONTROL.</span></p>
            <p class="mt-5 text-slate-500">Track every route, detect delays with transparent rules, and resolve exceptions before they reach your customers.</p>
        </div>
        <p class="relative text-xs text-slate-400">Web Programming 2 · UAS · {{ config('rekacy.group.name') }}</p>
    </div>
    <div class="flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex items-center gap-3 lg:hidden"><x-logo :size="36" /><span class="text-xl font-extrabold text-white">Reka<span class="text-cyan-300">Cy</span></span></div>
            <h1 class="text-2xl font-bold text-slate-900">Sign in</h1><p class="mt-1 text-sm text-slate-500">Access your RekaCy workspace.</p>
            @if (session('status'))<p class="mt-4 rounded-lg bg-emerald-500/10 px-3 py-2 text-sm text-emerald-300 ring-1 ring-emerald-400/30">{{ session('status') }}</p>@endif
            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">@csrf
                <div><label for="email" class="label">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input">@error('email')<p class="mt-1 text-sm text-red-300">{{ $message }}</p>@enderror</div>
                <div><div class="flex justify-between"><label for="password" class="label">Password</label><a href="{{ route('password.request') }}" class="text-[11px] link">Forgot?</a></div><input id="password" type="password" name="password" required autocomplete="current-password" class="input"></div>
                <label class="flex items-center gap-2 text-sm text-slate-500"><input type="checkbox" name="remember" class="rounded border-line bg-deep text-cyan-400 focus:ring-cyan-400"> Keep me signed in</label>
                <button class="btn-primary w-full">Sign in</button>
            </form>
            <p class="mt-6 text-center text-sm text-slate-500">New customer? <a href="{{ route('register') }}" class="link">Create an account</a></p>
            <p class="mt-2 text-center text-sm"><a href="{{ route('track.public') }}" class="text-slate-500 hover:text-cyan-300">Track a shipment without signing in →</a> · <a href="{{ route('about') }}" class="text-slate-500 hover:text-cyan-300">About</a></p>
            @if (app()->environment('local', 'testing'))
                <div class="mt-8 rounded-lg bg-deep p-3 text-xs text-slate-500 ring-1 ring-line"><p class="mb-1 font-semibold text-slate-700">Demo accounts (local only) — password: <code>password</code></p>admin@rekacy.test · operator@rekacy.test · customer@rekacy.test</div>
            @endif
        </div>
    </div>
</div>
@endsection
