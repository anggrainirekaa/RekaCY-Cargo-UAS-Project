<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(): View
    {
        Gate::authorize('manage-users');

        return view('users.index', ['users' => User::orderBy('role')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-users');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'role' => ['required', Rule::enum(Role::class)],
            'company' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::create(collect($data)->except('role')->all());
        $user->forceFill(['role' => Role::from($data['role'])])->save();
        $this->ensureCourierProfile($user);
        $this->audit->log('user.created', $user, null, ['email' => $user->email, 'role' => $data['role']], $request->user());

        return back()->with('status', "User {$user->name} created.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');

        $data = $request->validate([
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($user->is($request->user()) && (! $data['is_active'] || $data['role'] !== Role::Admin->value)) {
            return back()->withErrors(['business_rule' => 'You cannot deactivate or demote your own account.']);
        }

        $old = ['role' => $user->role->value, 'is_active' => $user->is_active];
        $user->forceFill(['role' => Role::from($data['role']), 'is_active' => (bool) $data['is_active']])->save();

        $this->ensureCourierProfile($user);

        if (! $user->is_active) {
            $user->tokens()->delete(); // revoke API tokens of deactivated users
        }

        $this->audit->log('user.updated', $user, $old, ['role' => $data['role'], 'is_active' => (bool) $data['is_active']], $request->user());

        return back()->with('status', "User {$user->name} updated.");
    }

    private function ensureCourierProfile(User $user): void
    {
        if ($user->isCourier()) {
            \App\Models\Courier::firstOrCreate(['user_id' => $user->id]);
        }
    }
}
