@extends('layouts.courier')
@section('title', 'Tugas saya')

@section('content')
@php $avail = ['AVAILABLE' => ['Tersedia', 'text-emerald-300'], 'ON_DELIVERY' => ['Sedang mengantar', 'text-cyan-300'], 'OFFLINE' => ['Offline', 'text-slate-500']][$courier?->availability ?? 'OFFLINE']; @endphp
<div class="mb-4 grid grid-cols-3 gap-2 text-center">
    <div class="card !p-3"><p class="text-2xl font-extrabold text-cyan-300">{{ $tasks->count() }}</p><p class="text-[10px] uppercase tracking-wider text-slate-500">Tugas aktif</p></div>
    <div class="card !p-3"><p class="text-2xl font-extrabold text-emerald-300">{{ $doneToday }}</p><p class="text-[10px] uppercase tracking-wider text-slate-500">Selesai hari ini</p></div>
    <div class="card !p-3"><p class="text-sm font-bold {{ $avail[1] }}">{{ $avail[0] }}</p><p class="text-[10px] uppercase tracking-wider text-slate-500">Status</p></div>
</div>

<div class="space-y-3">
    @forelse ($tasks as $t)
        <a href="{{ route('courier.show', $t) }}" class="card block active:scale-[.99]">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0"><p class="truncate text-base font-extrabold text-slate-900">{{ $t->shipment->shipment_number }}</p>
                    <p class="truncate text-sm text-slate-500">{{ $t->shipment->origin->city }} → {{ $t->shipment->destination->city }}</p></div>
                <span class="badge shrink-0 {{ $t->status->badgeClasses() }}">{{ $t->status->labelId() }}</span>
            </div>
            <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                <span>{{ $t->shipment->customer->name }}{{ $t->shipment->customer->company ? ' · '.$t->shipment->customer->company : '' }}</span>
                <span>Tiba {{ ($t->shipment->estimated_arrival_at ?? $t->shipment->expected_arrival_at)->format('d M H:i') }}</span>
            </div>
            <p class="mt-3 rounded-lg bg-cyan-400/10 py-2.5 text-center text-sm font-bold text-cyan-300">Buka tugas</p>
        </a>
    @empty
        <div class="card py-14 text-center"><x-icon name="truck" class="mx-auto h-10 w-10 text-slate-400" /><p class="mt-3 font-semibold text-slate-800">Belum ada tugas</p><p class="text-sm text-slate-500">Tugas baru akan muncul di sini dan kamu akan mendapat notifikasi.</p></div>
    @endforelse
</div>
@endsection

@push('scripts')
<script>
// New tasks appear without a refresh: reload the list when a task event for this courier arrives (or poll while offline).
(() => {
    const reload = () => { if (!document.hidden) location.reload(); };
    window.addEventListener('load', () => { if (window.Echo) window.Echo.private('App.Models.User.' + window.RK.userId).listen('.user.notified', () => setTimeout(reload, 1200)); });
    setInterval(() => { if (window.rkState !== 'live') reload(); }, 60000);
})();
</script>
@endpush
