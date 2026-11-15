@extends('layouts.app')
@section('title', 'Carriers')
@section('subtitle', 'Logistics operators moving your cargo')
@section('content')
<div class="grid gap-4 lg:grid-cols-3">
    <div class="card overflow-hidden p-0 lg:col-span-2">
        <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50"><tr><th class="th">Carrier</th><th class="th">Code</th><th class="th">Mode</th><th class="th">Contact</th><th class="th">Shipments</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($carriers as $c)
                <tr><td class="td font-semibold text-slate-900">{{ $c->name }}</td><td class="td"><code>{{ $c->code }}</code></td><td class="td capitalize">{{ $c->mode }}</td><td class="td">{{ $c->contact_email ?? '—' }}</td><td class="td">{{ $c->shipments_count }}</td></tr>
            @empty
                <tr><td colspan="5" class="py-12 text-center text-sm text-slate-500">No carriers yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @can('manage-master-data')
    <form method="POST" action="{{ route('carriers.store') }}" class="card space-y-3 self-start">
        @csrf
        <h2 class="card-title">Add carrier</h2>
        <div><label class="label" for="name">Name</label><input id="name" name="name" value="{{ old('name') }}" required class="input"></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="label" for="code">Code</label><input id="code" name="code" value="{{ old('code') }}" required maxlength="12" class="input uppercase"></div>
            <div><label class="label" for="mode">Mode</label><select id="mode" name="mode" class="input">@foreach (['sea','air','road','rail'] as $m)<option value="{{ $m }}">{{ ucfirst($m) }}</option>@endforeach</select></div>
        </div>
        <div><label class="label" for="contact_email">Contact email</label><input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email') }}" class="input"></div>
        <button class="btn-primary w-full">Add carrier</button>
    </form>
    @endcan
</div>
@endsection
