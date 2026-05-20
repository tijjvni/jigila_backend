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
| Public | none | `POST /auth/login`, `POST /auth/register`, OTP password reset |
| Protected | `auth:sanctum` | `GET/POST /orders`, `GET/PUT /profile`, `POST /auth/logout` |
| Admin | `auth:sanctum` + `role:admin` | `GET /admin/dashboard` |

The `CheckRole` middleware (`app/Http/Middleware/CheckRole.php`) enforces role-based access.

## Authentication Flow

1. Register or login → receive a Sanctum Bearer token
2. Include `Authorization: Bearer <token>` header on all protected requests
3. Password reset: `POST /auth/forgot-password` → `POST /auth/verify-otp` → `POST /auth/reset-password`
   - OTP tokens expire after 15 minutes and are stored in `password_reset_tokens`

## Key Data Model

- `users`: id, name, email, phone, password, role
- `orders`: id, user_id (FK cascade delete), vin, stock_id, auction_source, condition, already_purchased (bool), bid_price, vehicle_stock_no, buyer_no, buyer_code, services (JSON array), status (enum)
- Order `services` field is cast to array; `status` is an enum; `already_purchased` is boolean

## Authorization Conventions

- `OrderService` checks ownership on read/update/delete so users can only access their own orders
- Admin users bypass ownership checks
- Forbidden actions return HTTP 403
