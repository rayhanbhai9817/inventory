<?php

namespace Tests\Feature\Supplier;

use App\Support\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
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

    public function test_a_supplier_can_be_created_updated_archived_and_restored(): void
    {
        $owner = $this->registerBusiness('Acme', 'owner@acme.test');

        $created = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/suppliers', [
                'name' => 'Global Supply Co',
                'email' => 'contact@globalsupply.test',
            ])->assertCreated();

        $supplierId = $created->json('data.id');
        $this->assertSame('active', $created->json('data.status'));

        $this->asBearerToken($owner['token'])
            ->putJson("/api/v1/suppliers/{$supplierId}", ['name' => 'Global Supply Co Ltd'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Global Supply Co Ltd');

        $this->asBearerToken($owner['token'])
            ->postJson("/api/v1/suppliers/{$supplierId}/archive")
            ->assertOk();

        $this->asBearerToken($owner['token'])
            ->getJson('/api/v1/suppliers?tab=active')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->asBearerToken($owner['token'])
            ->getJson('/api/v1/suppliers?tab=archived')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->asBearerToken($owner['token'])
            ->postJson("/api/v1/suppliers/{$supplierId}/restore")
            ->assertOk();

        $this->asBearerToken($owner['token'])
            ->getJson('/api/v1/suppliers?tab=active')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_archiving_a_supplier_never_deletes_it_or_its_historical_batches(): void
    {
        $owner = $this->registerBusiness('Acme', 'owner3@acme.test');

        $supplier = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/suppliers', ['name' => 'Global Supply Co'])
            ->assertCreated();

        $product = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/products', ['name' => 'Widget', 'sku' => 'W-1'])
            ->assertCreated();

        $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/stock/in', [
                'product_id' => $product->json('data.id'),
                'boxes' => 1,
                'units_per_box' => 10,
                'received_at' => now()->toDateString(),
                'supplier_id' => $supplier->json('data.id'),
            ])->assertCreated();

        $this->asBearerToken($owner['token'])
            ->postJson("/api/v1/suppliers/{$supplier->json('data.id')}/archive")
            ->assertOk();

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->json('data.id')]);

        $batches = $this->asBearerToken($owner['token'])
            ->getJson('/api/v1/batches?product_id='.$product->json('data.id'))
            ->assertOk();

        $batches->assertJsonPath('data.0.supplier.id', $supplier->json('data.id'));
    }

    public function test_a_business_cannot_see_or_modify_another_businesss_suppliers(): void
    {
        $businessA = $this->registerBusiness('Business A', 'ownerA@test.com');
        $businessB = $this->registerBusiness('Business B', 'ownerB@test.com');

        Tenant::set($businessA['business_id']);
        $supplier = $this->asBearerToken($businessA['token'])
            ->postJson('/api/v1/suppliers', ['name' => 'Secret Supplier'])
            ->assertCreated();

        $supplierId = $supplier->json('data.id');

        $this->asBearerToken($businessB['token'])
            ->getJson("/api/v1/suppliers/{$supplierId}")
            ->assertNotFound();

        $this->asBearerToken($businessB['token'])
            ->putJson("/api/v1/suppliers/{$supplierId}", ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->asBearerToken($businessB['token'])
            ->getJson('/api/v1/suppliers')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_staff_can_view_but_not_create_suppliers_while_manager_can(): void
    {
        $owner = $this->registerBusiness('Acme', 'owner4@acme.test');

        $staffUser = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/users', [
                'name' => 'Staff Member',
                'email' => 'staff4@acme.test',
                'password' => 'correct-horse-battery-staple',
                'role' => 'Staff',
            ])->assertCreated();

        $staffLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff4@acme.test',
            'password' => 'correct-horse-battery-staple',
        ])->assertOk();

        $this->asBearerToken($staffLogin->json('token'))
            ->getJson('/api/v1/suppliers')
            ->assertOk();

        $this->asBearerToken($staffLogin->json('token'))
            ->postJson('/api/v1/suppliers', ['name' => 'Rogue Supplier'])
            ->assertStatus(403);

        $managerUser = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/users', [
                'name' => 'Manager',
                'email' => 'manager4@acme.test',
                'password' => 'correct-horse-battery-staple',
                'role' => 'Manager',
            ])->assertCreated();

        $managerLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager4@acme.test',
            'password' => 'correct-horse-battery-staple',
        ])->assertOk();

        $this->asBearerToken($managerLogin->json('token'))
            ->postJson('/api/v1/suppliers', ['name' => 'Approved Supplier'])
            ->assertCreated();
    }
}
