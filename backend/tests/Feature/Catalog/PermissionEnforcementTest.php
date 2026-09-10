<?php

namespace Tests\Feature\Catalog;

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

    public function test_staff_role_cannot_delete_products_or_adjust_stock_but_can_view_and_move_stock(): void
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

        $product = $this->asBearerToken($ownerToken)
            ->postJson('/api/v1/products', [
                'name' => 'Widget',
                'sku' => 'W-1',
            ])->assertCreated();

        $productId = $product->json('data.id');

        // Staff can view.
        $this->asBearerToken($staffToken)
            ->getJson("/api/v1/products/{$productId}")
            ->assertOk();

        // Staff can perform stock in/out (Staff role grants stock.in/stock.out).
        $this->asBearerToken($staffToken)
            ->postJson('/api/v1/stock/in', [
                'product_id' => $productId,
                'boxes' => 2,
                'units_per_box' => 10,
                'received_at' => now()->toDateString(),
            ])->assertCreated();

        // Staff cannot delete a product (no products.delete permission).
        $this->asBearerToken($staffToken)
            ->deleteJson("/api/v1/products/{$productId}")
            ->assertStatus(403);

        // Staff cannot perform a stock adjustment (no stock.adjust permission).
        $this->asBearerToken($staffToken)
            ->postJson('/api/v1/stock/adjustments', [
                'product_id' => $productId,
                'direction' => 'increase',
                'quantity' => 5,
                'reason' => 'found',
            ])->assertStatus(403);

        // Staff cannot manage users (no users.create permission).
        $this->asBearerToken($staffToken)
            ->postJson('/api/v1/users', ['name' => 'Rogue', 'email' => 'rogue@acme.test', 'password' => 'password1234', 'role' => 'Staff'])
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
