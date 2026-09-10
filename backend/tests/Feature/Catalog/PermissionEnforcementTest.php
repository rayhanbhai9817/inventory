<?php

namespace Tests\Feature\Catalog;

use App\Models\Unit;
use App\Models\User;
use App\Support\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_staff_role_cannot_delete_products_but_can_view_and_sell(): void
    {
        $register = $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Acme Retail',
            'name' => 'Owner',
            'email' => 'owner@acme.test',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])->assertCreated();

        $businessId = $register->json('business.id');

        Tenant::set($businessId);
        app(PermissionRegistrar::class)->setPermissionsTeamId($businessId);

        $staff = User::create([
            'business_id' => $businessId,
            'name' => 'Staff Member',
            'email' => 'staff@acme.test',
            'password' => 'correct-horse-battery-staple',
        ]);
        $staff->assignRole('Staff');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@acme.test',
            'password' => 'correct-horse-battery-staple',
        ])->assertOk();

        $staffToken = $login->json('token');
        $ownerToken = $register->json('token');

        $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc']);
        $product = $this->asBearerToken($ownerToken)
            ->postJson('/api/v1/products', [
                'name' => 'Widget',
                'sku' => 'W-1',
                'unit_id' => $unit->id,
                'cost_price' => 1,
                'selling_price' => 2,
            ])->assertCreated();

        $productId = $product->json('data.id');

        // Staff can view.
        $this->asBearerToken($staffToken)
            ->getJson("/api/v1/products/{$productId}")
            ->assertOk();

        // Staff cannot delete (no products.delete permission).
        $this->asBearerToken($staffToken)
            ->deleteJson("/api/v1/products/{$productId}")
            ->assertStatus(403);

        // Staff cannot create suppliers (no suppliers.create permission).
        $this->asBearerToken($staffToken)
            ->postJson('/api/v1/suppliers', ['name' => 'Rogue Supplier'])
            ->assertStatus(403);

        // Owner can delete.
        $this->asBearerToken($ownerToken)
            ->deleteJson("/api/v1/products/{$productId}")
            ->assertOk();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/products')->assertStatus(401);
    }
}
