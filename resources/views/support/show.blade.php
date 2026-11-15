@extends('layouts.app')
@section('title', $ticket->reference)

@section('content')
<div class="grid gap-3 lg:grid-cols-3">
    <div class="space-y-3 lg:col-span-2">
        <section class="card"><div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-extrabold text-slate-900">{{ $ticket->subject }}</h2><span class="badge {{ $ticket->status->badgeClasses() }}">{{ $ticket->status->label() }}</span></div>
            <p class="mt-1 text-xs text-slate-500">{{ $ticket->requester->name }} · {{ $ticket->category }} · priority {{ $ticket->priority }}@if($ticket->shipment) · <a class="link" href="{{ route('shipments.show', $ticket->shipment) }}">{{ $ticket->shipment->shipment_number }}</a>@endif</p></section>
        @foreach ($replies as $r)
            @php $mine = $r->user_id === auth()->id(); @endphp
            <section class="card {{ $r->is_internal ? '!border-gold/40' : '' }}">
                <div class="flex items-center justify-between text-xs"><span class="font-semibold text-slate-900">{{ $r->author?->name ?? 'Unknown' }} @if($r->author?->isStaff())<span class="badge bg-cyan-500/10 text-cyan-300 ring-cyan-400/30">Staff</span>@endif @if($r->is_internal)<span class="badge bg-gold/10 text-gold ring-gold/30">Internal note</span>@endif</span><span class="text-slate-500">{{ $r->created_at->format('d M Y H:i') }}</span></div>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $r->body }}</p>
            </section>
        @endforeach
        @if ($ticket->status !== \App\Enums\TicketStatus::Closed)
        <form method="POST" action="{{ route('support.reply', $ticket) }}" class="card space-y-3">@csrf
            <label class="label" for="body">Reply</label><textarea id="body" name="body" rows="4" required maxlength="4000" class="input"></textarea>
            <div class="flex items-center justify-between">@if(auth()->user()->isStaff())<label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="is_internal" value="1" class="rounded border-line bg-deep text-cyan-400"> Internal note (hidden from customer)</label>@else<span></span>@endif<button class="btn-primary">Send reply</button></div>
        </form>
        @endif
    </div>
    @if (auth()->user()->isStaff())
    <form method="POST" action="{{ route('support.update', $ticket) }}" class="card space-y-3 self-start">@csrf @method('PATCH')
        <h3 class="card-title">Manage ticket</h3>
        <div><label class="label" for="st">Status</label><select id="st" name="status" class="input">@foreach (\App\Enums\TicketStatus::cases() as $s)<option value="{{ $s->value }}" @selected($ticket->status === $s)>{{ $s->label() }}</option>@endforeach</select></div>
        <div><label class="label" for="pr">Priority</label><select id="pr" name="priority" class="input">@foreach (['LOW', 'MEDIUM', 'HIGH'] as $p)<option @selected($ticket->priority === $p)>{{ $p }}</option>@endforeach</select></div>
        <div><label class="label" for="as">Assign to</label><select id="as" name="assigned_to" class="input"><option value="">Unassigned</option>@foreach ($operators as $o)<option value="{{ $o->id }}" @selected($ticket->assigned_to === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
        <button class="btn-primary w-full">Save</button>
    </form>
    @endif
</div>
@endsection
