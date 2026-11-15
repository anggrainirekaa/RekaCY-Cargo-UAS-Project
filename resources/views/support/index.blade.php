@extends('layouts.app')
@section('title', 'Support Tickets')

@section('content')
<div class="mb-3 flex flex-wrap items-end justify-between gap-3">
    <form method="GET" class="flex items-end gap-2"><div><label class="label" for="status">Status</label><select id="status" name="status" class="input">
        <option value="">All</option>@foreach (\App\Enums\TicketStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div><button class="btn-navy">Filter</button></form>
    <a href="{{ route('support.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> New ticket</a>
</div>
<div class="card !p-0"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-line">
    <thead class="bg-slate-50"><tr><th class="th">Ticket</th><th class="th">Subject</th>@if(auth()->user()->isStaff())<th class="th">Requester</th>@endif<th class="th">Priority</th><th class="th">Status</th><th class="th">Assignee</th><th class="th">Opened</th></tr></thead>
    <tbody class="divide-y divide-line">
    @forelse ($tickets as $t)
        <tr class="hover:bg-slate-50"><td class="td"><a class="link" href="{{ route('support.show', $t) }}">{{ $t->reference }}</a></td><td class="td">{{ $t->subject }}@if($t->shipment)<p class="text-xs text-slate-500">{{ $t->shipment->shipment_number }}</p>@endif</td>
            @if(auth()->user()->isStaff())<td class="td">{{ $t->requester->name }}</td>@endif
            <td class="td">{{ $t->priority }}</td><td class="td"><span class="badge {{ $t->status->badgeClasses() }}">{{ $t->status->label() }}</span></td><td class="td">{{ $t->assignee?->name ?? '—' }}</td><td class="td whitespace-nowrap">{{ $t->created_at->format('d M Y') }}</td></tr>
    @empty<tr><td colspan="7" class="py-14 text-center text-sm text-slate-500">No tickets yet.</td></tr>@endforelse
    </tbody></table></div>
    @if ($tickets->hasPages())<div class="border-t border-line px-4 py-3">{{ $tickets->links() }}</div>@endif
</div>
@endsection
