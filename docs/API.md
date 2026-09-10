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

`register` body: `business_name, name, email, password, password_confirmation`.
`login` body: `email, password`.

## Catalog resources

The following are standard REST resources, all under `/api/v1`, all
requiring `Authorization: Bearer <token>` and a permission matching
`{module}.{view|create|edit|delete}` (e.g. `products.delete`):

| Resource | Endpoint | Notes |
|---|---|---|
| Categories | `/categories` | `parent_id` for hierarchy, `slug` auto-generated & unique per business |
| Brands | `/brands` | `slug` auto-generated & unique per business |
| Units | `/units` | `name` + `short_name`, unique per business |
| Warehouses | `/warehouses` | `is_default` — setting one clears the flag on all others |
| Suppliers | `/suppliers` | `opening_balance` seeds `current_balance` |
| Customers | `/customers` | same shape as suppliers |
| Products | `/products` | see below |

Standard actions: `GET /` (paginated list), `GET /{id}`, `POST /`,
`PUT /{id}`, `DELETE /{id}` (soft delete).

List query params: `search`, `status`, `per_page` (default 15), `page`.
Products additionally support `category_id`, `brand_id`, and
`low_stock=true` (total stock across all warehouses ≤ `min_stock_level`).

Single-resource responses are wrapped: `{ "data": { ... } }`. List
responses are wrapped with pagination: `{ "data": [...], "meta": {...},
"links": {...} }` (standard Laravel `AnonymousResourceCollection`
pagination shape).

### Product fields

`name, sku (unique per business), barcode, category_id, brand_id, unit_id
(required), cost_price, selling_price, min_stock_level, description,
status`. Response includes `total_stock` and per-warehouse `stocks` when
the `show` endpoint is used (`stocks.warehouse` eager-loaded).

## Not yet implemented

Purchases, purchase returns, sales, sales returns, payments, expenses,
stock adjustments/transfers, dashboard aggregate endpoints, and reports
are modeled in the database schema and Eloquent layer but have **no API
routes yet**. See `docs/PROGRESS.md` for what's next.

## Errors

- `401` — no/invalid token.
- `403` — authenticated but missing the required permission, or the
  authenticated user has no `business_id` (no tenant context).
- `404` — record not found *or* belongs to a different tenant (these are
  indistinguishable by design — tenant isolation must not leak existence).
- `409` — a delete was blocked by a referential-integrity guard (e.g.
  deleting a unit still assigned to products).
- `422` — validation failure.
