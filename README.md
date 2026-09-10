# Inventory Management SaaS

A production-grade, multi-tenant warehouse/stock inventory management SaaS
platform, built around FIFO batch tracking (Stock IN creates a batch,
Stock OUT consumes oldest-first). It is deliberately **not** a sales/POS/
ERP system — see [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the
scope decision.

- **Frontend:** Next.js (App Router) + React + TypeScript + Tailwind CSS
- **Backend:** Laravel (REST API) + PHP
- **Database:** MySQL 8 (production target: Hostinger Cloud Hosting)
- **Auth:** Laravel Sanctum (token-based API authentication)
- **Authorization:** Spatie Laravel Permission (roles & permissions, tenant-scoped)

> **Status:** Active development. See [`docs/PROGRESS.md`](docs/PROGRESS.md) for exactly
> what is implemented vs. outstanding. Do not treat this README as a claim that every
> module below is finished — modules are only "done" once they have migrations, API
> endpoints, authorization, and (where noted) frontend UI with no placeholders.

## Repository layout

```
inventory-management-saas/
├── backend/    Laravel REST API application
├── frontend/   Next.js application
├── docs/       Architecture, API, and deployment documentation
└── README.md
```

Frontend and backend are fully separate applications with a clean HTTP/JSON boundary.
The frontend never talks to the database directly and never performs authoritative
business calculations (stock, pricing, totals, permissions) — Laravel is the single
source of truth for all of that.

## Why MySQL, not PostgreSQL

This project targets **Hostinger Cloud Hosting** (the hPanel-based cloud plan), not a
Hostinger VPS. Hostinger's cloud/shared hPanel only provisions **MySQL/MariaDB**
databases through its control panel — there is no managed PostgreSQL option on that
tier. Since production deployment is the hard constraint, the schema and Laravel
config target MySQL 8. Local development in this repository can run on SQLite
(Laravel's zero-setup file database) since the migrations avoid MySQL-only syntax.

## Multi-tenancy model

Single database, shared schema, **row-level tenant isolation**: every tenant-owned
table carries a `business_id` foreign key. A `BelongsToTenant` model trait applies a
global Eloquent scope that automatically restricts every query to the authenticated
user's business, and an `EnsureTenantContext` middleware resolves and binds the
current tenant on every authenticated request. This is enforced server-side only —
the frontend is never trusted to filter tenant data. See
[`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for details.

## Development setup

### Prerequisites

- PHP 8.3+ and Composer
- Node.js 20+ and npm
- MySQL 8 (recommended) or SQLite for quick local development

### Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
# Configure DB_* in .env (see backend/.env.example), then:
php artisan migrate --seed
php artisan serve
```

### Frontend (Next.js)

```bash
cd frontend
npm install
cp .env.example .env.local
# Set NEXT_PUBLIC_API_URL to point at the Laravel API (see frontend/.env.example)
npm run dev
```

### Running tests

```bash
# Backend
cd backend && php artisan test

# Frontend
cd frontend && npm run lint
```

## Environment variables

Never commit real `.env` files. Each application ships a `.env.example` documenting
every variable it needs:

- [`backend/.env.example`](backend/.env.example)
- [`frontend/.env.example`](frontend/.env.example)

## Documentation

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — system architecture, tenancy, auth
- [`docs/API.md`](docs/API.md) — REST API reference
- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — Hostinger Cloud deployment guide
- [`docs/PROGRESS.md`](docs/PROGRESS.md) — module-by-module implementation status

## Production data safety

Production customer/business data lives **only** in the Hostinger production MySQL
database. It must never be copied into this repository, into seeders, into test
fixtures, or into this development environment. Seeders in this repo generate
synthetic development data only.

## License

Proprietary — all rights reserved.
