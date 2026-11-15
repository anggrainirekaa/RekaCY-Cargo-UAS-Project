@extends('layouts.app')
@section('title', 'Search')

@section('content')
<form method="GET" class="card mb-3 flex gap-2"><input name="q" value="{{ $q }}" placeholder="Shipment number, cargo, customer, carrier, location…" class="input" autofocus aria-label="Search"><button class="btn-primary">Search</button></form>
@php $groups = ['shipments' => 'Shipments', 'customers' => 'Customers', 'carriers' => 'Carriers', 'locations' => 'Locations']; $any = collect($results)->flatten(1)->isNotEmpty(); @endphp
@if ($q === '')<p class="py-16 text-center text-sm text-slate-500">Type at least 2 characters to search.</p>
@elseif (! $any)<p class="py-16 text-center text-sm text-slate-500">No results for “{{ $q }}”.</p>
@else
<div class="grid gap-3 md:grid-cols-2">
    @foreach ($groups as $key => $title)@if (count($results[$key]))
        <section class="card"><h2 class="card-title">{{ $title }}</h2>
            <ul class="mt-2 divide-y divide-line">@foreach ($results[$key] as $r)<li><a href="{{ $r['url'] }}" class="block py-2 hover:text-cyan-300"><span class="font-semibold text-slate-900">{{ $r['title'] }}</span><span class="block text-xs text-slate-500">{{ $r['sub'] }}</span></a></li>@endforeach</ul></section>
    @endif @endforeach
</div>
@endif
@endsection
