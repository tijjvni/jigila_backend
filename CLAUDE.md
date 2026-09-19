# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Product Overview

**Jigila** is a vehicle auction logistics platform. It sources vehicles from US auctions
(Copart, IAAI) and manages the end-to-end logistics of shipping them to Nigeria and West
Africa. This repository is the JSON API; the React SPA lives in `../boiler-frontend`
(despite the directory name, its package is `jigila-frontend`).

Two user classes: **customers**, who create and track their own vehicle imports and pay
invoices, and **admins**, who run the order lifecycle, bids, invoices, tracking, documents
and support.

## Commands

```bash
composer install && npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

Seeded accounts: `admin@jigila.com` / `password` (admin) and `user@jigila.com` /
`password` (customer), both pre-verified. `SuperAdminRoleSeeder` creates a "Super Admin"
role holding every `Permission` case.

```bash
composer dev      # server + queue listener + Pail log viewer + Vite, concurrently
composer check    # lint + tests + dependency audit — what CI runs
composer test     # config:clear then the full suite
composer lint     # Pint, check only
composer fix      # Pint, apply
php artisan test --filter=OrderControllerTest
php artisan test --filter=OrderControllerTest::test_user_can_create_order
php artisan test --group=benchmark   # performance harnesses — prints timings
```

**Run `composer check` before pushing** — it is exactly what CI runs.

Pint and the test suite are separate invocations inside `check`, and separate jobs in CI,
because in one process they have been observed to exhaust memory. `check` also does not
delegate to `@test`: Composer only substitutes `@no_additional_args` for a directly-invoked
script, so chaining it passes the literal token through to `config:clear` and fails.

Tests run on in-memory SQLite (`phpunit.xml` sets `DB_DATABASE=:memory:`) — no database
setup needed. Mail is `array`, queue is `sync`, cache is `array` under test.

**Run Pint separately from the test suite** — together they can exhaust memory.

CI (`.github/workflows/ci.yml`) runs style, tests and `composer audit` as three jobs on
pushes and PRs to `main` and `local-development`.

PHPStan is **not** installed — Pint, the test suite and the dependency audit are the only
automated checks. Adding static analysis is the obvious next gap to close.

`DEPLOYMENT.md` holds the release runbook and the pre-flight checklist.

## Architecture

**Pattern:** Controllers → Services → Models.

- **Controllers** (`app/Http/Controllers/`) are thin — extract input, call a service,
  return a response. Never query the DB from a controller.
- **Services** (`app/Services/`) hold all business logic, constructor-injected.
- **Eloquent models** (`app/Models/`) define relationships, casts and scopes.
- **Form Requests** (`app/Http/Requests/`) own all input validation.
- **API Resources** (`app/Http/Resources/`) shape every JSON response.

### Response envelope

`App\Http\Traits\ApiResponse` is used by the base `Controller` and gives every controller
four helpers:

| Helper | Shape |
|---|---|
| `okResponse($data, $status = 200)` | A Resource passes through as `{ data: ... }`; a plain array is returned as-is |
| `createdResponse($data)` | Same, status 201 |
| `messageResponse($message, $status = 200)` | `{ "message": "..." }` — no `data` key |
| `errorResponse($message, $status = 400)` | `{ "message": "..." }` |

There is **no global envelope**. A Resource response is `{ data: ... }` because that is
Laravel's Resource wrapper; a plain array response is unwrapped. Mutations that return
nothing meaningful use `messageResponse`, so **the client must not assume `data` exists on
every response**.

## Route Structure

All API routes live in `routes/api.php`.

**Everything is under `/api/v1` except the Paystack webhook**, which is deliberately
unversioned at `/api/webhooks/paystack` so the gateway's stored callback URL survives a
version bump.

Middleware stacks, outermost first:

| Group | Middleware | Notes |
|---|---|---|
| Config | `cache.headers:public;max_age=300;etag` | `GET config`, `GET config/stats` |
| Auth | `throttle:10,1` (login/register), `throttle:5,1` (OTP paths) | no token |
| Logout only | `auth:sanctum` | deliberately **not** `verified` — an unverified user must be able to log out |
| Everything else | `auth:sanctum` + `verified` + `active` | the main authenticated surface |
| Admin | the above + `role:admin` + `permission:<key>` | prefix `admin` |

`SlidingTokenExpiry` is appended to the whole `api` group in `bootstrap/app.php`: when a
request arrives with under 2 hours left on its token, the expiry is pushed to 8 hours out.
An idle token still expires; an actively used one does not log the user out mid-session.

### Customer routes (`/api/v1`)

`GET|PUT profile` · `GET|POST orders` · `GET|PUT|DELETE orders/{order}` ·
`POST orders/{order}/cancel` · `GET orders/{order}/documents` ·
`GET orders/{order}/documents/{document}/download` · `GET invoices` ·
`GET invoices/{invoice}` · `POST invoices/{invoice}/refund-request` ·
`POST invoices/{invoice}/extension-request` · `GET notifications` ·
`PATCH notifications/read-all` ·
`PATCH notifications/{notification}/read` · `GET|POST tickets` · `GET tickets/{ticket}` ·
`POST tickets/{ticket}/messages`

**Customer orders are at `/orders`, not `/user/orders`.** Ownership is enforced inside
`OrderService`, not by a URL prefix.

### Admin routes (`/api/v1/admin`)

`GET dashboard` · `GET orders` · `GET orders/{order}` · `GET orders/{order}/audit-log` ·
`PATCH orders/{order}/status|bid|location|shipping` · `POST orders/{order}/cancel` ·
`POST|DELETE orders/{order}/documents[/{document}]` · `GET invoices` ·
`GET invoices/{invoice}` · `POST orders/{order}/invoices` ·
`PATCH invoices/{invoice}/refund` · `PATCH invoices/{invoice}/deadline` ·
`PATCH invoices/{invoice}/extension` · `POST invoices/{invoice}/late-fee-invoice` ·
`POST orders/{order}/release-hold` · `GET|POST users` · `GET|PUT|DELETE users/{user}` ·
`PATCH users/{user}/archive|activate` · `POST users/{user}/reset-password` ·
`apiResource roles` · `POST roles/assign` · `POST|DELETE roles/{role}/users/{user}` ·
`GET tickets` · `GET tickets/{ticket}` · `POST tickets/{ticket}/messages` ·
`PATCH tickets/{ticket}/status` · `GET|PUT settings`

## Authorization

Two layers, and it is important to understand how they currently interact.

**`CheckRole` (`role:admin`)** wraps the entire admin group and requires
`users.role === 'admin'`.

**`CheckPermission` (`permission:<key>`)** guards each admin sub-group and calls
`User::hasPermission()`, which:
1. returns `true` immediately when `role === 'admin'`;
2. otherwise unions the `permissions` JSON arrays of the user's `adminRoles`;
3. treats `<resource>.manage` as satisfying `<resource>.view` — **`.manage` implies
   `.view`**, so granting `orders.manage` alone is enough to read and write orders.

**Known limitation:** because `role:admin` wraps everything and `hasPermission()`
short-circuits for admins, the `permission:` layer cannot currently change any outcome. A
granular staff member (`role = 'user'` with an assigned `Role`) is rejected by `CheckRole`
before any permission check runs, and a real admin passes every check regardless of
assigned roles. The roles/permissions UI and the 11 `Permission` cases are wired end to
end, but the gate is not yet load-bearing. No test covers a non-admin reaching an admin
route with a granted permission. Dropping the outer `role:admin` wrapper and letting each
route's own `permission:` declaration stand as the gate is what would activate it — until
then, do not assume the permission layer is enforcing anything.

`Permission` cases (11): `dashboard.view`, `orders.view`, `orders.manage`, `users.view`,
`users.manage`, `roles.manage`, `invoices.view`, `invoices.manage`, `support.view`,
`support.manage`, `settings.manage`.

`EnsureUserIsActive` (`active`) returns 403 for `users.status === 'archived'`, so archiving
a user cuts off their existing tokens without revoking them.

## Authentication Flow

1. `POST auth/register` or `auth/login` → Sanctum bearer token
2. Send `Authorization: Bearer <token>` on every protected request
3. **Email verification is enforced** — the whole authenticated surface sits behind
   `verified`. A registered but unverified user can only call `auth/logout` and
   `auth/email/resend`. `GET auth/email/verify/{id}/{hash}` is a **signed** route.
4. Password reset: `auth/forgot-password` → `auth/verify-otp` → `auth/reset-password`.
   OTPs are 6 digits, expire after 15 minutes, live in `password_reset_tokens`.
   `forgot-password` returns a generic message whether or not the email exists (no account
   enumeration); the OTP is included in the response **only** in the `local` environment.

## Key Data Model

- **`users`** — `id, first_name, last_name, name, email, phone, password, role, status`.
  `role` is `user|admin`; `status` is `active|archived`. Soft-deleted.
  Relations: `orders()`, `invoices()`, `notifications()`, `tickets()`,
  `adminRoles()` (BelongsToMany through `role_user`).
- **`orders`** — `user_id` (cascade), `vin`, `stock_id`, `auction_source`, `condition`,
  `already_purchased`, `bid_price` (decimal:2), `vehicle_stock_no`, `buyer_no`,
  `buyer_code`, `services` (JSON array), `status`, `vehicle_type`, `pickup_location`,
  `departure_port`, `destination_port`, plus admin-only shipping fields (`vessel_name`,
  `container_number`, `shipping_tracking_number`, `shipping_line`, `shipping_type`,
  `current_vessel_location`, `port_received_at`, `eta_start`, `eta_end`), cancellation
  fields (`cancelled_at`, `cancellation_reason`), port-authority condition
  (`port_condition`, `port_condition_confirmed_at`, `port_condition_note`) and shipment
  hold (`status_before_hold`, `shipment_hold`, `shipment_hold_reason`, `shipment_held_at`).
  Soft-deleted.
- **`invoices`** — `user_id`, `order_id`, `invoice_number` (display only),
  `type`, `description`, `amount` (decimal:2), `status` (`pending|paid|cancelled`),
  `due_date`, `paid_at`, `payment_reference` (Paystack UUID), `payment_url`, `metadata`
  (JSON), reminder fields (`last_reminded_at`, `reminder_count`, `reminder_stages_sent`),
  refund fields (`refund_status`, `refund_amount`, `refund_reason`, `refund_requested_at`,
  `refund_processed_at`, `refund_processed_by`), deadline fields (`payment_due_at`,
  `deadline_hours`, `overdue_at`), late-fee fields (`late_fee_mode`, `late_fee_rate`,
  `late_fee_amount`, `late_fee_days`, `late_fee_accrued_at`, `late_fee_invoiced_at`) and
  extension fields (`extension_status`, `extension_requested_hours`,
  `extension_granted_hours`, `extension_reason`, `extension_requested_at`,
  `extension_reviewed_at`, `extension_reviewed_by`, `original_payment_due_at`).
  Soft-deleted, prunable after 90 days.
- **`order_documents`** — shipping paperwork on the **private `local` disk**.
- **`order_audit_logs`** — `order_id`, `user_id`, `action`, `old_values`, `new_values`.
- **`tickets`** / **`ticket_messages`** — support threads; messages carry attachments.
- **`notifications`** — in-app feed; `read` is derived from `read_at !== null`.
- **`roles`** / **`role_user`** — named roles with a `permissions` JSON array.
- **`settings`** — flat key/value, cached a day via `Setting::get()`/`set()`.
  `exchange_rate` (NGN per USD) lives here and gates invoice creation. The payment
  deadline and late-fee schedule are overridable here too (`payment_deadline_hours`,
  `deadline_extension_max_hours`, `late_fee_mode`, `late_fee_flat_amount`,
  `late_fee_percent`, `late_fee_grace_hours`, `late_fee_max_days`, `late_fee_cap_percent`),
  each falling through to its `config/orders.php` default when unset.

`due_date` is a **`date`** column (day granularity). The hour-precision deadline lives in
**`payment_due_at`** (timestamp); `due_date` is written alongside it as the date part,
because the invoice email and the admin invoice panel both still read it. Write both or
they drift.

## Order Lifecycle

`OrderStatus` has 9 cases — 8 in sequence plus one off-pipeline:

```
pending → processing → pickup → in_transit → at_port → on_vessel → delivered
                                                              ↘ cancelled (any stage)
                                                              ↘ payment_overdue (any stage, reversible)
