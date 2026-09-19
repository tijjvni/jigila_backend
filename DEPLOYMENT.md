# Deployment

Runbook for the Jigila API. The companion SPA deploys separately — see
`../boiler-frontend/README.md`.

## Pre-flight checklist

Work through this before a release is called done. Items marked **blocking** cause silent
failures in production, not loud ones.

### Environment

- [ ] **`APP_ENV=production`** — blocking, and it is not cosmetic. Three code paths call
      `app()->isLocal()` and leak secrets when it is `local`: `AuthController` line 49
      returns the password-reset **OTP in the HTTP response**, and `AuthService` /
      `ProfileService` do the same. Deploying with `APP_ENV=local` makes password reset an
      account-takeover path for anyone who can name an email address.
- [ ] **`APP_DEBUG=false`** — blocking. With it true, stack traces (including connection
      strings) are returned to API clients on any 500.
- [ ] `APP_KEY` set (`php artisan key:generate`) and **not** the one from another
      environment — rotating it invalidates every encrypted value.
- [ ] `APP_URL` is the public API origin, used to sign email-verification links.
- [ ] `FRONTEND_URL` is the public SPA origin. Email verification redirects here after the
      signed link is consumed; a wrong value lands verified users on a dead page.
- [ ] `CORS_ALLOWED_ORIGINS` lists the real SPA origin(s) and **not** `localhost` or a
      wildcard.
- [ ] `SANCTUM_TOKEN_EXPIRATION` reviewed — the example ships 10080 minutes (7 days).
- [ ] `DB_CONNECTION` is the production driver with credentials; SQLite is a dev default.
- [ ] `MAIL_*` point at a real transport and `MAIL_FROM_ADDRESS` is a deliverable sender.
- [ ] `PAYSTACK_SECRET_KEY` / `PAYSTACK_PUBLIC_KEY` are **live** keys, not test keys.
- [ ] `PAYSTACK_CALLBACK_URL` points at the deployed SPA, not `localhost:3000`.

### Runtime services

- [ ] **A queue worker is running** — blocking. Every mail send uses `Mail::queue()` and
      `QUEUE_CONNECTION` is `database`. With no worker, nothing fails and no email is ever
      delivered: welcome mail, invoice notices, password reset, payment reminders.
- [ ] **The scheduler is in cron** — blocking. Payment reminders, expired-token pruning and
      soft-delete pruning all hang off `schedule:run`.
- [ ] The web server points at `public/`, not the project root.
- [ ] `storage/` and `bootstrap/cache/` are writable by the web user.

### Application state

- [ ] `php artisan migrate --force` has run against the production database.
- [ ] **The NGN/USD exchange rate is set** — blocking. It lives in the `settings` table,
      not in env. Until an admin sets it via `PUT /admin/settings`, `InvoiceService::create`
      logs an error and every invoice is created with **no payment link**. Set it before
      taking real orders.
- [ ] At least one admin account exists and is email-verified.
- [ ] Seeder accounts (`admin@jigila.com` / `user@jigila.com`, both `password`) are
      **removed or have had their passwords changed**. Never run `db:seed` against
      production.

### Verification

- [ ] `GET /up` returns healthy.
- [ ] `GET /api/v1/config` returns 200 and carries an `ETag`.
- [ ] Register → verify email → login works end to end against the deployed SPA.
- [ ] A test invoice produces a working Paystack link and the webhook marks it paid.

## Cron and queue

The scheduler needs exactly one cron entry:

```cron
* * * * * cd /path/to/jigila_backend && php artisan schedule:run >> /dev/null 2>&1
```

The queue worker should be supervised so it restarts on failure and after a deploy. With
systemd:

```ini
# /etc/systemd/system/jigila-queue.service
[Unit]
Description=Jigila queue worker
After=network.target

[Service]
User=www-data
Restart=always
RestartSec=5
WorkingDirectory=/path/to/jigila_backend
ExecStart=/usr/bin/php artisan queue:work --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now jigila-queue
```

`--max-time=3600` recycles the worker hourly so a long-lived process cannot hold stale
code or leak memory.

**Verify both are actually running** — this is the failure mode most likely to reach
production unnoticed:

```bash
php artisan schedule:list                          # what should run, and when
php artisan queue:monitor database --max=100       # queue depth
php artisan jigila:send-payment-reminders --dry-run  # lists without sending
```

A `jobs` table that only grows is a worker that is not running.

## Release steps

```bash
git pull

composer install --no-dev --optimize-autoloader
php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan event:cache

sudo systemctl restart jigila-queue
```

**Restart the queue worker on every deploy.** A running worker holds the old code in
memory and will keep executing it against the new database schema.

`config:cache` means `env()` outside a config file returns `null`. Read configuration
through `config()`, never `env()`, outside `config/`.

### Rollback

```bash
git checkout <previous-sha>
composer install --no-dev --optimize-autoloader
php artisan config:cache && php artisan route:cache
sudo systemctl restart jigila-queue
```

Migrations are **not** rolled back automatically. Check whether the release contained one
before reverting; `migrate:rollback` on a release that dropped a column loses the data in
it. Take a database snapshot before any deploy carrying a destructive migration.

## Private file storage

Order documents live on the private `local` disk at `storage/app/private`, streamed through
an authorised controller action. They must **not** be served by the web server directly, and
`php artisan storage:link` is not needed for them — linking `storage/app/public` does not
expose the private disk, but do not move documents onto the public disk to "fix" a download
problem. A 401 on a download is the client omitting the bearer token.

Include `storage/app/private` in backups. It holds bills of lading and export titles that
exist nowhere else.

## Known gaps before go-live

These are real and currently unaddressed. Decide on each rather than discovering them in
production.

**The permission layer does not enforce anything.** Every admin route group declares a
`permission:` middleware, but the whole block is wrapped in `role:admin`, and
`User::hasPermission()` returns `true` immediately for admins. A staff member with
`role = 'user'` and a granted permission is rejected by `CheckRole` before any permission
check runs. The roles UI, the 11 `Permission` cases and the middleware are all wired, but
the gate cannot change an outcome. **Any admin is effectively a full admin.** If the plan
is to give staff scoped access, that must be closed first.

**No static analysis.** PHPStan is not installed. Pint and the test suite are the only
automated checks.

**`invoices.due_date` is a `date` column that nothing writes.** Any payment-deadline work
needs a migration to `datetime` first.

**Payment reminder emails reuse `InvoiceCreatedMail`.** A customer receiving a reminder gets
an email that reads as a brand-new invoice.
