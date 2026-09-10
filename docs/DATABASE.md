# Database

Schema reference for the tables added or changed by the Business
Management Expansion (Suppliers + Product Pricing). For the inventory
engine's own tables (`stock_batches`, `stock_movements`,
`stock_movement_batches`, `stock_adjustments`), see `docs/ARCHITECTURE.md`
— they are unchanged except for the single new `supplier_id` column noted
below. Every table here follows the same multi-tenant convention as the
rest of the schema: a `business_id` foreign key, enforced server-side via
the `BelongsToTenant` trait, never trusted from client input.

## Production database: Supabase PostgreSQL

Production uses a **Supabase-hosted PostgreSQL database**, connected to
directly by Laravel via its native `pgsql` driver — Laravel is still the
only thing that talks to the database (see `docs/ARCHITECTURE.md`).
Supabase's REST/JS client layer (`@supabase/supabase-js`) is **not**
used anywhere in this project; Supabase here is purely a managed
Postgres host, reached with a plain database connection string exactly
the way MySQL was reached before.

All 21 migrations (20 pre-existing + the one Postgres-specific fix
below) were verified against a real local PostgreSQL 16 instance —
`php artisan migrate --force` and the full `php artisan test` suite
both run clean. See "Migration order" below for the one Postgres-only
fix this required.

### Values you need from your Supabase dashboard

Supabase project → **Settings → Database** → **Connection parameters**:

| `.env` variable | What it is | Where on the Supabase dashboard |
|---|---|---|
| `DB_HOST` | Database host | "Host" field under Connection parameters |
| `DB_PORT` | `5432` for a direct connection, `6543` for the connection-pooler (PgBouncer) endpoint | Shown next to each connection mode Supabase offers |
| `DB_DATABASE` | Database name | "Database name" — Supabase's default is `postgres` |
| `DB_USERNAME` | Database user | "User" — Supabase's default is `postgres` |
| `DB_PASSWORD` | The database password you set when creating the Supabase project (**not** any API key) | You set this yourself at project creation; reset it from Settings → Database if forgotten |
| `DB_SSLMODE` | `require` | Not shown on the dashboard — Supabase mandates SSL, so this is always `require` regardless of what the dashboard displays |

**Port 5432 vs 6543**: 5432 is a direct connection to Postgres itself.
6543 goes through Supabase's PgBouncer connection pooler, recommended
when many short-lived connections are expected (typical for a
PHP-FPM-hosted Laravel app where each request opens its own
connection). Either works with Laravel's `pgsql` driver; start with
5432 for simplicity, move to 6543 if you see connection-limit issues
under load.

None of these are typed into this repository or committed to Git —
they go only into the production server's own `backend/.env` file,
which is gitignored (see `.gitignore` and `backend/.env.example`, which
contains blank placeholders, not real values).

## `suppliers`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | FK → businesses | tenant scope |
| `name` | string | required |
| `company_name`, `contact_person`, `email`, `phone`, `address`, `city`, `state`, `country`, `website`, `tax_number`, `notes` | nullable strings/text | |
| `status` | enum(`active`,`inactive`) | default `active`; independent of archival |
| `archived_at` | nullable timestamp | archive, never delete — see below |

Indexes: `(business_id, archived_at)`, `(business_id, name)`.

**Archive, never delete.** There is no destroy endpoint. `stock_batches.supplier_id`
references `suppliers.id` with `restrictOnDelete()` — the database
itself refuses a hard delete of a supplier that any batch (even from
years ago) still references. Archiving (`archived_at` set) is the only
retirement path, and it's fully reversible via restore.

## `supplier_products`

The many-to-many link between suppliers and products — *not* a
duplication of supplier fields onto the product, and *not* the same
thing as `stock_batches.supplier_id` (see `docs/ARCHITECTURE.md` →
"Suppliers" for why those two relationships are tracked independently).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | FK → businesses | |
| `supplier_id` | FK → suppliers | |
| `product_id` | FK → products | |
| `supplier_sku`, `supplier_product_name`, `notes` | nullable | how *this supplier* refers to the product |
| `is_primary` | boolean, default false | enforced single-primary-per-product in `SupplierProductController` (app-level invariant inside a transaction, not a DB constraint — consistent with how the rest of this codebase enforces multi-row invariants) |
| `status` | enum(`active`,`inactive`) | default `active` |

Unique constraint: `(supplier_id, product_id)` — a supplier can only be
linked to a given product once (`SupplierProductController::store()`
also checks this explicitly and returns a friendly `422` rather than a
raw DB constraint error).

## `product_prices`

A single **append-only ledger** — deliberately not split into a
"current price" table plus a "price history" table. See
`docs/ARCHITECTURE.md` → "Product Pricing" for the full reasoning.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | FK → businesses | |
| `product_id` | FK → products | |
| `price` | decimal(15,4) | |
| `currency` | string(3), default `USD` | |
| `effective_date` | date | |
| `notes` | nullable text | the only field `update()` is allowed to change |
| `created_by` | FK → users | |
| `created_at` | timestamp | **no `updated_at`** — `const UPDATED_AT = null` on the model; a row is either newly created or has its `notes` corrected, never "changed" in the sense of losing its original values |

"Current price" for a product = `SELECT * FROM product_prices WHERE
product_id = ? ORDER BY effective_date DESC, id DESC LIMIT 1` — always
computed live (`Product::currentPrice()`), never cached.

Nothing in `stock_batches`, `stock_movements`, `stock_adjustments`, or
`InventoryService` has a foreign key to, or reads from, `product_prices`.
That is the structural guarantee behind "price never affects inventory."

## `stock_batches` — one new column

| Column | Type | Notes |
|---|---|---|
| `supplier_id` | nullable FK → suppliers, `restrictOnDelete()` | which supplier this specific delivery came from; set once at Stock IN time, never changed afterward |

Index: `(business_id, supplier_id)`.

## `notifications` — `type` widened

`type` was originally a fixed DB `enum` (`low_stock`, `out_of_stock`,
`stock_in`, `stock_out`, `adjustment`, `admin_activity`). Migration
`2026_09_10_090022_widen_notifications_type_column` widens it to a plain
`string(50)`, since this expansion added three more values
(`supplier_added`, `product_missing_supplier`, `product_missing_price`)
and more will likely follow — a migration per new notification type was
becoming a maintenance burden for a column whose only real constraint
was documentation.

## Migration order (this expansion)

```
create_suppliers_table
create_supplier_products_table
create_product_prices_table
add_supplier_id_to_stock_batches_table
widen_notifications_type_column
drop_stale_notifications_type_check_on_pgsql
```

All are additive or widening — none of them alter or drop any existing
inventory-engine column, and none require a data backfill.

The last one, `drop_stale_notifications_type_check_on_pgsql`, exists
only because of a driver difference: Laravel implements `enum()` on
Postgres as `VARCHAR` + a `CHECK` constraint (MySQL uses a native
`ENUM` type; SQLite enforces nothing). `widen_notifications_type_column`
correctly widened the column on every driver, but left that old
Postgres-only check constraint in place — so on Postgres specifically,
inserting any notification `type` added after the original six
(`supplier_added`, `product_missing_supplier`, `product_missing_price`)
failed with `SQLSTATE[23514]: Check violation`. This migration drops
that constraint, guarded to run only on `pgsql` — a genuine no-op on
MySQL/SQLite, which never had the problem. Found and fixed by actually
running the full test suite against a real Postgres instance, not by
inspection alone (11/48 tests failed before the fix, 0 after).