```

`in_transit` is inland US trucking to the export port; `on_vessel` is ocean freight.

**`payment_overdue` is system-owned and is not a pipeline stage.** The deadline sweep
writes it together with `shipment_hold`, stashing the real stage in `status_before_hold`;
payment, an approved extension or an explicit release restores it. It is excluded from
`OrderStatus::assignableValues()`, so `PATCH admin/orders/{order}/status` rejects it —
admins lift a hold via `POST admin/orders/{order}/release-hold`. Any policy question
(can this be cancelled, has it passed a milestone) must read `Order::effectiveStatus()`,
never `status`, or a hold silently changes the answer.

**Adding a status means updating all six of these:**
1. `app/Enums/OrderStatus.php`
2. `app/Http/Controllers/ConfigController.php` (the `order_statuses` labels)
3. A migration extending the MySQL enum column
4. `src/components/shared/status-badge.component.tsx` (frontend colour map)
5. `src/domains/admin_portal/orders/components/tracking-status-section.component.tsx`
6. `src/domains/user_portal/orders/pages/order-detail.page.tsx`

`payment_overdue` is deliberately absent from (5): it is not admin-assignable, so listing
it in a stage array would offer a dropdown option the API then rejects.

Status transitions are admin-only via `PATCH admin/orders/{order}/status`. The customer
`PUT orders/{order}` route ignores `status` entirely.

## Enums

17 in `app/Enums/`:

| Enum | Values |
|---|---|
| `OrderStatus` | 9, above |
| `AuctionSource` | `Copart`, `IAAI` — **not** `Co-parts` |
| `VehicleCondition` | `Run and Drive`, `Non-Runner`, `Forklift` |
| `VehicleType` | `hatchback`, `sedan`, `coupe`, `mid_suv`, `full_suv`, `lux_suv`, `minivan`, `pickup_std`, `pickup_full`, `commercial_van` |
| `ServiceType` | `trucking`, `shipping` |
| `BidStatus` | `pending`, `won`, `lost`, `out_bid` |
| `InvoiceType` | `bid`, `service`, `bid_deposit`, `bid_balance`, `late_fee` |
| `RefundStatus` | `requested`, `approved`, `processed`, `rejected` |
| `DeadlineExtensionStatus` | `requested`, `approved`, `rejected` |
| `LateFeeMode` | `none`, `flat`, `percent` |
| `TicketStatus` | `open`, `in_progress`, `resolved`, `closed` — there is no `processing` |
| `DocumentType` | `bill_of_lading`, `invoice`, `export_title`, `dock_receipt`, `vehicle_release_form`, `shipping_receipt`, `auction_purchase_receipt`, `other` |
| `ShippingLine` | `sallaum`, `grimaldi`, `maersk`, `cma_cgm` |
| `ShippingType` | `roro`, `container` |
| `DeparturePort` | 10 US ports |
| `DestinationPort` | 12 African ports |
| `Permission` | 11 keys, above |

**Departure ports** (10): `baltimore_md`, `dundalk_baltimore_md`, `newark_nj`,
`philadelphia_pa`, `wilmington_de`, `providence_ri`, `savannah_ga`, `jacksonville_fl`,
`miami_fl`, `freeport_tx`.

**Destination ports** (12): `lagos_apapa`, `tin_can_lagos`, `tema_ghana`, `lome_togo`,
`cotonou_benin`, `abidjan_ivory_coast`, `dakar_senegal`, `conakry_guinea`,
`freetown_sierra_leone`, `monrovia_liberia`, `banjul_gambia`, `bissau_guinea_bissau`.

**Pickup locations** are all 50 US states as 2-letter lowercase codes (`al`, `tx`, `ny`…).

## The Config Endpoint

`GET /api/v1/config` is a single ~25 KB payload the SPA fetches on nearly every page load:
enum labels, freight rates, ports, charges, disclosures, the exchange rate and the
permission catalogue. It is served with `ETag` + `max-age=300` so repeat visits take a 304
with no body, while an exchange-rate change is visible within five minutes.
`GET /api/v1/config/stats` serves the landing page's marketing counters.

Because the SPA renders dropdowns from this payload, **an enum added here but missing from
the matching Form Request validation produces a selectable option the API then rejects.**
Update both.

## Freight Configuration

`config/freight.php` holds the rate engine: `vehicle_types`,
`trucking_vehicle_multipliers`, `trucking_condition_surcharges`, `condition_disclosures`,
`large_vehicle_types`, `condition_reclassification`, `charges`, `range_pct`,
`trucking_sedan_rates`, `departure_ports`, `destination_ports`.

- **Key order is load-bearing.** `ConfigController` zips `departure_ports` positionally
  against each `trucking_sedan_rates` row, and every destination port's `transit_days` map
  must use the same keys in the same order. Changing the port set means updating the enum,
  all three config arrays, the Form Request validation **and** `STATE_SUGGESTED_PORT` in
  the frontend's `src/lib/freight.ts` together.
- `charges` holds the platform fees (Jigila flat rate, auction account handling, FX
  offshore). A `percent` charge with `basis: 'total'` resolves **after** the flat fees and
  any departure-port `surcharge`.
- A departure port may carry a flat `surcharge` (Port Freeport, TX is +$100), deliberately
  separate from `shipping_offset` so the customer sees it as its own line.
- `orders.port_condition` is what the **export port authority** confirmed on inspection;
  `orders.condition` stays as booked. A vehicle sold as a runner is regularly downgraded at
  the port, and the billable amount is the *difference* between the two
  `condition_disclosures` fees — never the full fee. Recording it does not raise an invoice.

## Invoices & Payments

`InvoiceService::create()` converts USD to NGN using `Setting::get('exchange_rate')`,
initialises a Paystack transaction, and stores the returned `authorization_url` as
`payment_url`. If the rate is unset (or ≤ 0) the invoice is still created but without a
payment link, and the failure is logged rather than thrown.

- **The Paystack reference is a UUID** — `'jig_' . Str::uuid()`, *not* the invoice number.
  `INV-000001` is a display label only.
- The invoice number is allocated under `Invoice::lockForUpdate()->max('id')` inside a
  transaction, so concurrent creates cannot collide.
- Rich creation context is written into `metadata` **before** the Paystack call, so
  analytics survive a gateway failure.
- `POST /api/webhooks/paystack` marks the invoice paid and writes an `order_audit_logs`
  entry.
- **`InvoiceResource` (list) omits `metadata`**; `InvoiceDetailResource` (admin detail)
  includes it. Metadata is ~46% of a serialised paid invoice and is never rendered in a
  table. A component that needs it fetches the detail endpoint.

## Refunds

A refund is a **separate axis from `status`** — an invoice stays `paid` while
`refund_status` walks `requested → approved → processed`, or `→ rejected`. This keeps the
payment ledger honest and lets the customer see both facts at once.

Allowed transitions live in `RefundService::ALLOWED`, keyed by current state, and are
mirrored in the frontend's `refund-section.component.tsx` — **change both together**. Only
a `paid` invoice can be refunded, and only one open request at a time.

## Order Cancellation

Customers may cancel only while `pending`/`processing`; admins may cancel at any stage
except `delivered`. Cancelling twice returns 422 so the original audit entry survives.
`config/orders.php` `free_cancellation_minutes` (default 60) is the no-charge window.
`OrderResource.can_cancel` is **server-derived** — the client shows or hides the button
from it rather than re-deriving the policy.

## Order Documents

Files live on the **private `local` disk** and stream through an authorised controller
action — never public storage. Listing and download are open to the order's owner and to
admins; upload and delete are admin-only. The client must attach the bearer token to the
download request (`downloadWithAuth()` on the frontend); a plain `<a href>` 401s.

## Notifications & Payment Reminders

In-app notifications are written by `NotificationService::createInApp()`. Every public
method pairs an in-app record with a queued email, and **every mail send is wrapped in
try/catch and logged** — a mail failure must never break the business operation that
triggered it.

Dispatch points: welcome, invoice created, payment reminder, document uploaded, order
cancelled, shipping updated, refund status, ticket created/replied.
`notifyAdmins()` fans out to all admins via `chunkById`.

**Payment deadlines** (spec 4). Two commands, both scheduled
`everyFifteenMinutes()->withoutOverlapping()` in `routes/console.php`:

| Command | Does |
|---|---|
| `jigila:send-payment-reminders` | Fires the reminder stages due for each unpaid invoice |
| `jigila:process-payment-deadlines` | Marks lapsed invoices overdue + holds the shipment, then accrues late fees |

Reminders are **anchored to the invoice's own deadline**, not a rolling interval:
`config('orders.payment_reminder_offsets_hours')` is `[48, 24, 6, 0]` — hours before
`payment_due_at`, where `0` means at expiry. Each stage is recorded in
`invoices.reminder_stages_sent` as it goes out, so **a double run never double-sends** and
a scheduler outage produces a late notice rather than a duplicate. A stage whose trigger
time falls at or before `created_at` is *consumed unsent* — on a 24-hour deadline, "48
hours left" was never true, and "24 hours left" would land beside the invoice email.
Approving an extension clears the column so the new deadline re-arms every stage.

Fifteen minutes, not hourly: the expiry notice and the hold that follows are anchored to an
exact deadline on an invoice that is accruing a fee, and an hourly sweep lands them up to
59 minutes late.

**Late fees** accrue in `LateFeeService`. Two rules keep it safe on a scheduler:
accrual **recomputes `f(days_overdue)` from scratch** rather than incrementing (so a
re-run, or a week-long catch-up, lands on the same number), and the mode/rate are **frozen
onto the invoice at issuance** (`late_fee_mode`, `late_fee_rate`) so changing the schedule
in settings never re-prices an invoice already issued. A day counts as "a day or part
thereof"; the diff is truncated to whole minutes first, because Carbon's microsecond
precision would otherwise charge an extra day at an exact boundary. Both caps apply:
`max_days` and `cap_percent` of the invoice.

A fee is **billed as its own `late_fee` invoice** (`POST admin/invoices/{id}/late-fee-invoice`),
never added to the parent — the parent's Paystack transaction was initialised for a fixed
amount at issuance, so a growing fee has nowhere to go on that link.

**Deadline extensions** are one-time per invoice. The customer requests
(`POST invoices/{id}/extension-request`), an admin approves or declines
(`PATCH admin/invoices/{id}/extension`). Approving moves `payment_due_at` forward from
whichever is later, the old deadline or now, lifts any hold, and **freezes** late fees
already accrued — the extension forgives further accrual, not the days already late. Pass
`waive_accrued_fees` to clear them too. Transitions live in
`PaymentDeadlineService::ALLOWED`; a declined request may be replaced, an approved one is
final.

Note `sendPaymentDeadlineReminder()` and `notifyPaymentOverdue()` use `PaymentDeadlineMail`
(`emails.payment-deadline`), which covers all five stages.

The scheduler also runs `sanctum:prune-expired --hours=24` and `model:prune` daily.
**None of this fires unless `schedule:run` is in cron and a queue worker is running**
(`QUEUE_CONNECTION=database`, and the mailables are dispatched with `Mail::queue()`).

## Audit Logging

`OrderAuditLog` records `action`, `old_values`, `new_values` and the acting user for
order-affecting operations (status, bid, location, shipping, cancellation, invoice
generation, payment received). **Audit writes belong in the service, not the controller** —
they were centralised there deliberately. Admins read the trail via
`GET admin/orders/{order}/audit-log`.

## Dashboard

`GET admin/dashboard` returns `total_orders`, `total_users`, `total_revenue`,
`total_invoiced`, `total_paid`, `total_outstanding`, `active_shipments`,
`order_completion_rate`, `average_order_value`, `orders_by_status`,
`orders_by_auction_source`, `orders_by_month`, `revenue_by_service`.

- **Aggregate in SQL, not PHP.** `revenueByService()` once streamed every order through
  PHP and took 1.5 s at 20k orders; the SQL version is ~97× faster. Do not reintroduce
  `cursor()`-and-sum patterns on unbounded tables.
- `DashboardService` branches on `DB::connection()->getDriverName()` and handles
  `'sqlite'`, `'pgsql'`, `'mysql'` and **`'mariadb'`**. Always add `mariadb` alongside
  `mysql` when branching on driver.

## Testing

398 tests, 972 assertions, ~14 seconds on in-memory SQLite. 31 feature tests, 7 unit tests.

Benchmarks are marked with the `#[Group('benchmark')]` **attribute** — PHPUnit 12 ignores
the old `@group` docblock — and are excluded in `phpunit.xml`. Run them deliberately with
`php artisan test --group=benchmark`.

