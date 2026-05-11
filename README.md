# Jigila Backend API

REST API backend for the Jigila vehicle import and shipping platform. Built with Laravel 13, Laravel Sanctum, and SQLite.

## Tech Stack

- **Framework**: Laravel 13.7
- **Auth**: Laravel Sanctum (API token)
- **Database**: SQLite (dev) — swap `DB_CONNECTION` for PostgreSQL/MySQL in production
- **Architecture**: Controllers → Services → Eloquent Models, with Form Requests for validation and API Resources for response shaping

## Requirements

- PHP 8.3+
- Composer

## Getting Started

```bash
git clone <repo-url>
cd jigila_backend

composer install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate

php artisan serve
```

The API will be available at `http://127.0.0.1:8000/api`.

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_ENV` | Application environment | `local` |
| `APP_KEY` | Encryption key (auto-generated) | — |
| `DB_CONNECTION` | Database driver | `sqlite` |
| `DB_DATABASE` | Database path / name | `database/database.sqlite` |
| `MAIL_MAILER` | Mail driver | `log` (dev) |

## API Reference

Base URL: `/api`

### Authentication

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/auth/register` | Public | Create a new user account |
| `POST` | `/auth/login` | Public | Login and receive a bearer token |
| `POST` | `/auth/logout` | Bearer token | Revoke the current token |
| `POST` | `/auth/forgot-password` | Public | Request a 6-digit OTP |
| `POST` | `/auth/verify-otp` | Public | Verify the OTP |
| `POST` | `/auth/reset-password` | Public | Set a new password using the OTP |

### Profile

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/profile` | Bearer token | Get the authenticated user's profile |
| `PUT` | `/profile` | Bearer token | Update name, email, phone, or password |

### Orders

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/orders` | Bearer token | List orders (own orders for users, all for admins) |
| `POST` | `/orders` | Bearer token | Create a new vehicle order |
| `GET` | `/orders/{id}` | Bearer token | Get a single order |
| `PUT` | `/orders/{id}` | Bearer token | Update an order |
| `DELETE` | `/orders/{id}` | Bearer token | Delete an order |

### Admin

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/admin/dashboard` | Bearer token + admin role | Platform stats and recent orders |

### Example: Register

```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "08012345678",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

### Example: Login

```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "secret123"
}
```

Response:

```json
{
  "token": "1|abc123...",
  "user": {
    "id": "1",
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "08012345678",
    "role": "user"
  }
}
```

All subsequent authenticated requests must include:

```
Authorization: Bearer <token>
Accept: application/json
```

### Example: Create Order

```http
POST /api/orders
Authorization: Bearer <token>
Content-Type: application/json

{
  "vin": "1HGCM82633A004352",
  "auction_source": "Copart",
  "condition": "Runner",
  "already_purchased": false,
  "bid_price": "7500",
  "services": ["trucking", "shipping"]
}
```

## Order Fields

| Field | Type | Values |
|-------|------|--------|
| `auction_source` | string | `Copart`, `IAAI`, `Co-parts` |
| `condition` | string | `Runner`, `Runs and drives`, `Enhanced vehicle`, `Stationary` |
| `already_purchased` | boolean | `true` / `false` |
| `bid_price` | string | Required when `already_purchased` is `false` |
| `vehicle_stock_no` | string | Required when `already_purchased` is `true` |
| `buyer_no` | string | Required when `already_purchased` is `true` |
| `buyer_code` | string | Required when `already_purchased` is `true` |
| `services` | array | `trucking` ($800), `shipping` ($2000) |
| `status` | string | `pending`, `processing`, `in_transit`, `at_port`, `delivered`, `cancelled` |

## Project Structure

```
app/
├── Http/
│   ├── Controllers/        # Thin controllers — orchestration only
│   │   └── Admin/
│   ├── Middleware/         # CheckRole middleware
│   ├── Requests/           # Form Request validation classes
│   │   ├── Auth/
│   │   ├── Order/
│   │   └── Profile/
│   └── Resources/          # API Resource response shaping
│       └── Admin/
├── Models/                 # Eloquent models (User, Order)
└── Services/               # Business logic
    └── Admin/
database/
├── factories/              # Model factories for testing
├── migrations/
└── database.sqlite
routes/
└── api.php
tests/
├── Feature/                # HTTP endpoint tests
└── Unit/                   # Service layer tests
```

## Running Tests

```bash
php artisan test
```

The test suite uses an in-memory SQLite database — no setup required.

```
Tests:    69 passed
Duration: ~800ms
```

## Frontend

The React frontend companion app expects this API at `http://127.0.0.1:8000/api`. Set `VITE_API_URL` in the frontend `.env` to point to a different host.
