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
