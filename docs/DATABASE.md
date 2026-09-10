# Database

Schema reference for the tables added or changed by the Business
Management Expansion (Suppliers + Product Pricing). For the inventory
engine's own tables (`stock_batches`, `stock_movements`,
`stock_movement_batches`, `stock_adjustments`), see `docs/ARCHITECTURE.md`
— they are unchanged except for the single new `supplier_id` column noted
below. Every table here follows the same multi-tenant convention as the
rest of the schema: a `business_id` foreign key, enforced server-side via
the `BelongsToTenant` trait, never trusted from client input.

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
```

All are additive or widening — none of them alter or drop any existing
inventory-engine column, and none require a data backfill.
