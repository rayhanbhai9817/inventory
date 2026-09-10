<?php

namespace Tests\Feature\Supplier;

use App\Support\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierReportTest extends TestCase
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

        return ['token' => $response->json('token'), 'business_id' => $response->json('business.id')];
    }

    public function test_supplier_report_aggregates_stock_in_totals(): void
    {
        $owner = $this->registerBusiness('Acme', 'owner@acme.test');

        $supplier = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/suppliers', ['name' => 'Global Supply Co'])->assertCreated();

        $product = $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/products', ['name' => 'Widget', 'sku' => 'W-1'])->assertCreated();

        $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/stock/in', [
                'product_id' => $product->json('data.id'),
                'boxes' => 2, 'units_per_box' => 50,
                'received_at' => now()->toDateString(),
                'supplier_id' => $supplier->json('data.id'),
            ])->assertCreated();

        $report = $this->asBearerToken($owner['token'])
            ->getJson('/api/v1/reports/suppliers')
            ->assertOk();

        $this->assertSame(100, $report->json('data.0.total_units_supplied'));
        $this->assertSame(1, $report->json('data.0.batches_received'));
    }

    public function test_product_supplier_report_flags_products_with_no_supplier(): void
    {
        $owner = $this->registerBusiness('Acme', 'owner2@acme.test');

        $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/products', ['name' => 'Orphan Widget', 'sku' => 'W-2'])->assertCreated();

        $report = $this->asBearerToken($owner['token'])
            ->getJson('/api/v1/reports/product-suppliers?missing_supplier=1')
            ->assertOk();

        $this->assertCount(1, $report->json('data'));
        $this->assertFalse($report->json('data.0.has_supplier'));
    }

    public function test_staff_cannot_access_supplier_reports(): void
    {
        $owner = $this->registerBusiness('Acme', 'owner3@acme.test');

        $this->asBearerToken($owner['token'])
            ->postJson('/api/v1/users', [
                'name' => 'Staff', 'email' => 'staff5@acme.test',
                'password' => 'correct-horse-battery-staple', 'role' => 'Staff',
            ])->assertCreated();

        $staffToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff5@acme.test', 'password' => 'correct-horse-battery-staple',
        ])->assertOk()->json('token');

        $this->asBearerToken($staffToken)
            ->getJson('/api/v1/reports/suppliers')
            ->assertStatus(403);
    }
}
