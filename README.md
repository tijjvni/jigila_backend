# Jigila API

Backend for **Jigila** — a vehicle auction logistics platform that sources vehicles from US
auctions (Copart, IAAI) and manages shipping them to Nigeria and West Africa. Laravel 13 /
PHP 8.3, JSON API only, consumed by the React SPA in the sibling `boiler-frontend`
repository.

## What it does

| Domain | Entities | Purpose |
|---|---|---|
| Orders | `Order`, `OrderAuditLog` | The vehicle import lifecycle, from auction bid to delivery |
| Billing | `Invoice` | Paystack-backed invoicing, payment and refunds |
| Paperwork | `OrderDocument` | Bills of lading, titles, dock receipts — private storage |
| Support | `Ticket`, `TicketMessage`, `Notification` | Customer↔staff ticketing and the in-app feed |
| Access | `User`, `Role`, `Setting` | Accounts, roles and platform configuration |

## Getting started

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

The API is then at `http://127.0.0.1:8000/api/v1`.

`composer dev` runs the server, queue listener, log tailer and Vite together.
**A queue worker must be running for any email to send** — mail is dispatched with
`Mail::queue()` and `QUEUE_CONNECTION` defaults to `database`.

Seed development accounts with `php artisan db:seed`:

| Account | Password | Access |
|---|---|---|
| `admin@jigila.com` | `password` | Admin |
| `user@jigila.com` | `password` | Customer |

Both are seeded with `email_verified_at` set, so they skip the verification gate.
`SuperAdminRoleSeeder` creates a "Super Admin" role holding every `Permission` case.

**Change these before seeding anything that is not a throwaway database.**

## The API contract

Base path **`/api/v1`**. Bearer-token auth via Laravel Sanctum.

The single exception is the Paystack webhook at **`/api/webhooks/paystack`**, deliberately
left unversioned so the gateway's stored callback URL survives a version bump.

### Response shapes

There is no universal envelope. Three shapes exist:

```jsonc
// a resource or collection — Laravel's Resource wrapper
{ "data": { ... } }

// a message-only mutation
{ "message": "Settings updated successfully." }

// an error (validation errors add an `errors` object)
{ "message": "Forbidden." }
```

A missing model renders as `{ "message": "Order not found." }` with status 404.
Clients must not assume `data` is present on every response.

### Types on the wire

- Money is a `decimal:2` cast, so amounts serialise as **strings** (`"12000.00"`).
- Timestamps are ISO 8601 strings; `due_date`, `eta_start`, `eta_end` and
  `port_received_at` are dates.
- Enums serialise to their string value (`"on_vessel"`, `"bid_deposit"`).
- List endpoints return a plain array with **no pagination meta**; the admin order list
  accepts `?per_page=N`.

### Rate limits

| Routes | Limit |
|---|---|
| `auth/login`, `auth/register` | 10/min |
| `auth/forgot-password`, `verify-otp`, `reset-password` | 5/min |
| `auth/email/verify`, `auth/email/resend` | 6/min |
| `POST orders` | 30/min |
| `POST orders/{id}/cancel`, refund requests, `POST tickets` | 10/min |
| `POST tickets/{id}/messages` | 20/min |
| `webhooks/paystack` | 120/min |

## Authentication

