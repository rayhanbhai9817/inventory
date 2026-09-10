<?php

namespace Tests\Feature\Supplier;

use App\Support\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierProductTest extends TestCase
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

    public function test_a_supplier_can_be_linked_to_a_product_and_unlinked(): void
    {
        $owner = $this->registerBusiness('Acme', 'owner@acme.test');

        $supplier = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/suppliers', ['name' => 'Global Supply Co'])
            ->assertCreated();

        $product = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/products', ['name' => 'Widget', 'sku' => 'W-1'])
            ->assertCreated();

        $link = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/supplier-products', [
                'supplier_id' => $supplier->json('data.id'),
                'product_id' => $product->json('data.id'),
                'supplier_sku' => 'GSC-W1',
                'is_primary' => true,
            ])->assertCreated();

        $this->asBearerToken($owner['token'])
            ->getJson('/api/v1/supplier-products?product_id='.$product->json('data.id'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->asBearerToken($owner['token'])
            ->deleteJson('/api/v1/supplier-products/'.$link->json('data.id'))
            ->assertOk();

        $this->asBearerToken($owner['token'])
            ->getJson('/api/v1/supplier-products?product_id='.$product->json('data.id'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_only_one_supplier_product_link_can_be_primary_per_product(): void
    {
        $owner = $this->registerBusiness('Acme', 'owner2@acme.test');

        $supplierA = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/suppliers', ['name' => 'Supplier A'])->assertCreated();
        $supplierB = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/suppliers', ['name' => 'Supplier B'])->assertCreated();

        $product = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/products', ['name' => 'Widget', 'sku' => 'W-2'])
            ->assertCreated();

        $linkA = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/supplier-products', [
                'supplier_id' => $supplierA->json('data.id'),
                'product_id' => $product->json('data.id'),
                'is_primary' => true,
            ])->assertCreated();

        $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/supplier-products', [
                'supplier_id' => $supplierB->json('data.id'),
                'product_id' => $product->json('data.id'),
                'is_primary' => true,
            ])->assertCreated();

        $this->assertDatabaseHas('supplier_products', ['id' => $linkA->json('data.id'), 'is_primary' => false]);
    }

    public function test_duplicate_supplier_product_link_is_rejected(): void
    {
        $owner = $this->registerBusiness('Acme', 'owner3@acme.test');

        $supplier = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/suppliers', ['name' => 'Global Supply Co'])->assertCreated();
        $product = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/products', ['name' => 'Widget', 'sku' => 'W-3'])->assertCreated();

        $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/supplier-products', [
                'supplier_id' => $supplier->json('data.id'),
                'product_id' => $product->json('data.id'),
            ])->assertCreated();

        $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/supplier-products', [
                'supplier_id' => $supplier->json('data.id'),
                'product_id' => $product->json('data.id'),
            ])->assertStatus(422);
    }

    public function test_supplier_product_links_are_tenant_isolated(): void
    {
        $businessA = $this->registerBusiness('Business A', 'ownerA@test.com');
        $businessB = $this->registerBusiness('Business B', 'ownerB@test.com');

        Tenant::set($businessA['business_id']);
        $supplier = $this->asBearerToken($businessA['token'])
            ->postJson('/api/v1/suppliers', ['name' => 'A Supplier'])->assertCreated();
        $product = $this->asBearerToken($businessA['token'])
            ->postJson('/api/v1/products', ['name' => 'A Widget', 'sku' => 'A-1'])->assertCreated();

        $this->asBearerToken($businessA['token'])
            ->postJson('/api/v1/supplier-products', [
                'supplier_id' => $supplier->json('data.id'),
                'product_id' => $product->json('data.id'),
            ])->assertCreated();

        $this->asBearerToken($businessB['token'])
            ->getJson('/api/v1/supplier-products')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
