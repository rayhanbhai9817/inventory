# Backend — Inventory Management SaaS API

Laravel REST API for the Inventory Management SaaS platform. See the
[repository root README](../README.md) for the full project overview,
architecture, and setup instructions.

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Runs on SQLite by default for local development (zero setup). See
`.env.example` for the production MySQL configuration used on Hostinger.

## Tests

```bash
php artisan test
```

## Code style

```bash
./vendor/bin/pint
```
