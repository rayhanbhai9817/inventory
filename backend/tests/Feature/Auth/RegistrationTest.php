<?php

namespace Tests\Feature\Auth;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_registering_a_business_creates_owner_with_full_permissions(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Acme Retail',
            'name' => 'Alice Owner',
            'email' => 'alice@acme.test',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ]);

        $response->assertCreated()
            ->assertJsonPath('business.name', 'Acme Retail')
            ->assertJsonPath('user.email', 'alice@acme.test')
            ->assertJsonPath('user.roles.0', 'Owner')
            ->assertJsonStructure(['token']);

        $token = $response->json('token');

        $me = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $me->assertOk()->assertJsonPath('user.email', 'alice@acme.test');
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Acme Retail',
            'name' => 'Alice',
            'email' => 'dupe@acme.test',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])->assertCreated();

        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Other Co',
            'name' => 'Bob',
            'email' => 'dupe@acme.test',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])->assertStatus(422);
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Acme Retail',
            'name' => 'Alice',
            'email' => 'alice2@acme.test',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'not-the-same',
        ])->assertStatus(422);
    }
}
