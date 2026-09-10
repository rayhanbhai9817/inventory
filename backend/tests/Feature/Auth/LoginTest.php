<?php

namespace Tests\Feature\Auth;

use App\Models\Business;
use App\Models\User;
use App\Support\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $business = Business::create(['name' => 'Acme', 'slug' => 'acme']);
        Tenant::set($business->id);
        User::create([
            'business_id' => $business->id,
            'name' => 'Alice',
            'email' => 'alice@acme.test',
            'password' => 'correct-horse-battery-staple',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@acme.test',
            'password' => 'correct-horse-battery-staple',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);
    }

    /**
     * Regression test: /auth/login runs before any tenant/team context is
     * established (that's what login itself establishes), so a naive
     * implementation returns a user with empty roles/permissions even
     * though they're a fully-provisioned Owner. This broke the frontend
     * silently — a freshly logged-in user saw an empty sidebar because
     * every nav item is permission-gated — until a full page reload
     * (which re-fetches /auth/me) fixed it. Caught by driving the app in
     * a real browser, not by any static check.
     */
    public function test_login_response_includes_the_users_roles_and_permissions(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Acme Retail',
            'name' => 'Owner',
            'email' => 'owner@acme.test',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])->assertCreated();

        // A fresh, separate request — simulating a real second login,
        // not just reading the register response.
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@acme.test',
            'password' => 'correct-horse-battery-staple',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.roles.0', 'Owner')
            ->assertJsonPath('user.permissions', fn ($permissions) => count($permissions) > 0);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $business = Business::create(['name' => 'Acme', 'slug' => 'acme']);
        Tenant::set($business->id);
        User::create([
            'business_id' => $business->id,
            'name' => 'Alice',
            'email' => 'alice@acme.test',
            'password' => 'correct-horse-battery-staple',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@acme.test',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_login_fails_for_unknown_email_with_generic_message(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@nowhere.test',
            'password' => 'whatever12345',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['email' => ['The provided credentials are incorrect.']]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $business = Business::create(['name' => 'Acme', 'slug' => 'acme']);
        Tenant::set($business->id);
        User::create([
            'business_id' => $business->id,
            'name' => 'Alice',
            'email' => 'alice@acme.test',
            'password' => 'correct-horse-battery-staple',
            'status' => 'inactive',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@acme.test',
            'password' => 'correct-horse-battery-staple',
        ])->assertStatus(422);
    }
}
