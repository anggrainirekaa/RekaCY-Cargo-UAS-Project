@extends('layouts.app')
@section('title', $ex->type->label().' · '.$ex->shipment->shipment_number)

@section('content')
@php $s = $ex->shipment; @endphp
<div class="card mb-3 flex flex-wrap items-start justify-between gap-3">
    <div>
        <div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-extrabold text-slate-900">{{ $ex->type->label() }}</h2><span class="badge {{ $ex->severity->badgeClasses() }}">{{ $ex->severity->value }}</span><span class="badge {{ $ex->status->badgeClasses() }}">{{ $ex->status->value }}</span></div>
        <p class="mt-1 text-sm text-slate-600">{{ $ex->description }}</p>
        <p class="mt-1 text-xs text-slate-500">Detected {{ $ex->detected_at->format('d M Y H:i') }}@if($ex->resolved_at) · Resolved {{ $ex->resolved_at->format('d M Y H:i') }}@if($ex->resolver) by {{ $ex->resolver->name }}@endif @endif</p>
    </div>
    <a href="{{ route('shipments.show', $s) }}" class="btn-ghost">View shipment {{ $s->shipment_number }}</a>
</div>

<div class="grid gap-3 lg:grid-cols-3">
    <div class="space-y-3 lg:col-span-2">
        <section class="card">
            <h3 class="card-title">Investigation notes</h3><p class="card-sub mb-3">Internal — hidden from customers</p>
            <ul class="space-y-3">
                @forelse ($ex->notes as $n)
                    <li class="rounded-lg bg-deep p-3 ring-1 ring-line"><p class="text-sm text-slate-700">{{ $n->body }}</p><p class="mt-1 text-[11px] text-slate-500">{{ $n->author?->name ?? 'Unknown' }} · {{ $n->created_at->format('d M Y H:i') }}</p></li>
                @empty<li class="py-3 text-center text-sm text-slate-500">No notes yet.</li>@endforelse
            </ul>
            <form method="POST" action="{{ route('exceptions.notes.store', $ex) }}" class="mt-3 flex gap-2">@csrf
                <textarea name="body" rows="2" required maxlength="2000" class="input" placeholder="Add an investigation note…" aria-label="Investigation note"></textarea>
                <button class="btn-primary self-start">Add</button>
            </form>
        </section>

        <section class="card">
            <h3 class="card-title">Related event history</h3><p class="card-sub mb-3">Shipment timeline (append-only)</p>
            <ol class="relative ml-2 space-y-4 border-l-2 border-line pl-5">
                @foreach ($s->trackingEvents as $e)
                    <li class="relative"><span class="absolute -left-[27px] top-1 h-3 w-3 rounded-full ring-4 ring-panel {{ $e->is_exception ? 'bg-gold' : 'bg-cyan-400' }}"></span>
                        <p class="text-sm font-semibold text-slate-900">{{ $e->status?->label() ?? ucwords(strtolower(str_replace('_', ' ', $e->event_type))) }}</p>
                        <p class="text-xs text-slate-500">{{ $e->occurred_at->format('d M Y H:i') }}{{ $e->location_name ? ' · '.$e->location_name : '' }}</p>
                        @if ($e->description)<p class="text-sm text-slate-600">{{ $e->description }}</p>@endif</li>
                @endforeach
            </ol>
        </section>
    </div>

    <div class="space-y-3">
        <section class="card">
            <h3 class="card-title">Manage</h3>
            <form method="POST" action="{{ route('exceptions.update', $ex) }}" class="mt-3 space-y-3">@csrf @method('PATCH')
                <div><label class="label" for="assigned_to">Responsible operator</label>
                    <select id="assigned_to" name="assigned_to" class="input"><option value="">Unassigned</option>@foreach ($operators as $o)<option value="{{ $o->id }}" @selected($ex->assigned_to === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
                <div><label class="label" for="severity">Severity</label>
                    <select id="severity" name="severity" class="input">@foreach (\App\Enums\Severity::cases() as $sv)<option value="{{ $sv->value }}" @selected($ex->severity === $sv)>{{ $sv->value }}</option>@endforeach</select></div>
                @if ($ex->status->allowedTransitions())
                    <div><label class="label" for="status">Status</label>
                        <select id="status" name="status" class="input"><option value="">Keep {{ $ex->status->value }}</option>@foreach ($ex->status->allowedTransitions() as $to)<option value="{{ $to->value }}">Move to {{ $to->value }}</option>@endforeach</select></div>
                    <div><label class="label" for="resolution_note">Resolution note (required to resolve)</label><input id="resolution_note" name="resolution_note" class="input"></div>
                @endif
                <button class="btn-primary w-full">Save changes</button>
            </form>
        </section>
        <section class="card">
            <h3 class="card-title">Shipment</h3>
            <dl class="mt-2 space-y-1.5 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Route</dt><dd>{{ $s->origin->city }} → {{ $s->destination->city }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Carrier</dt><dd>{{ $s->carrier->name }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Customer</dt><dd>{{ $s->customer->name }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Status</dt><dd><x-status-badge :status="$s->status" /></dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Delay</dt><dd><x-delay-badge :shipment="$s" />@unless($s->is_delayed)On time @endunless</dd></div>
            </dl>
        </section>
    </div>
</div>
@endsection
