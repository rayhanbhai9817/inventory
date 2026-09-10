<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\TenantProvisioningService;
use App\Support\Tenant;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Synthetic development/demo data ONLY. Never run against production —
 * this seeder refuses to run unless APP_ENV is local or testing.
 */
class DemoDataSeeder extends Seeder
{
    public function run(TenantProvisioningService $provisioner): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'DemoDataSeeder creates synthetic data and must not run outside local/testing environments.'
            );
        }

        $business = Business::create([
            'name' => 'Demo Retail Co',
            'slug' => 'demo-retail-co',
            'email' => 'demo@example.com',
            'currency_code' => 'USD',
            'timezone' => 'UTC',
        ]);

        Tenant::set($business->id);

        $owner = User::create([
            'business_id' => $business->id,
            'name' => 'Demo Owner',
            'email' => 'owner@demo.test',
            'password' => 'password',
        ]);

        $provisioner->provision($business, $owner);

        $staff = User::create([
            'business_id' => $business->id,
            'name' => 'Demo Staff',
            'email' => 'staff@demo.test',
            'password' => 'password',
        ]);
        $staff->assignRole('Staff');

        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-1',
            'is_default' => true,
        ]);

        $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc']);

        $category = Category::create(['name' => 'General', 'slug' => 'general']);
        $brand = Brand::create(['name' => 'Generic', 'slug' => 'generic']);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
            'name' => 'Sample Widget',
            'sku' => 'SKU-0001',
            'cost_price' => 5.00,
            'selling_price' => 9.99,
            'min_stock_level' => 10,
        ]);

        $product->stocks()->create([
            'business_id' => $business->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 0,
        ]);

        Supplier::create(['name' => 'Demo Supplier Ltd']);
        Customer::create(['name' => 'Walk-in Customer']);

        $this->command?->info("Demo business created: {$business->slug} — login as owner@demo.test / password");
    }
}
