<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#070d18">
    <title>@yield('title', 'Tugas Kurir') · RekaCy</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">
    @include('partials.realtime-config')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-full bg-base pb-24 font-sans text-slate-700 antialiased">
@php
    $u = auth()->user();
    $unread = $u->unreadNotifications()->count();
    $tabs = [
        ['Tugas', 'courier.index', 'courier.index', 'truck'],
        ['Riwayat', 'courier.history', 'courier.history', 'list'],
        ['Notifikasi', 'notifications.index', 'notifications.*', 'bell'],
        ['Profil', 'profile.edit', 'profile.*', 'user'],
    ];
@endphp
<header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-line bg-base/90 px-4 py-3 backdrop-blur" style="padding-top: max(.75rem, env(safe-area-inset-top))">
    <div class="flex items-center gap-2.5"><x-logo :size="30" /><div class="leading-tight"><p class="text-[15px] font-extrabold text-white">Reka<span class="text-cyan-300">Cy</span></p><p class="text-[10px] uppercase tracking-widest text-slate-500">Kurir</p></div></div>
    <div class="flex items-center gap-2"><span id="rtPill"></span>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg p-2 text-slate-500 hover:text-red-300" aria-label="Keluar"><x-icon name="logout" /></button></form></div>
</header>

<main class="mx-auto max-w-xl p-4">
    <x-flash />
    @yield('content')
</main>

<nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-deep/95 backdrop-blur" style="padding-bottom: env(safe-area-inset-bottom)" aria-label="Navigasi kurir">
    <ul class="mx-auto grid max-w-xl grid-cols-4">
        @foreach ($tabs as [$label, $route, $pattern, $icon])
            @php $on = request()->routeIs($pattern) || ($route === 'courier.index' && request()->routeIs('courier.show')); @endphp
            <li><a href="{{ route($route) }}" class="relative flex flex-col items-center gap-0.5 py-2.5 text-[11px] font-semibold {{ $on ? 'text-cyan-300' : 'text-slate-500' }}">
                <x-icon :name="$icon" class="h-6 w-6" />{{ $label }}
                @if ($route === 'notifications.index')<span id="bellBadge" class="absolute right-[28%] top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white {{ $unread ? '' : 'hidden' }}">{{ $unread }}</span>@endif
            </a></li>
        @endforeach
    </ul>
</nav>
@include('partials.realtime')
@stack('scripts')
</body>
</html>
