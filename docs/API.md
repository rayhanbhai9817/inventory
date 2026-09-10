# API Reference

Base URL: `{APP_URL}/api/v1`. All authenticated endpoints require
`Authorization: Bearer <token>` and return JSON. Validation errors return
`422` with `{ "message": ..., "errors": { field: [messages] } }`.

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
| GET | `/inventory/{product}` | Full detail: inventory summary, movement stats, all batches, last 20 movements |

## Stock IN / OUT (`stock.in` / `stock.out`)

| Method | Path | Body |
|---|---|---|
| POST | `/stock/in` | `product_id, boxes, units_per_box, received_at, notes?` → `total_units = boxes × units_per_box` |
| POST | `/stock/out` | `product_id, units, notes?` — FIFO-consumes oldest batches first; `422` with a plain-English message if insufficient stock |

## Stock Adjustments (`stock.adjust`)

| Method | Path | Body |
|---|---|---|
| GET | `/stock/adjustments` | `product_id?`, paginated |
| POST | `/stock/adjustments` | `product_id, direction: increase\|decrease, quantity, reason: physical_count\|damaged\|lost\|found\|data_correction\|other, note?` |

## Batches (`batches.view`)

`GET /batches` (`product_id?`, `status?`, `search` on batch code), `GET
/batches/{id}`.

## Stock Ledger (`ledger.view`)

`GET /stock-ledger` — every movement, filterable by `product_id`, `type`,
`user_id`, `batch_code`, `from`/`to`; `export=true&format=csv`.

## Dashboard (`dashboard.view`)

`GET /dashboard?period=today|yesterday|week|month|custom&from&to` — returns
`period_stock_summary` (opening/net_flow/closing, computed by
reconstructing the balance at the period boundaries from the movement
ledger — correct for any period, not just "now"), `stock_in_total`,
`stock_out_total`, `totals`, `inventory_health` (percent + status +
low/out-of-stock counts), `recent_movements`.

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

Sales, purchases, customers, suppliers, payments, expenses, multi-
warehouse — see `docs/PROGRESS.md` for the decision record.

## Errors

- `401` no/invalid token · `403` missing permission or no tenant context
- `404` not found *or* belongs to another tenant (indistinguishable by design)
- `409` a delete was blocked by a referential-integrity guard
- `422` validation failure, or insufficient stock (Stock OUT / adjustment decrease)
