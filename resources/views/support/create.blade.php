@extends('layouts.app')
@section('title', 'New support ticket')

@section('content')
<form method="POST" action="{{ route('support.store') }}" class="card mx-auto max-w-2xl space-y-4">@csrf
    <div><label class="label" for="subject">Subject</label><input id="subject" name="subject" value="{{ old('subject') }}" required maxlength="160" class="input"></div>
    <div class="grid gap-4 sm:grid-cols-3">
        <div><label class="label" for="category">Category</label><select id="category" name="category" class="input">@foreach (['GENERAL', 'DELAY', 'DAMAGE', 'DOCUMENT', 'BILLING'] as $c)<option @selected(old('category') === $c)>{{ $c }}</option>@endforeach</select></div>
        <div><label class="label" for="priority">Priority</label><select id="priority" name="priority" class="input">@foreach (['LOW', 'MEDIUM', 'HIGH'] as $p)<option @selected(old('priority', 'MEDIUM') === $p)>{{ $p }}</option>@endforeach</select></div>
        <div><label class="label" for="shipment_id">Shipment (optional)</label><select id="shipment_id" name="shipment_id" class="input"><option value="">—</option>@foreach ($shipments as $s)<option value="{{ $s->id }}" @selected(old('shipment_id') == $s->id || request('shipment') == $s->id)>{{ $s->shipment_number }}</option>@endforeach</select></div>
    </div>
    <div><label class="label" for="body">Describe the issue</label><textarea id="body" name="body" rows="6" required maxlength="4000" class="input">{{ old('body') }}</textarea></div>
    <div class="flex justify-end gap-2"><a href="{{ route('support.index') }}" class="btn-ghost">Cancel</a><button class="btn-primary">Submit ticket</button></div>
</form>
@endsection
