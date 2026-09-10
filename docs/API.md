# API Reference

Base URL: `{APP_URL}/api/v1`. All authenticated endpoints require
`Authorization: Bearer <token>` and return JSON. Validation errors return
`422` with `{ "message": ..., "errors": { field: [messages] } }`. See
`docs/DATABASE.md` for the underlying schema and
`docs/ARCHITECTURE.md` for the supplier/pricing architecture.

## Auth

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/auth/register` | none (6/min) | Creates a business + owner user, returns `{ business, user, token }` |
| POST | `/auth/login` | none (10/min) | Returns `{ user, token }` |
| POST | `/auth/logout` | Bearer | Revokes the current token |
| GET | `/auth/me` | Bearer | Returns `{ user, business }` (user includes `roles`, `permissions`) |

## Products (`products.*` permissions)

| Method | Path | Notes |
|---|---|---|
| GET | `/products?tab=active\|archived\|trashed` | Paginated, `search`, `category_id`; `export=true&format=csv` streams a CSV |
| GET | `/products/{id}` | Includes batches |
| POST | `/products` | `name, sku, category_id?, description?, min_stock_level?` |
| PUT | `/products/{id}` | Same fields |
| POST | `/products/{id}/archive` | Active → Archived |
| POST | `/products/{id}/restore` | Archived → Active |
| DELETE | `/products/{id}` | Active/Archived → Trashed (soft delete) |
| POST | `/products/trashed/{id}/restore` | Trashed → Active |

There is no permanent-delete endpoint — see `docs/PROGRESS.md`.

## Categories (`categories.*` permissions)

Standard REST resource: `GET /categories`, `GET/POST/PUT/DELETE
/categories/{id}`. `DELETE` returns `409` if any product (including
trashed) is still assigned to the category.

## Inventory (`inventory.view`)

| Method | Path | Notes |
|---|---|---|
| GET | `/inventory` | Rollup per active product: boxes, units/box (null if batches disagree), total units, cumulative stock in/out, status. `search`, `category_id`, `status=in_stock\|low_stock\|out_of_stock`, `export=true&format=csv` |
| GET | `/inventory/{product}` | Full detail: inventory summary, movement stats, all batches (each with its `supplier`), last 20 movements, plus `supplier_info` (primary supplier, other linked suppliers, last Stock IN supplier) and `price_info` (current reference price) — both read-only summaries, present for the enhanced product detail page |

## Stock IN / OUT (`stock.in` / `stock.out`)

| Method | Path | Body |
|---|---|---|
| POST | `/stock/in` | `product_id, boxes, units_per_box, received_at, notes?, supplier_id?` → `total_units = boxes × units_per_box`. `supplier_id` is optional and tenant-scoped-validated; stored on the resulting batch, never on the movement |
| POST | `/stock/out` | `product_id, units, notes?` — FIFO-consumes oldest batches first; `422` with a plain-English message if insufficient stock. **No supplier field** — Stock OUT never gains a customer/sales concept |

## Stock Adjustments (`stock.adjust`)

| Method | Path | Body |
|---|---|---|
| GET | `/stock/adjustments` | `product_id?`, paginated |
| POST | `/stock/adjustments` | `product_id, direction: increase\|decrease, quantity, reason: physical_count\|damaged\|lost\|found\|data_correction\|other, note?` |

## Batches (`batches.view`)

`GET /batches` (`product_id?`, `supplier_id?`, `status?`, `search` on
batch code), `GET /batches/{id}` — both include the batch's `supplier`
when present. This same endpoint, filtered by `supplier_id`, is the
"Stock In by Supplier" report (`/reports/stock-in-by-supplier`, same
controller action, `supplier_reports.view`-gated).

## Suppliers (`suppliers.*`)

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/suppliers?tab=active\|inactive\|archived\|all` | `suppliers.view` | paginated, `search`; `export=true&format=csv` |
| GET | `/suppliers/{id}` | `suppliers.view` | rich detail: supplier info, `supplied_products` (with first/last-supplied dates from batch history), `totals`, `recent_stock_in` (last 20 batches) |
| POST | `/suppliers` | `suppliers.create` | `name` required; rest optional. Fires a `supplier_added` notification |
| PUT | `/suppliers/{id}` | `suppliers.update` | |
| POST | `/suppliers/{id}/archive` \| `/restore` | `suppliers.archive` | reversible; **no delete endpoint exists** — see `docs/DATABASE.md` |

## Supplier ↔ Product links (`suppliers.view` / `suppliers.update`)

| Method | Path | Notes |
|---|---|---|
| GET | `/supplier-products?supplier_id?&product_id?&status?` | paginated |
| POST | `/supplier-products` | `supplier_id, product_id, supplier_sku?, supplier_product_name?, notes?, is_primary?, status?`. `422` if this supplier/product pair is already linked. Setting `is_primary: true` automatically clears any other primary link for that product (inside a transaction) |
| PUT | `/supplier-products/{id}` | same fields, partial |
| DELETE | `/supplier-products/{id}` | unlinks (does not touch supplier or product records, or any batch history) |