```http
POST /api/v1/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "08012345678",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

Register and login both return:

```json
{ "token": "1|abc123...", "user": { "id": 1, "name": "John Doe", "email": "john@example.com", "phone": "08012345678", "role": "user" } }
```

Send it on every protected request:

```
Authorization: Bearer <token>
Accept: application/json
```

**Email verification is enforced.** A registered but unverified user can reach only
`POST auth/logout` and `POST auth/email/resend`; everything else returns 403 until
`GET auth/email/verify/{id}/{hash}` (a signed URL) is visited.

Tokens **slide**: a request arriving with under 2 hours left pushes the expiry to 8 hours
out, so an active session never expires mid-use while an idle one still does.

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `POST` | `/auth/register` | — | Create an account |
| `POST` | `/auth/login` | — | Exchange credentials for a token |
| `POST` | `/auth/logout` | Bearer | Revoke the current token |
| `POST` | `/auth/forgot-password` | — | Request a 6-digit OTP |
| `POST` | `/auth/verify-otp` | — | Verify the OTP |
| `POST` | `/auth/reset-password` | — | Set a new password using the OTP |
| `GET` | `/auth/email/verify/{id}/{hash}` | signed | Confirm an email address |
| `POST` | `/auth/email/resend` | Bearer | Resend the verification mail |

`forgot-password` returns the same generic message whether or not the email exists, so the
endpoint cannot be used to enumerate accounts. The OTP is included in the response **only**
when `APP_ENV=local`. OTPs are 6 digits and expire after 15 minutes.

## Customer endpoints

All require a verified, active, authenticated user.

| Method | Endpoint | Description |
|---|---|---|
| `GET` `PUT` | `/profile` | Read / update own profile |
| `GET` `POST` | `/orders` | List own orders / create one |
| `GET` `PUT` `DELETE` | `/orders/{order}` | Read / update / delete own order |
| `POST` | `/orders/{order}/cancel` | Cancel own order — body `{ reason }`, min 5 chars |
| `GET` | `/orders/{order}/documents` | List paperwork on own order |
| `GET` | `/orders/{order}/documents/{document}/download` | Stream a document (token required) |
| `GET` | `/invoices` | Own invoices |
| `GET` | `/invoices/{invoice}` | Invoice detail |
| `POST` | `/invoices/{invoice}/refund-request` | Request a refund on a paid invoice |
| `GET` | `/notifications` | In-app feed, newest first |
| `PATCH` | `/notifications/read-all` | Mark all read |
| `PATCH` | `/notifications/{notification}/read` | Mark one read — 403 if not the owner |
| `GET` `POST` | `/tickets` | Support threads |
| `GET` | `/tickets/{ticket}` | Thread with messages |
| `POST` | `/tickets/{ticket}/messages` | Reply |

Customer orders are at `/orders` — **not** `/user/orders`. Ownership is enforced in
`OrderService`; admins bypass it. `PUT /orders/{order}` ignores `status`: transitions are
admin-only.

Changing `email` or `password` through `PUT /profile` requires `current_password`. An email
change nulls `email_verified_at` and re-sends the verification mail.

## Admin endpoints

Prefix `/admin`, and require `role = admin`.

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/admin/dashboard` | Platform stats |
| `GET` | `/admin/orders` | All orders (`?per_page=N`) |
| `GET` | `/admin/orders/{order}` | Order detail |
| `GET` | `/admin/orders/{order}/audit-log` | Change history |
| `PATCH` | `/admin/orders/{order}/status` | Move the lifecycle forward |
| `PATCH` | `/admin/orders/{order}/bid` | Bid information |
| `PATCH` | `/admin/orders/{order}/location` | Pickup / departure / destination |
| `PATCH` | `/admin/orders/{order}/shipping` | Vessel, container, ETA — partial write |
| `POST` | `/admin/orders/{order}/cancel` | Cancel at any stage except delivered |
| `POST` `DELETE` | `/admin/orders/{order}/documents[/{document}]` | Upload / delete paperwork |
| `POST` | `/admin/orders/{order}/invoices` | Raise an invoice |
| `GET` | `/admin/invoices`, `/admin/invoices/{invoice}` | Invoice list / detail |
| `PATCH` | `/admin/invoices/{invoice}/refund` | Advance a refund |
| `GET` `POST` | `/admin/users` | User list / create |
| `GET` `PUT` `DELETE` | `/admin/users/{user}` | User detail / update / delete |
| `PATCH` | `/admin/users/{user}/archive`, `/activate` | Suspend / restore access |
| `POST` | `/admin/users/{user}/reset-password` | Issue temporary credentials |
| — | `/admin/roles` (apiResource) | Role CRUD |
| `POST` | `/admin/roles/assign` | Assign a role to users |
| `POST` `DELETE` | `/admin/roles/{role}/users/{user}` | Add / remove one user |
| `GET` | `/admin/tickets`, `/admin/tickets/{ticket}` | Support queue |
| `POST` | `/admin/tickets/{ticket}/messages` | Staff reply |
| `PATCH` | `/admin/tickets/{ticket}/status` | Move a ticket |
| `GET` `PUT` | `/admin/settings` | Platform settings (the NGN/USD exchange rate) |

Each admin sub-group also declares a `permission:` middleware. Note that the outer
`role:admin` gate currently makes that layer inert — see `CLAUDE.md` for the detail.

## Configuration endpoint

```http
GET /api/v1/config
```

One ~25 KB payload with every enum label, freight rate, port, charge, disclosure, the
current exchange rate and the permission catalogue. The SPA renders all of its dropdowns
from it, which is why the API never hardcodes an option list on the client.

Served with `ETag` and `max-age=300`, so a repeat visit takes a 304 with no body while an
exchange-rate change still becomes visible within five minutes.
`GET /api/v1/config/stats` serves the landing page's counters.

## Creating an order

```http
POST /api/v1/orders
Authorization: Bearer <token>

{
  "vin": "1HGCM82633A004352",
  "auction_source": "Copart",
  "condition": "Run and Drive",
  "already_purchased": false,
  "bid_price": 7500,
  "services": ["trucking", "shipping"],
  "vehicle_type": "sedan",
  "pickup_location": "tx",
  "departure_port": "freeport_tx",
  "destination_port": "tin_can_lagos"
}
```

