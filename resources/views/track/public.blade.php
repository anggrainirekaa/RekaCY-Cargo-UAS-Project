@extends('layouts.guest')
@section('title', 'Track a shipment · RekaCy')
@section('content')
<div class="min-h-full bg-slate-50">
    <header class="bg-base px-6 py-10 text-white">
        <div class="mx-auto flex max-w-3xl flex-col gap-6">
            <a href="{{ route('login') }}" class="flex items-center gap-3"><x-logo :size="36" /><span class="text-lg font-extrabold tracking-wide">RekaCy</span></a>
            <h1 class="text-3xl font-extrabold">Track your shipment</h1>
            <form method="GET" action="{{ route('track.public') }}" class="flex gap-2" role="search">
                <input name="number" value="{{ $number }}" placeholder="e.g. RCY-2026-000001" class="input flex-1 py-3" aria-label="Shipment number" required>
                <button class="btn-primary px-6">Track</button>
            </form>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-6 py-8">
        @if ($number !== '' && ! $shipment)
            <div class="rounded-2xl bg-panel p-8 text-center ring-1 ring-slate-200">
                <p class="font-semibold text-slate-900">No shipment found for “{{ $number }}”.</p>
                <p class="mt-1 text-sm text-slate-500">Check the number on your booking confirmation and try again.</p>
            </div>
        @elseif ($shipment)
            <div class="rounded-2xl bg-panel p-6 ring-1 ring-slate-200">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Shipment</p><p class="text-xl font-extrabold text-slate-900">{{ $shipment->shipment_number }}</p></div>
                    <x-status-badge :status="$shipment->status" class="text-sm" />
                </div>
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-slate-500">From</dt><dd class="font-semibold">{{ $shipment->origin->city }}, {{ $shipment->origin->country }}</dd></div>
                    <div><dt class="text-slate-500">To</dt><dd class="font-semibold">{{ $shipment->destination->city }}, {{ $shipment->destination->country }}</dd></div>
                    <div><dt class="text-slate-500">{{ $shipment->delivered_at ? 'Delivered' : 'Estimated arrival' }}</dt><dd class="font-semibold">{{ ($shipment->delivered_at ?? $shipment->estimated_arrival_at ?? $shipment->expected_arrival_at)->format('d M Y') }}</dd></div>
                </dl>
                @if ($shipment->is_delayed && ! $shipment->delivered_at)
                    <p class="mt-4 rounded-xl bg-amber-500/10 px-4 py-3 text-sm text-amber-300 ring-1 ring-amber-400/30">This shipment is running behind its original schedule.</p>
                @endif
            </div>

            <div class="mt-4 rounded-2xl bg-panel p-6 ring-1 ring-slate-200">
                <h2 class="text-sm font-semibold text-slate-900">Journey</h2>
                <ol class="relative mt-4 ml-2 space-y-5 border-l-2 border-slate-200 pl-6">
                    @foreach ($shipment->trackingEvents as $e)
                        @continue($e->event_type === 'DELAY_DETECTED')
                        <li class="relative">
                            <span class="absolute -left-[33px] top-1 h-4 w-4 rounded-full ring-4 ring-panel {{ $e->is_exception ? 'bg-amber-500' : 'bg-cyan-500' }}"></span>
                            <p class="text-sm font-semibold text-slate-900">{{ $e->status?->label() ?? ucwords(strtolower(str_replace('_', ' ', $e->event_type))) }}</p>
                            <p class="text-xs text-slate-500">{{ $e->occurred_at->format('d M Y, H:i') }}@if($e->location_name) · {{ $e->location_name }}@endif</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
        <p class="mt-8 text-center text-xs text-slate-400">Public view shows status and milestones only. Sign in for full details.</p>
    </main>
</div>
@endsection
