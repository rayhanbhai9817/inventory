<?php

namespace Tests\Feature\Dashboard;

use App\Support\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSupplierKpiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_dashboard_reports_supplier_kpis_and_missing_supplier_price_alerts(): void
    {
        $register = $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Acme',
            'name' => 'Owner',
            'email' => 'owner@acme.test',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])->assertCreated();

        $token = $register->json('token');
        Tenant::set($register->json('business.id'));

        $supplier = $this->asBearerToken($token)
            ->postJson('/api/v1/suppliers', ['name' => 'Global Supply Co'])
            ->assertCreated();

        $suppliedProduct = $this->asBearerToken($token)
            ->postJson('/api/v1/products', ['name' => 'Supplied Widget', 'sku' => 'W-1'])
            ->assertCreated();

        $this->asBearerToken($token)
            ->postJson('/api/v1/products', ['name' => 'Orphan Widget', 'sku' => 'W-2'])
            ->assertCreated();

        $this->asBearerToken($token)
            ->postJson('/api/v1/stock/in', [
                'product_id' => $suppliedProduct->json('data.id'),
                'boxes' => 1, 'units_per_box' => 20,
                'received_at' => now()->toDateString(),
                'supplier_id' => $supplier->json('data.id'),
            ])->assertCreated();

        // Formal supplier-product link (distinct from Stock IN's per-batch
        // supplier tracking) — this is what the "missing supplier" alert checks.
        $this->asBearerToken($token)
            ->postJson('/api/v1/supplier-products', [
                'supplier_id' => $supplier->json('data.id'),
                'product_id' => $suppliedProduct->json('data.id'),
            ])->assertCreated();

        $dashboard = $this->asBearerToken($token)
            ->getJson('/api/v1/dashboard')
            ->assertOk();

        $this->assertSame(1, $dashboard->json('data.supplier_summary.total_suppliers'));
        $this->assertSame(1, $dashboard->json('data.supplier_summary.recently_used_suppliers'));
        $this->assertSame(20, $dashboard->json('data.supplier_summary.top_suppliers_by_quantity.0.total_units_supplied'));

        // Both products are missing a price; only "Orphan Widget" is missing a supplier.
        $this->assertSame(2, $dashboard->json('data.product_alerts.missing_price_count'));
        $this->assertSame(1, $dashboard->json('data.product_alerts.missing_supplier_count'));
        $this->assertSame('Orphan Widget', $dashboard->json('data.product_alerts.missing_supplier_sample.0.name'));
    }
}
