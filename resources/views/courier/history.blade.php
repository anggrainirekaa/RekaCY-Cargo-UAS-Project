@extends('layouts.courier')
@section('title', 'Riwayat')

@section('content')
<h1 class="mb-3 text-lg font-bold text-slate-900">Riwayat pengiriman</h1>
<div class="space-y-2">
    @forelse ($tasks as $t)
        <div class="card !p-3"><div class="flex items-center justify-between gap-2"><p class="font-bold text-slate-900">{{ $t->shipment->shipment_number }}</p><span class="badge {{ $t->status->badgeClasses() }}">{{ $t->status->labelId() }}</span></div>
            <p class="text-xs text-slate-500">{{ $t->shipment->origin->city }} → {{ $t->shipment->destination->city }} · {{ ($t->completed_at ?? $t->updated_at)->format('d M Y H:i') }}</p>
            @if ($t->recipient_name)<p class="text-xs text-slate-500">Diterima oleh {{ $t->recipient_name }}</p>@endif
            @if ($t->failure_reason)<p class="text-xs text-red-300">Alasan: {{ $t->failure_reason }}</p>@endif</div>
    @empty<div class="card py-12 text-center text-sm text-slate-500">Belum ada riwayat.</div>@endforelse
</div>
<div class="mt-3">{{ $tasks->links() }}</div>
@endsection
