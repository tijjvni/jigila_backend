# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

**Setup:**
```bash
composer install && npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
```

**Development (all services concurrently):**
```bash
composer run dev
# Starts: Laravel server + queue listener + Pail log viewer + Vite dev server
```

**Testing:**
```bash
composer run test          # run all tests
php artisan test --filter=OrderTest   # run a single test class
php artisan test --filter=OrderTest::test_user_can_create_order  # run a single test method
```

Tests use an in-memory SQLite database (configured in `phpunit.xml`) — no database setup needed.

**Frontend assets:**
```bash
npm run build
npm run dev   # watch mode (already included in composer run dev)
```

## Architecture

**Pattern:** Controllers → Services → Models

- **Controllers** (`app/Http/Controllers/`) are thin — they extract input, call a service, and return a response.
- **Services** (`app/Services/`) hold all business logic. Constructor-injected into controllers.
- **Eloquent Models** (`app/Models/`) define relationships and casts; no business logic.
- **Form Requests** (`app/Http/Requests/`) handle all input validation.
- **API Resources** (`app/Http/Resources/`) shape all JSON responses.

## Route Structure

All routes live in `routes/api.php` under the `/api` prefix:

| Group | Middleware | Examples |
|---|---|---|
| Public | none | `POST /auth/login`, `POST /auth/register`, OTP password reset, `POST /webhooks/paystack` |
| Protected | `auth:sanctum` | `GET/POST /user/orders`, `GET /invoices`, `GET/PUT /profile`, `POST /auth/logout` |
| Admin | `auth:sanctum` + `role:admin` | `GET /admin/dashboard`, `GET /admin/orders`, `GET /admin/invoices` |

The `CheckRole` middleware (`app/Http/Middleware/CheckRole.php`) enforces role-based access.

## Authentication Flow

1. Register or login → receive a Sanctum Bearer token
2. Include `Authorization: Bearer <token>` header on all protected requests
3. Password reset: `POST /auth/forgot-password` → `POST /auth/verify-otp` → `POST /auth/reset-password`
   - OTP tokens expire after 15 minutes and are stored in `password_reset_tokens`
   - When `APP_DEBUG=true`, the OTP is returned in the `forgot-password` response for local development

## Key Data Model

- `users`: id, name, email, phone, password, role — relationships: `orders()` HasMany, `invoices()` HasMany, `adminRoles()` BelongsToMany
- `orders`: id, user_id (FK cascade delete), vin, stock_id, auction_source, condition, already_purchased (bool), bid_price, vehicle_stock_no, buyer_no, buyer_code, services (JSON array), status (enum), pickup_location, departure_port, destination_port
- `invoices`: id, user_id, order_id, invoice_number (display only), payment_reference (Paystack UUID), payment_url, status (pending/paid/cancelled), paid_at
- Order `services` field is cast to array; `status` is an enum; `already_purchased` differentiates bid-only vs full-purchase orders
- Departure ports: `houston_tx`, `baltimore_md`, `newark_nj`, `savannah_ga`, `los_angeles_ca`
- Destination ports: `tin_can_lagos`, `lagos_apapa`, `tema_ghana`

## Authorization Conventions

- `OrderService` checks ownership on read/update/delete so users can only access their own orders
- Admin users bypass ownership checks
- Forbidden actions return HTTP 403

## Key Invariants

- **Controllers must delegate to services** — never query the DB directly in a controller. The service is injected via constructor; call it.
- **Paystack reference is a UUID** — `InvoiceService::create()` uses `'jig_' . Str::uuid()` as the Paystack reference, NOT the invoice number. The invoice number (`INV-000001`) is only a display label.
- **Port validation** — `UpdateOrderLocationRequest` validates `departure_port` and `destination_port` against `Rule::in()`. If you add a port to `ConfigController`, also add it to the request validation.
- **`listAll()` vs `list()`** — `InvoiceService::list(User $user)` returns only that user's invoices; `listAll()` returns all (used by admin). Never bypass these via direct DB queries.
- **MariaDB** — `DashboardService` explicitly handles `'mariadb'` as a driver case (same as `'mysql'`). Always add both when branching on `DB::connection()->getDriverName()`.
