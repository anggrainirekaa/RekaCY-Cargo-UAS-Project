<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_login_redirects_to_dashboard(): void
    {
        User::factory()->create(['email' => 'a@x.test', 'password' => 'secret123']);

        $this->post('/login', ['email' => 'a@x.test', 'password' => 'secret123'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_invalid_login_is_rejected(): void
    {
        User::factory()->create(['email' => 'a@x.test', 'password' => 'secret123']);

        $this->post('/login', ['email' => 'a@x.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create(['email' => 'off@x.test', 'password' => 'secret123']);

        $this->post('/login', ['email' => 'off@x.test', 'password' => 'secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/shipments')->assertRedirect('/login');
    }

    public function test_registration_always_creates_a_customer(): void
    {
        $this->post('/register', [
            'name' => 'Sneaky', 'email' => 's@x.test', 'password' => 'abcd1234', 'password_confirmation' => 'abcd1234', 'role' => 'admin',
        ])->assertRedirect('/dashboard');

        $this->assertSame(Role::Customer, User::where('email', 's@x.test')->first()->role);
    }

    public function test_api_login_returns_token_and_rejects_bad_credentials(): void
    {
        User::factory()->create(['email' => 'api@x.test', 'password' => 'secret123']);

        $this->postJson('/api/login', ['email' => 'api@x.test', 'password' => 'secret123'])
            ->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);

        $this->postJson('/api/login', ['email' => 'api@x.test', 'password' => 'nope'])->assertStatus(401);
        $this->postJson('/api/login', [])->assertStatus(422);
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/shipments')->assertStatus(401);
        $this->getJson('/api/user')->assertStatus(401);
    }

    public function test_api_unknown_resource_returns_json_404(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/shipments/99999')->assertStatus(404)->assertJson(['message' => 'Resource not found.']);
    }
}
