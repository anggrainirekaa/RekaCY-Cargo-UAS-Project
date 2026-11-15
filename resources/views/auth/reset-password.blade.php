@extends('layouts.guest')
@section('title', 'Choose a new password · RekaCy')
@section('content')
<div class="flex min-h-full items-center justify-center px-6 py-12"><div class="card w-full max-w-md p-8">
    <h1 class="text-xl font-bold text-slate-900">Choose a new password</h1><x-flash />
    <form method="POST" action="{{ route('password.update') }}" class="mt-5 space-y-4">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div><label class="label" for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $email) }}" required class="input"></div>
        <div><label class="label" for="password">New password</label><input id="password" type="password" name="password" required class="input"></div>
        <div><label class="label" for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" required class="input"></div>
        <button class="btn-primary w-full">Reset password</button></form>
</div></div>
@endsection
