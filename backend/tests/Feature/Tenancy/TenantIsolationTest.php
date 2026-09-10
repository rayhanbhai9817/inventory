<?php

namespace Tests\Feature\Tenancy;

use App\Models\Business;
use App\Models\Category;
use App\Models\Unit;
use App\Support\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function registerBusiness(string $name, string $email): array
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'business_name' => $name,
            'name' => 'Owner',
            'email' => $email,
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])->assertCreated();

        return [
            'token' => $response->json('token'),
            'business_id' => $response->json('business.id'),
        ];
    }

    public function test_a_business_cannot_see_another_businesss_products(): void
    {
        $businessA = $this->registerBusiness('Business A', 'ownerA@test.com');
        $businessB = $this->registerBusiness('Business B', 'ownerB@test.com');

        // Create a unit + product directly for business A.
        Tenant::set($businessA['business_id']);
        $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc']);
        $productResponse = $this->asBearerToken($businessA['token'])
            ->postJson('/api/v1/products', [
                'name' => 'Secret Widget',
                'sku' => 'SEC-001',
                'unit_id' => $unit->id,
                'cost_price' => 1,
                'selling_price' => 2,
            ])->assertCreated();

        $productId = $productResponse->json('data.id');

        // Business B must not be able to see, update, or delete it.
        $this->asBearerToken($businessB['token'])
            ->getJson("/api/v1/products/{$productId}")
            ->assertNotFound();

        $this->asBearerToken($businessB['token'])
            ->putJson("/api/v1/products/{$productId}", ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->asBearerToken($businessB['token'])
            ->deleteJson("/api/v1/products/{$productId}")
            ->assertNotFound();

        // Business B's own product index must be empty.
        $this->asBearerToken($businessB['token'])
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Business A still sees exactly its own product.
        $this->asBearerToken($businessA['token'])
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_category_slug_uniqueness_is_scoped_per_business_not_global(): void
    {
        $businessA = $this->registerBusiness('Business A', 'ownerA2@test.com');
        $businessB = $this->registerBusiness('Business B', 'ownerB2@test.com');

        $this->asBearerToken($businessA['token'])
            ->postJson('/api/v1/categories', ['name' => 'Electronics'])
            ->assertCreated();

        // Same category name in a different business must be allowed.
        $this->asBearerToken($businessB['token'])
            ->postJson('/api/v1/categories', ['name' => 'Electronics'])
            ->assertCreated();

        $this->assertSame(2, Category::withoutGlobalScope('tenant')->count());
    }
}
