<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Seeds the global permission catalog (shared reference data, not
 * tenant-owned). Safe to run in every environment, including production,
 * since it never touches business/customer data.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }
}
