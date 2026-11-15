@extends('layouts.app')
@section('title', 'System Settings')

@section('content')
<div class="grid gap-3 lg:grid-cols-2">
    <section class="card"><h2 class="card-title">System health</h2>
        <ul class="mt-3 divide-y divide-line">@foreach ($checks as [$name, $value, $ok])
            <li class="flex items-center justify-between gap-3 py-2.5 text-sm"><span class="text-slate-600">{{ $name }}</span><span class="flex items-center gap-2 font-medium {{ $ok ? 'text-emerald-300' : 'text-red-300' }}"><span class="h-2 w-2 rounded-full {{ $ok ? 'bg-emerald-400' : 'bg-red-500' }}"></span>{{ $value }}</span></li>
        @endforeach</ul></section>
    <section class="card"><h2 class="card-title">Business configuration</h2><p class="card-sub">Read-only. Change values in <code>.env</code> and run <code>php artisan config:clear</code>.</p>
        <dl class="mt-3 divide-y divide-line">@foreach ($config as $k => $v)<div class="flex justify-between gap-3 py-2.5 text-sm"><dt class="text-slate-500">{{ $k }}</dt><dd class="text-right font-medium text-slate-800">{{ $v }}</dd></div>@endforeach</dl></section>
</div>
@endsection
