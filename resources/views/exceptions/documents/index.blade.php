@extends('layouts.app')
@section('title', 'Documents')

@section('content')
<form method="GET" class="card mb-3 flex items-end gap-3"><div><label class="label" for="type">Type</label><select id="type" name="type" class="input">
    <option value="">All</option>@foreach (\App\Enums\DocumentType::cases() as $t)<option value="{{ $t->value }}" @selected(request('type') === $t->value)>{{ $t->label() }}</option>@endforeach</select></div><button class="btn-navy">Filter</button>
    <p class="ml-auto text-xs text-slate-500">Files are stored privately and served only after an access check. Upload from a shipment page.</p></form>
<div class="card !p-0"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-line">
    <thead class="bg-slate-50"><tr><th class="th">Document</th><th class="th">Type</th><th class="th">Shipment</th><th class="th">Size</th><th class="th">Uploaded</th><th class="th"></th></tr></thead>
    <tbody class="divide-y divide-line">
    @forelse ($docs as $d)
        <tr class="hover:bg-slate-50">
            <td class="td"><p class="font-semibold text-slate-900">{{ $d->title }}</p>@if($d->recipient_name)<p class="text-xs text-slate-500">Received by {{ $d->recipient_name }}</p>@endif</td>
            <td class="td"><span class="badge bg-cyan-500/10 text-cyan-300 ring-cyan-400/30">{{ $d->type->label() }}</span></td>
            <td class="td"><a class="link" href="{{ route('shipments.show', $d->shipment_id) }}">{{ $d->shipment->shipment_number }}</a></td>
            <td class="td">{{ $d->humanSize() }}</td><td class="td whitespace-nowrap">{{ $d->created_at->format('d M Y H:i') }}</td>
            <td class="td whitespace-nowrap text-right"><a class="link" href="{{ route('documents.download', [$d, 'inline' => 1]) }}" target="_blank">Preview</a> · <a class="link" href="{{ route('documents.download', $d) }}">Download</a></td>
        </tr>
    @empty<tr><td colspan="6" class="py-14 text-center text-sm text-slate-500">No documents yet.</td></tr>@endforelse
    </tbody></table></div>
    @if ($docs->hasPages())<div class="border-t border-line px-4 py-3">{{ $docs->links() }}</div>@endif
</div>
@endsection
