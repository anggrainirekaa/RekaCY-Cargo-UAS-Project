@extends(auth()->user()->isCourier() ? 'layouts.courier' : 'layouts.app')
@section('title', 'Profile settings')

@section('content')
<div class="grid gap-3 lg:grid-cols-2">
    <form method="POST" action="{{ route('profile.update') }}" class="card space-y-4">@csrf @method('PATCH')
        <h2 class="card-title">Account</h2>
        <div><label class="label" for="name">Name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required class="input"></div>
        <div><label class="label" for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required class="input"></div>
        <div class="grid grid-cols-2 gap-3"><div><label class="label" for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="input"></div><div><label class="label" for="company">Company</label><input id="company" name="company" value="{{ old('company', $user->company) }}" class="input"></div></div>
        <p class="text-xs text-slate-500">Role: <b class="text-slate-700">{{ $user->role->label() }}</b> (changed by an administrator only)</p>
        <button class="btn-primary">Save profile</button>
    </form>
    <form method="POST" action="{{ route('profile.password') }}" class="card space-y-4 self-start">@csrf @method('PUT')
        <h2 class="card-title">Change password</h2>
        <div><label class="label" for="current_password">Current password</label><input id="current_password" type="password" name="current_password" required autocomplete="current-password" class="input"></div>
        <div><label class="label" for="password">New password</label><input id="password" type="password" name="password" required autocomplete="new-password" class="input"></div>
        <div><label class="label" for="password_confirmation">Confirm</label><input id="password_confirmation" type="password" name="password_confirmation" required class="input"></div>
        <button class="btn-primary">Update password</button>
    </form>
</div>
@endsection
