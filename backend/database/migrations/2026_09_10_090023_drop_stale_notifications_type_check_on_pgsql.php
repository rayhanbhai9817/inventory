<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL-only fix. Laravel's pgsql grammar implements `enum()` as
 * `VARCHAR` + a `CHECK (type IN (...))` constraint — unlike MySQL's
 * native ENUM or SQLite's lack of enforcement. The earlier
 * 2026_09_10_090022 migration widened `notifications.type` to a plain
 * string on every driver, but never dropped that leftover Postgres
 * check constraint, so on Postgres specifically any notification type
 * added after the original six (supplier_added,
 * product_missing_supplier, product_missing_price, ...) was silently
 * rejected by the database with a 500 error. Confirmed by running the
 * full migration set + test suite against a real local Postgres 16
 * instance: 11/48 tests failed with
 * `SQLSTATE[23514]: Check violation ... notifications_type_check`.
 *
 * MySQL and SQLite have no such constraint (the 090022 migration
 * already fully fixed them), so this migration is a no-op there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_type_check');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'ALTER TABLE notifications ADD CONSTRAINT notifications_type_check '.
            "CHECK (type IN ('low_stock', 'out_of_stock', 'stock_in', 'stock_out', 'adjustment', 'admin_activity'))"
        );
    }
};
