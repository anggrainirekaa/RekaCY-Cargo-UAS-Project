<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    // `role` and `is_active` are deliberately NOT mass-assignable (privilege escalation guard).
    protected $fillable = ['name', 'email', 'password', 'phone', 'company'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isOperator(): bool
    {
        return $this->role === Role::Operator;
    }

    public function isCustomer(): bool
    {
        return $this->role === Role::Customer;
    }

    public function isCourier(): bool
    {
        return $this->role === Role::Courier;
    }

    public function courier(): HasOne
    {
        return $this->hasOne(Courier::class);
    }

    /** Admin or logistics operator. */
    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isOperator();
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'customer_id');
    }
}