Notable guards worth keeping green: `QueryBudgetTest` (N+1 regressions),
`ConfigCachingTest` (ETag/304 behaviour), `InvoiceMetadataExposureTest` (list must not leak
metadata), `RevenueByServiceCorrectnessTest` + `RevenueAggregationBenchmarkTest` (the SQL
aggregate), `OrderStatusTimestampsTest`, `SendPaymentRemindersTest`, and the spec-4 set:
`PaymentDeadlineTest`, `PaymentOverdueHoldTest` (which asserts `payment_overdue` survives a
round trip through SQLite's CHECK constraint, and that an overdue invoice stays `pending`
for the webhook), `LateFeeAccrualTest`, `DeadlineExtensionTest`.

## Key Invariants

- **Controllers delegate to services** — never query the DB in a controller.
- **Paystack reference is a UUID**, not the invoice number.
- **Customer orders live at `/orders`**, not `/user/orders`; ownership is enforced in
  `OrderService`, and admins bypass the ownership check.
- **Forbidden actions return 403**; a missing model returns a 404 shaped as
  `{"message": "Order not found."}` by the handler in `bootstrap/app.php`.
- **`listAll()` vs `list()`** — `InvoiceService::list(User $user)` returns one user's
  invoices, `listAll()` returns every invoice (admin). Never bypass either with a raw query.
