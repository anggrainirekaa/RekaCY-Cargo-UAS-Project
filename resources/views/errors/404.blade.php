@extends('layouts.guest')
@section('title', '404 · RekaCy')
@section('content')
<div class="flex min-h-full flex-col items-center justify-center px-6 py-16 text-center">
    <x-logo :size="48" />
    <p class="mt-6 text-7xl font-extrabold text-cyan-300" style="text-shadow:0 0 30px rgba(34,211,238,.4)">404</p>
    <h1 class="mt-2 text-2xl font-bold text-slate-900">Page not found</h1>
    <p class="mt-2 max-w-md text-sm text-slate-500">The page you are looking for does not exist or has moved.</p>
    <div class="mt-6 flex gap-3"><a href="{{ url('/') }}" class="btn-primary">Back to RekaCy</a><a href="javascript:history.back()" class="btn-ghost">Go back</a></div>
</div>
@endsection