| Field | Values |
|---|---|
| `auction_source` | `Copart`, `IAAI` |
| `condition` | `Run and Drive`, `Non-Runner`, `Forklift` |
| `already_purchased` | `true` ⇒ requires `vehicle_stock_no`, `buyer_no`, `buyer_code`; `false` ⇒ requires `bid_price` |
| `services` | `trucking`, `shipping` |
| `vehicle_type` | `hatchback`, `sedan`, `coupe`, `mid_suv`, `full_suv`, `lux_suv`, `minivan`, `pickup_std`, `pickup_full`, `commercial_van` |
| `pickup_location` | 2-letter lowercase US state (`tx`, `ny`, …) |
| `departure_port` | one of 10 US ports |
| `destination_port` | one of 12 African ports |
| `status` | set by admins only — `pending`, `processing`, `pickup`, `in_transit`, `at_port`, `on_vessel`, `delivered`, `cancelled` |

## Order lifecycle

```
pending → processing → pickup → in_transit → at_port → on_vessel → delivered
                                                              ↘ cancelled (any stage)
```

`in_transit` is inland US trucking to the export port; `on_vessel` is ocean freight.
Customers may cancel only while `pending`/`processing`; admins at any stage except
`delivered`. Every transition writes an `OrderAuditLog` entry.

## Payments and refunds

Invoices are paid through Paystack. `InvoiceService::create()` converts USD to NGN with the
admin-set exchange rate, opens a Paystack transaction and stores its `authorization_url`.
The Paystack reference is a UUID (`jig_…`); `INV-000001` is a display label only.

A refund is a **separate axis from payment status** — the invoice stays `paid` while
`refund_status` walks `requested → approved → processed`, or `→ rejected`. Allowed
transitions live in `RefundService::ALLOWED`.

## Scheduled work

`routes/console.php` registers:

| Schedule | Command |
|---|---|
| hourly | `jigila:send-payment-reminders` — reminds on unpaid invoices, once per 24 h, capped at 5 |
| daily | `sanctum:prune-expired --hours=24` |
| daily | `model:prune` — soft-deleted invoices older than 90 days |

**None of this runs unless `schedule:run` is in cron on the server** and a queue worker is
consuming the `database` queue.

## Configuration worth knowing

| Key | Why it matters |
|---|---|
| `APP_DEBUG` | **Must be `false` outside local.** Also gates whether the OTP is echoed. |
| `DB_CONNECTION` | `sqlite` for dev; MySQL/MariaDB/PostgreSQL in production |
| `QUEUE_CONNECTION` | `database` — needs a worker, or no mail is ever sent |
| `MAIL_*` | SMTP credentials; `log` is fine for local |
| `PAYSTACK_SECRET_KEY` / `PAYSTACK_PUBLIC_KEY` | Without these, invoices are created with no payment link |
| `ORDER_FREE_CANCELLATION_MINUTES` | No-charge cancellation window (default 60) |
| `PAYMENT_REMINDER_INTERVAL_HOURS` / `PAYMENT_REMINDER_MAX` | Reminder cadence (24 / 5) |

The NGN/USD **exchange rate is not an env var** — it lives in the `settings` table and is
set by an admin through `PUT /admin/settings`. Invoices cannot be paid until it is set.

## Layout

```
app/
├── Console/Commands/   SendPaymentReminders
├── Enums/              15 — OrderStatus, Permission, DeparturePort, DestinationPort, …
├── Http/
│   ├── Controllers/    Thin; validate, delegate, return a Resource
│   │   └── Admin/
│   ├── Middleware/     CheckRole, CheckPermission, EnsureUserIsActive, SlidingTokenExpiry
│   ├── Requests/       One per operation; all validation lives here
│   ├── Resources/      Every response shape
│   └── Traits/         ApiResponse
├── Mail/               7 mailables
├── Models/             Annotated with @property so casts are visible to tooling
└── Services/           All business logic
    └── Admin/          DashboardService, RoleService, UserService
config/
├── freight.php         The rate engine — ports, rates, charges, disclosures
└── orders.php          Cancellation window and reminder cadence
```

Business logic does not live in controllers. Invoice creation and number allocation run
inside a transaction with a row lock so concurrent creates cannot collide.

## Testing

```bash
composer check                       # lint + tests + audit — what CI runs
composer test                        # 329 tests, 827 assertions, ~13s
composer lint                        # Pint, check only
composer fix                         # Pint, apply
php artisan test --filter=OrderControllerTest
php artisan test --group=benchmark   # performance harnesses — prints timings
```

CI (`.github/workflows/ci.yml`) runs style, tests and `composer audit` as separate jobs.
Pint and the suite stay in separate processes — together they have been observed to
exhaust memory.

The suite runs on in-memory SQLite with mail captured to an array and the queue set to
`sync` — no setup required. Benchmarks use the `#[Group('benchmark')]` attribute and are
excluded from the default run.

## Frontend

The React SPA expects this API at `http://127.0.0.1:8000/api/v1`. Point it elsewhere with
`VITE_API_URL` in the frontend's `.env`.
