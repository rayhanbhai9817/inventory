<?php

namespace Tests\Feature\Console;

use App\Models\Business;
use App\Models\User;
use App\Support\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    /**
     * Registers a business the same way it happens in real production —
     * through the public endpoint, which is the only other path that
     * provisions Owner/Manager/Staff roles for a business — rather than
     * hand-crafting a Business row that would never occur in real data.
     */
    private function registerBusiness(string $businessName, string $email): Business
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name' => $businessName,
            'name' => 'Existing User',
            'email' => $email,
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])->assertCreated();

        return Business::where('name', $businessName)->firstOrFail();
    }

    public function test_creates_first_business_and_owner_when_none_exists(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Business name', 'Ozipco Inventory')
            ->expectsQuestion('Name', 'Admin User')
            ->expectsQuestion('Email', 'admin@ozipco.test')
            ->expectsQuestion('Password', 'SecurePass123!')
            ->expectsQuestion('Confirm Password', 'SecurePass123!')
            ->assertExitCode(0);

        $business = Business::where('name', 'Ozipco Inventory')->first();
        $this->assertNotNull($business);

        $user = User::where('email', 'admin@ozipco.test')->first();
        $this->assertNotNull($user);
        $this->assertSame($business->id, $user->business_id);
        $this->assertTrue(str_starts_with($user->password, '$2y$'), 'Password must be bcrypt hashed.');

        Tenant::set($business->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
        $this->assertTrue($user->fresh()->hasRole('Owner'));
        $this->assertTrue($user->fresh()->can('users.create'));
        $this->assertTrue($user->fresh()->can('settings.manage'));
    }

    public function test_rejects_duplicate_email_and_reprompts(): void
    {
        $this->registerBusiness('Existing Co', 'taken@acme.test');

        $this->artisan('app:create-admin')
            ->expectsQuestion('Enter a number to add this admin to that business, or type "new" to create a new business', '1')
            ->expectsQuestion('Name', 'Second Admin')
            ->expectsQuestion('Email', 'taken@acme.test')
            ->expectsQuestion('Email', 'second@acme.test')
            ->expectsQuestion('Password', 'SecurePass123!')
            ->expectsQuestion('Confirm Password', 'SecurePass123!')
            ->assertExitCode(0);

        $this->assertSame(1, User::where('email', 'taken@acme.test')->count());
        $this->assertSame(1, User::where('email', 'second@acme.test')->count());
    }

    public function test_adds_owner_to_an_existing_business_without_creating_a_new_one(): void
    {
        $business = $this->registerBusiness('Existing Co', 'founder@acme.test');

        $this->artisan('app:create-admin')
            ->expectsQuestion('Enter a number to add this admin to that business, or type "new" to create a new business', '1')
            ->expectsQuestion('Name', 'New Owner')
            ->expectsQuestion('Email', 'newowner@acme.test')
            ->expectsQuestion('Password', 'SecurePass123!')
            ->expectsQuestion('Confirm Password', 'SecurePass123!')
            ->assertExitCode(0);

        $this->assertSame(1, Business::count());

        $user = User::where('email', 'newowner@acme.test')->first();
        $this->assertSame($business->id, $user->business_id);

        Tenant::set($business->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
        $this->assertTrue($user->fresh()->hasRole('Owner'));
    }

    public function test_rejects_mismatched_password_confirmation(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Business name', 'Ozipco Inventory')
            ->expectsQuestion('Name', 'Admin User')
            ->expectsQuestion('Email', 'admin@ozipco.test')
            ->expectsQuestion('Password', 'SecurePass123!')
            ->expectsQuestion('Confirm Password', 'DoesNotMatch1!')
            ->expectsQuestion('Password', 'SecurePass123!')
            ->expectsQuestion('Confirm Password', 'SecurePass123!')
            ->assertExitCode(0);

        $this->assertSame(1, User::where('email', 'admin@ozipco.test')->count());
    }
}
