@extends('layouts.guest')
@section('title', 'Create account · RekaCy')
@section('content')
<div class="flex min-h-full items-center justify-center bg-slate-50 px-6 py-12">
    <div class="w-full max-w-md rounded-2xl bg-panel p-8 shadow-sm ring-1 ring-slate-200">
        <div class="mb-6 flex items-center gap-3"><x-logo :size="36" /><span class="text-xl font-extrabold">RekaCy</span></div>
        <h1 class="text-xl font-bold text-slate-900">Create a customer account</h1>
        <p class="mt-1 text-sm text-slate-500">Customers can follow their own shipments, timelines and ETAs.</p>
        <x-flash />
        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
            @csrf
            <div><label class="label" for="name">Full name</label><input id="name" name="name" value="{{ old('name') }}" required class="input"></div>
            <div><label class="label" for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required class="input"></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="label" for="company">Company</label><input id="company" name="company" value="{{ old('company') }}" class="input"></div>
                <div><label class="label" for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone') }}" class="input"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="label" for="password">Password</label><input id="password" type="password" name="password" required class="input"></div>
                <div><label class="label" for="password_confirmation">Confirm</label><input id="password_confirmation" type="password" name="password_confirmation" required class="input"></div>
            </div>
            <p class="text-xs text-slate-500">Minimum 8 characters, with letters and numbers.</p>
            <button class="btn-primary w-full">Create account</button>
        </form>
        <p class="mt-6 text-center text-sm text-slate-500">Already registered? <a href="{{ route('login') }}" class="font-semibold text-cyan-300 hover:underline">Sign in</a></p>
    </div>
</div>
@endsection
