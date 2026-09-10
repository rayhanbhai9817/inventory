<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Only PermissionSeeder is safe to run unconditionally — it seeds the
     * global permission catalog, not tenant data. DemoDataSeeder creates
     * synthetic development data and refuses to run outside local/testing
     * environments; run it explicitly with:
     *   php artisan db:seed --class=DemoDataSeeder
     */
    public function run(): void
    {
        $this->call(PermissionSeeder::class);
    }
}
