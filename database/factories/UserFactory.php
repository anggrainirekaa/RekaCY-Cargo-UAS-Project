<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone' => fake()->numerify('+62 8##-####-####'),
            'company' => fake()->company(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Customer,
            'is_active' => true,
            'remember_token' => str()->random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::Admin]);
    }

    public function operator(): static
    {
        return $this->state(['role' => Role::Operator]);
    }

    public function courier(): static
    {
        return $this->state(['role' => Role::Courier])->afterCreating(fn (User $u) => \App\Models\Courier::create(['user_id' => $u->id, 'availability' => 'AVAILABLE']));
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
