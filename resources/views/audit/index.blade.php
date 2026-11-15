@extends('layouts.app')
@section('title', 'Audit log')
@section('subtitle', 'Who did what, when and to which record')
@section('content')
<form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
    <div><label class="label" for="action">Action starts with</label><input id="action" name="action" value="{{ $filters['action'] ?? '' }}" placeholder="shipment." class="input"></div>
    <div><label class="label" for="entity">Entity</label>
        <select id="entity" name="entity" class="input"><option value="">All</option>@foreach (['Shipment','ShipmentException','User','Carrier','Location'] as $e)<option @selected(($filters['entity'] ?? '') === $e)>{{ $e }}</option>@endforeach</select></div>
    <button class="btn-navy">Filter</button><a href="{{ route('audit.index') }}" class="btn-ghost">Reset</a>
</form>
<div class="card overflow-hidden p-0">
    <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-100">
        <thead class="bg-slate-50"><tr><th class="th">Time</th><th class="th">Actor</th><th class="th">Action</th><th class="th">Entity</th><th class="th">Change</th><th class="th">IP</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($logs as $l)
            <tr class="align-top">
                <td class="td whitespace-nowrap">{{ $l->created_at->format('d M Y H:i:s') }}</td>
                <td class="td">{{ $l->user?->name ?? 'System' }}</td>
                <td class="td"><code class="text-xs">{{ $l->action }}</code></td>
                <td class="td">{{ $l->entity_type }} #{{ $l->entity_id }}</td>
                <td class="td max-w-sm text-xs text-slate-500">
                    @if ($l->old_values)<p><span class="font-semibold">from</span> {{ json_encode($l->old_values, JSON_UNESCAPED_UNICODE) }}</p>@endif
                    @if ($l->new_values)<p><span class="font-semibold">to</span> {{ json_encode($l->new_values, JSON_UNESCAPED_UNICODE) }}</p>@endif
                </td>
                <td class="td text-xs text-slate-400">{{ $l->ip_address ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-16 text-center text-sm text-slate-500">No audit entries yet.</td></tr>
        @endforelse
        </tbody></table></div>
    @if ($logs->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $logs->links() }}</div>@endif
</div>
@endsection