## Product Pricing (`product_prices.*`) — reference data, never inventory

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/products/{id}/prices` | `product_prices.history` | full price history, newest first, paginated |
| POST | `/products/{id}/prices` | `product_prices.create` | `price, effective_date, currency? (default USD), notes?` — **always inserts a new row**, never overwrites a prior one |
| PUT | `/products/{id}/prices/{price}` | `product_prices.update` | edits **only** `notes` — `price` and `effective_date` are immutable once recorded |
| GET | `/product-price-catalog` | `product_prices.view` | current price per product; `search`, `category_id`, `status=active\|archived`, `missing_price=1`; `export=true&format=csv`. Also mounted at `/reports/product-prices` as the "Product Price Report" |

Recording a price never touches `stock_batches`, `stock_movements`, or
any inventory quantity — see `docs/ARCHITECTURE.md` → "Product Pricing"
for the structural guarantee, and
`tests/Feature/Pricing/ProductPriceTest.php` for the regression test.

## Supplier Reports (`supplier_reports.view` / `.export`)

| Method | Path | Notes |
|---|---|---|
| GET | `/reports/suppliers` | one row per supplier: products supplied, batches received, total units supplied, last stock-in date; `export=true&format=csv` requires `supplier_reports.export` |
| GET | `/reports/product-suppliers` | one row per product: primary supplier, all linked suppliers, `has_supplier` flag; `missing_supplier=1` to list only products with none |

## Stock Ledger (`ledger.view`)

`GET /stock-ledger` — every movement, filterable by `product_id`, `type`,
`user_id`, `batch_code`, `from`/`to`; `export=true&format=csv`.

## Dashboard (`dashboard.view`)

`GET /dashboard?period=today|yesterday|week|month|custom&from&to` — returns
`period_stock_summary` (opening/net_flow/closing, computed by
reconstructing the balance at the period boundaries from the movement
ledger — correct for any period, not just "now"), `stock_in_total`,
`stock_out_total`, `totals`, `inventory_health` (percent + status +
low/out-of-stock counts), `recent_movements`, `supplier_summary` (total/
active supplier counts, suppliers used in the period, top 5 by quantity
supplied), and `product_alerts` (counts + a 5-item sample each of
products with no supplier and products with no reference price). The
latter two are pure counts/quantities — no financial data.

## Daily Activity (`activity.view`) / Audit Log (`audit.view`)

`GET /activity?date=YYYY-MM-DD` (defaults to today) and `GET
/audit-logs?from&to&user_id&action` — both read-only views over the same
underlying audit trail; see `docs/ARCHITECTURE.md`.

## Reports (`reports.view`)

Thin, permission-gated aliases over the endpoints above — deliberately
not separate implementations (see "Reports" in `docs/PROGRESS.md`):
`/reports/inventory-summary` → InventoryController, `/reports/stock-movement`
→ StockLedgerController, `/reports/batches` → BatchController,
`/reports/daily-activity` → ActivityController.

## Notifications

`GET /notifications?unread_only=` (no dedicated permission — personal/
business-wide), `POST /notifications/{id}/read`, `POST
/notifications/read-all`.

## Admin Users (`users.*`) / Roles (`roles.view`)

`GET/POST /users`, `GET/PUT /users/{id}`, `PATCH /users/{id}/status`
(`{status: active|inactive}`), `POST /users/{id}/reset-password`. `GET
/roles` — read-only; the three role templates are fixed per business (no
custom-role builder in this phase).

These endpoints all require an authenticated Owner/Manager session, so
they can't create the *first* user for a fresh deployment. That's what
`php artisan app:create-admin` (an interactive console command, not an
API endpoint — see `docs/DEPLOYMENT.md` → "First Admin User Setup") is
for.

## Settings (`settings.manage`)

`GET/PUT /settings` — general (company name, timezone, date/number
format), inventory (default min-stock threshold), notifications (low
stock / out of stock toggles).

## Not present (by design, this phase)

Sales, purchase orders, customers, payments, expenses, invoicing,
multi-warehouse, and any form of stock valuation — see
`docs/PROGRESS.md` for the decision record. Suppliers and product
reference pricing *are* present as of this expansion (see above); they
remain pure business-management data with no financial/sales semantics.

## Errors

- `401` no/invalid token · `403` missing permission or no tenant context
- `404` not found *or* belongs to another tenant (indistinguishable by design)
- `409` a delete was blocked by a referential-integrity guard
- `422` validation failure, or insufficient stock (Stock OUT / adjustment decrease)