- **Port validation** — `UpdateOrderLocationRequest` validates ports with `Rule::in()`.
  Adding a port to `ConfigController` without adding it here produces a selectable option
  the API rejects.
- **`.manage` implies `.view`** in `User::hasPermission()` — granting `orders.manage` alone
  also grants read access.
- **MariaDB** — always handle `'mariadb'` wherever `'mysql'` is handled.
- **Aggregate in SQL**, never by streaming rows through PHP.
- **Mail failures are caught and logged**, never allowed to break the operation.
- **`already_purchased` is set at creation and never changed.** When `true` the customer
  already owns the vehicle, so `StoreOrderRequest` requires `vehicle_stock_no`/`buyer_no`/
  `buyer_code`; when `false` it requires `bid_price`. The frontend hides the bidding
  section entirely for `already_purchased` orders.
- **Refund status is not invoice status** — a refunded invoice stays `paid`.
- **An overdue invoice stays `pending`.** Overdue lives on `invoices.overdue_at` and on the
  order, never on `invoices.status`. `WebhookController` locks on
  `where('status', 'pending')`, so adding an `overdue` invoice status would make every
  payment landing after a deadline silently fail to register.
- **`payment_overdue` is not a pipeline stage** — read `Order::effectiveStatus()` for any
  policy decision, and never let an admin set the status directly.
- **Late fees recompute, never increment** — that is what makes the sweep safe to re-run.
- **Late fee terms are frozen at issuance** — never re-price an issued invoice from the
  live settings.
- **`port_condition` is not `condition`** — the billable amount is the difference between
  the two disclosure fees.

## The Frontend

`../boiler-frontend` is the React SPA that consumes this API (its package is
`jigila-frontend`; the directory name is historical). Its `CLAUDE.md` documents the same
contract from the client side, including which server fields each screen depends on.

Changes that must be made in both repositories at once:

| Change | Also update |
|---|---|
| New `OrderStatus` case | The 6 places listed under **Order Lifecycle** |
| New or removed port | `config/freight.php` (×3 arrays), the enum, the Form Request, and `src/lib/freight.ts` |
| `RefundService::ALLOWED` transitions | `refund-section.component.tsx` |
| Shipment-hold or deadline fields | `order.types.ts` / `invoice.types.ts`, and the timelines that read `effective_status` |
| Any new enum exposed via `/config` | The matching Form Request validation |
