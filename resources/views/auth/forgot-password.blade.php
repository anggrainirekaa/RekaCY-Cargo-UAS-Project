@extends('layouts.guest')
@section('title', 'Forgot password · RekaCy')
@section('content')
<div class="flex min-h-full items-center justify-center px-6 py-12"><div class="card w-full max-w-md p-8">
    <div class="mb-5 flex items-center gap-3"><x-logo :size="34" /><span class="text-xl font-extrabold text-white">Reka<span class="text-cyan-300">Cy</span></span></div>
    <h1 class="text-xl font-bold text-slate-900">Reset your password</h1><p class="mt-1 text-sm text-slate-500">Enter your email and we will send a reset link.</p>
    <x-flash />
    <form method="POST" action="{{ route('password.email') }}" class="mt-5 space-y-4">@csrf
        <div><label class="label" for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="input"></div>
        <button class="btn-primary w-full">Send reset link</button></form>
    <p class="mt-5 text-center text-sm"><a href="{{ route('login') }}" class="link">Back to sign in</a></p>
</div></div>
@endsection
