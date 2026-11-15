@extends('layouts.app')
@section('title', 'Users')
@section('subtitle', 'Accounts, roles and access status')
@section('content')
<div class="grid gap-4 lg:grid-cols-3">
    <div class="card overflow-hidden p-0 lg:col-span-2">
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50"><tr><th class="th">User</th><th class="th">Role</th><th class="th">Active</th><th class="th"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($users as $u)
                <tr>
                    <td class="td"><p class="font-semibold text-slate-900">{{ $u->name }}</p><p class="text-xs text-slate-500">{{ $u->email }}{{ $u->company ? ' · '.$u->company : '' }}</p></td>
                    <form method="POST" action="{{ route('users.update', $u) }}">@csrf @method('PATCH')
                        <td class="td"><select name="role" class="input py-1.5" aria-label="Role for {{ $u->name }}">@foreach (\App\Enums\Role::cases() as $r)<option value="{{ $r->value }}" @selected($u->role === $r)>{{ $r->label() }}</option>@endforeach</select></td>
                        <td class="td"><select name="is_active" class="input py-1.5" aria-label="Active for {{ $u->name }}"><option value="1" @selected($u->is_active)>Active</option><option value="0" @selected(! $u->is_active)>Disabled</option></select></td>
                        <td class="td text-right"><button class="btn-ghost py-1.5">Save</button></td>
                    </form>
                </tr>
            @endforeach
            </tbody></table></div>
    </div>
    <form method="POST" action="{{ route('users.store') }}" class="card space-y-3 self-start">
        @csrf
        <h2 class="card-title">Create user</h2>
        <div><label class="label" for="name">Name</label><input id="name" name="name" value="{{ old('name') }}" required class="input"></div>
        <div><label class="label" for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required class="input"></div>
        <div><label class="label" for="company">Company</label><input id="company" name="company" value="{{ old('company') }}" class="input"></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="label" for="role">Role</label><select id="role" name="role" class="input">@foreach (\App\Enums\Role::cases() as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach</select></div>
            <div><label class="label" for="password">Password</label><input id="password" type="password" name="password" required class="input"></div>
        </div>
        <button class="btn-primary w-full">Create user</button>
    </form>
</div>
@endsection
