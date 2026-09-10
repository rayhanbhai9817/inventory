<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
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
    public function run(TenantProvisioningService $provisioner, InventoryService $inventory): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'DemoDataSeeder creates synthetic data and must not run outside local/testing environments.'
            );
        }

        $business = Business::create([
            'name' => 'Ozipco Demo',
            'slug' => 'ozipco-demo',
            'email' => 'demo@example.com',
            'currency_code' => 'USD',
            'timezone' => 'UTC',
        ]);

        Tenant::set($business->id);

        $owner = User::create([
            'business_id' => $business->id,
            'name' => 'Demo Admin',
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

        $dewormer = Category::create(['name' => 'Dewormer', 'slug' => 'dewormer']);
        $ivermectin = Category::create(['name' => 'Ivermectin', 'slug' => 'ivermectin']);

        $catDewormer = Product::create([
            'category_id' => $dewormer->id,
            'name' => 'Cat Dewormer Tablet',
            'sku' => '001',
            'min_stock_level' => 100,
            'created_by' => $owner->id,
        ]);
        $inventory->stockIn($catDewormer, 5, 196, now()->subWeeks(3)->toDateString(), 'Initial stock', $owner);

        $ivermectinPaste = Product::create([
            'category_id' => $ivermectin->id,
            'name' => 'Horse Unflavored Ivermectin Paste',
            'sku' => '003',
            'min_stock_level' => 200,
            'created_by' => $owner->id,
        ]);
        $inventory->stockIn($ivermectinPaste, 5, 434, now()->subWeeks(2)->toDateString(), 'Initial stock', $owner);
        $inventory->stockOut($ivermectinPaste, 400, 'Dispatched to clinic', $owner);

        $this->command?->info("Demo business created: {$business->slug} — login as owner@demo.test / password (or staff@demo.test / password)");
    }
}
