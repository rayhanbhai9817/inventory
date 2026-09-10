<?php

namespace Tests\Feature\Pricing;

use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPriceTest extends TestCase
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

        Tenant::set($response->json('business.id'));

        return [
            'token' => $response->json('token'),
            'business_id' => $response->json('business.id'),
        ];
    }

    public function test_a_price_can_be_recorded_and_appears_as_current_price(): void
    {
        $owner = $this->registerBusiness('Acme', 'owner@acme.test');

        $product = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/products', ['name' => 'Widget', 'sku' => 'W-1'])
            ->assertCreated();

        $this->asBearerToken($owner['token'])
            ->postJson("/api/v1/products/{$product->json('data.id')}/prices", [
                'price' => '19.99',
                'effective_date' => '2026-01-01',
            ])->assertCreated()
            ->assertJsonPath('data.price', '19.9900');

        $catalog = $this->asBearerToken($owner['token'])
            ->getJson('/api/v1/product-price-catalog')
            ->assertOk();

        $this->assertSame('19.9900', $catalog->json('data.0.current_price'));
    }

    public function test_recording_a_new_price_never_overwrites_prior_history(): void
    {
        $owner = $this->registerBusiness('Acme', 'owner2@acme.test');

        $product = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/products', ['name' => 'Widget', 'sku' => 'W-2'])
            ->assertCreated();

        $productId = $product->json('data.id');

        $this->asBearerToken($owner['token'])
            ->postJson("/api/v1/products/{$productId}/prices", ['price' => '10.00', 'effective_date' => '2026-01-01'])
            ->assertCreated();

        $this->asBearerToken($owner['token'])
            ->postJson("/api/v1/products/{$productId}/prices", ['price' => '12.00', 'effective_date' => '2026-02-01'])
            ->assertCreated();

        $history = $this->asBearerToken($owner['token'])
            ->getJson("/api/v1/products/{$productId}/prices")
            ->assertOk();

        $this->assertCount(2, $history->json('data'));
        $this->assertSame('12.0000', $history->json('data.0.price'));
        $this->assertSame('10.0000', $history->json('data.1.price'));
    }

    public function test_price_history_and_catalog_are_tenant_isolated(): void
    {
        $businessA = $this->registerBusiness('Business A', 'ownerA@test.com');
        $businessB = $this->registerBusiness('Business B', 'ownerB@test.com');

        Tenant::set($businessA['business_id']);
        $product = $this->asBearerToken($businessA['token'])
            ->postJson('/api/v1/products', ['name' => 'A Widget', 'sku' => 'A-1'])
            ->assertCreated();

        $this->asBearerToken($businessA['token'])
            ->postJson("/api/v1/products/{$product->json('data.id')}/prices", ['price' => '5.00', 'effective_date' => '2026-01-01'])
            ->assertCreated();

        $this->asBearerToken($businessB['token'])
            ->getJson('/api/v1/product-price-catalog')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_staff_cannot_view_or_create_prices_but_manager_can(): void
    {
        $owner = $this->registerBusiness('Acme', 'owner3@acme.test');

        $product = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/products', ['name' => 'Widget', 'sku' => 'W-3'])
            ->assertCreated();

        $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/users', [
                'name' => 'Staff Member', 'email' => 'staff3@acme.test',
                'password' => 'correct-horse-battery-staple', 'role' => 'Staff',
            ])->assertCreated();

        $staffToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff3@acme.test', 'password' => 'correct-horse-battery-staple',
        ])->assertOk()->json('token');

        $this->asBearerToken($staffToken)
            ->getJson('/api/v1/product-price-catalog')
            ->assertStatus(403);

        $this->asBearerToken($staffToken)
            ->postJson("/api/v1/products/{$product->json('data.id')}/prices", ['price' => '1.00', 'effective_date' => '2026-01-01'])
            ->assertStatus(403);

        $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/users', [
                'name' => 'Manager', 'email' => 'manager3@acme.test',
                'password' => 'correct-horse-battery-staple', 'role' => 'Manager',
            ])->assertCreated();

        $managerToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager3@acme.test', 'password' => 'correct-horse-battery-staple',
        ])->assertOk()->json('token');

        $this->asBearerToken($managerToken)
            ->postJson("/api/v1/products/{$product->json('data.id')}/prices", ['price' => '1.00', 'effective_date' => '2026-01-01'])
            ->assertCreated();
    }

    /**
     * Critical separation guarantee: recording/changing a product's
     * reference price must NEVER touch inventory quantity, FIFO batch
     * state, the stock ledger, or Inventory Health in any way.
     */
    public function test_price_changes_never_affect_inventory_quantity_or_fifo_state(): void
    {
        $business = Business::create(['name' => 'Acme', 'slug' => 'acme']);
        Tenant::set($business->id);

        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => 'owner4@acme.test',
            'password' => 'password1234',
        ]);

        $product = Product::create([
            'name' => 'Widget', 'sku' => 'W-4', 'min_stock_level' => 10, 'created_by' => $user->id,
        ]);

        $inventory = app(InventoryService::class);
        $inventory->stockIn($product, 2, 100, '2026-01-01', null, $user);

        $balanceBefore = $product->totalRemainingUnits();
        $batch = $product->batches()->first();
        $remainingBefore = $batch->remaining_units;
        $movementCountBefore = $product->movements()->count();

        $product->prices()->create(['price' => 9.99, 'effective_date' => '2026-01-01', 'created_by' => $user->id]);
        $product->prices()->create(['price' => 12.50, 'effective_date' => '2026-02-01', 'created_by' => $user->id]);
        $product->prices()->create(['price' => 8.00, 'effective_date' => '2026-03-01', 'created_by' => $user->id]);

        $this->assertSame($balanceBefore, $product->totalRemainingUnits());
        $this->assertSame($remainingBefore, $batch->fresh()->remaining_units);
        $this->assertSame($movementCountBefore, $product->movements()->count());
        $this->assertSame('full', $batch->fresh()->status);
    }
}
