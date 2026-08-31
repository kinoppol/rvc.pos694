# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A multi-merchant/multi-branch POS & ERP web application (Thai-language retail), implementing the 5 screens originally mocked up in [project/POS ERP Mockups.dc.html](project/POS%20ERP%20Mockups.dc.html):

- **1a/1b** POS checkout (`/pos`) + payment popup (`/pos/pay`) — product grid, size×color variant matrix, cart, PromptPay/cash/card payment
- **1c** Merchant dashboard (`/dashboard`) — dark-mode KPIs, per-branch performance, top products, stock alerts
- **1d** Staff attendance (`/attendance`) — mobile GPS geofenced clock-in/out
- **1e** Member profile (`/members/{id}`) — purchase history, loyalty points, birthday promo

Screens added since the original 5 mockups (same conventions, no new mockup files):

- **Product management** (`/products`, owner/manager) — CRUD over `products` + variant matrix
- **Staff management** (`/staff`, owner/manager) — CRUD over users, enable/disable, PIN
- **Store settings** (`/store`, owner) — merchant profile (phone/email/address) + branch CRUD
- **Merchant self-registration** (`/register`, public) — creates a `merchants` row + owner user; lands `pending` when `system_settings.require_approval = 1`, else `active`
- **Platform admin console** (`/admin/merchants`, `/admin/settings`) — gated by `PlatformAdminMiddleware` (session user must have `is_platform` + `owner` role, i.e. belong to the `merchants.is_platform = 1` tenant created by `install.php`). Approve/suspend merchants and **impersonate** them (`POST /admin/merchants/{id}/impersonate` swaps the session tenant; `POST /admin/impersonate/stop` restores it — that stop route sits outside the platform-admin group on purpose).

Backend: **PHP 8+ with no framework and no Composer dependency** (a deliberate deviation from an earlier plan to use Slim — this environment has no internet access for `composer install`, and a dependency-free app is also simply easier to deploy on typical shared/XAMPP hosting). Data layer: **MariaDB 10+ via PDO**, hand-rolled migrations (no ORM).

## Commands

```bash
# Start MariaDB (XAMPP)
/c/xampp/mysql_start.bat            # or via the XAMPP control panel

# Run the app locally with PHP's built-in server (dev only)
php -S localhost:8000 dev_router.php

# Then open the installer:
#   http://localhost:8000/install.php
```

There is no `composer install` / build / lint / test step — this is intentionally plain PHP with a custom `spl_autoload_register` autoloader ([app/bootstrap.php](app/bootstrap.php)). Syntax-check any file with `php -l path/to/file.php`.

**Production deployment (Apache/XAMPP):** point the vhost/htdocs folder at the project root (not a `public/` subfolder — there isn't one). [.htaccess](.htaccess) rewrites all requests without a matching real file to `index.php`; `app/`, `database/`, and `storage/` each carry their own `.htaccess` (`Require all denied`) so their contents are never served directly even though they sit next to the web root.

## Installer & migrations

- **`install.php`** is idempotent: on repeat visits it detects an existing install (config file + reachable `merchants` table) via `App\Services\InstallState::isFullyInstalled()` and shows a "already installed" screen instead of re-seeding — with explicit **Reconfigure** (re-enter DB creds, keep data) and **Reset** (type `RESET` to confirm; rolls back every migration batch and lets you reinstall clean) actions.
- Before anything touches the database, it runs `App\Services\PermissionChecker::checkAll()` — PHP version, required extensions (`pdo_mysql`, `mbstring`, `openssl`, `json`), and write-access to `app/Config/` and `storage/**`. Installation is blocked until these pass.
- Schema is applied via `App\Services\MigrationRunner`, the **same class** the admin UI uses — install = "run every migration from zero", so the two paths can't drift apart.
- **Admin → Migrations** (`/admin/migrations`, owner role only) lists every file in `database/migrations/` against what's been applied, and can run pending migrations or roll back the last batch.
- Current migrations: `0001_init_schema` (full schema), `0002_merchant_registration` (`status`/`is_platform` columns, `system_settings`), `0003_merchant_profile` (merchant phone/email/address).
- Migration files live in `database/migrations/`, named `NNNN_description.php`, each returning an anonymous object with `up(PDO $db)` / `down(PDO $db)`. **Important:** MariaDB/MySQL implicitly commits on every DDL statement, so `MigrationRunner` intentionally does *not* wrap `up()`/`down()` in an explicit PDO transaction (see the comment in [app/Services/MigrationRunner.php](app/Services/MigrationRunner.php) — wrapping DDL in `beginTransaction()`/`commit()` throws spurious "no active transaction" errors once MySQL auto-commits underneath PDO's back).

## Architecture

```
index.php                  # front controller: loads config, builds Router, dispatches
install.php                # standalone installer (works before config.php exists)
dev_router.php             # router script for `php -S` only, mimics .htaccess
.htaccess                  # Apache: real files served as-is, else -> index.php
assets/app.css             # design tokens extracted from the original mockup

app/
  bootstrap.php            # autoloader (App\ -> app/), session start, loads config.php, APP_BASE_PATH
  Router.php                # tiny regex-based router with group()/middleware support
  Config/
    config.php               # generated by install.php (DB creds + APP_KEY) — gitignored, not present until installed
    routes.php                # all route definitions
  Controllers/               # one per screen/feature: Auth, Pos, Payment, Dashboard, Attendance, Member, Migration
  Middleware/                 # AuthMiddleware (session gate), RoleMiddleware::only(...roles), PlatformAdminMiddleware (is_platform owner)
  Services/                   # Database (PDO singleton), AuthService, CartService, MigrationRunner,
                               # InstallState, PermissionChecker, DemoSeeder, GeoService (Haversine), View
  Models/Model.php            # thin base class (most data access is direct PDO in controllers, by design —
                               # this app is small enough that a full ORM/repository layer would be pure overhead)
  Views/                      # plain PHP templates, one subfolder per feature; layout/head.php + layout/sidebar.php shared

database/migrations/         # 0001_init_schema.php defines the full schema; add NNNN_*.php files for changes
storage/                     # logs/, cache/, uploads/, installed.lock — must stay writable by the webserver
```

**Multi-tenancy:** `merchants` → `branches` → everything else. Tenant-scoped tables carry `merchant_id`; branch-scoped ones also carry `branch_id`. There's no automatic query-scoping layer — controllers filter by `$user['merchant_id']` / `$user['branch_id']` explicitly per query (see any controller for the pattern). One special tenant has `merchants.is_platform = 1` (set by `install.php`, or by migration `0002` on the first-created merchant) — its owner is the platform superadmin. `merchants.status` is `pending|active|suspended`; `system_settings` (key/value, e.g. `require_approval`) holds platform-wide config.

**Auth:** PHP session (`App\Services\AuthService`), roles `owner|manager|cashier|staff`. Passwords use `password_hash()`/`password_verify()`. Cashiers also get an optional PIN (`AuthService::loginWithPin()` / `verifyPin()`) for quick terminal switching — set at install time or per-user later.

**Cart:** kept in `$_SESSION` (`App\Services\CartService`), not the database, until it's either held (`POST /pos/hold` → persisted as a `sales` row with `status='held'`) or paid (`POST /pos/pay/confirm` → `sales` + `sale_items` + `payments`, with stock deducted from `stock_levels` and member points/spend updated in the same request).

**Geofencing:** `App\Services\GeoService::distanceMeters()` (Haversine formula) compares the browser's `navigator.geolocation` coordinates against the branch's stored `lat`/`lng`/`geofence_radius_m` on every clock-in/out (`POST /attendance/clock`), recorded in `attendance_logs.within_geofence`.

## Coding conventions

**Controller methods** always have signature `public function methodName(array $args): void` where `$args` carries named URL params (e.g. `{id}` → `$args['id']`). Fetch the session user with `AuthService::currentUser()` at the top; get a DB handle from `Database::connection()`. There is no DI container — construct dependencies inline.

**Middleware** is a static factory returning a closure: `static function (callable $next): void { ... $next(); }`. Add new middleware in `app/Middleware/`, return a callable from a static method, pass it to `$router->group([...], ...)` or as the third arg to `get()`/`post()`.

**View helpers** — always use these in templates:
- `View::e($value)` — `htmlspecialchars` wrapper; **use for every user-supplied value** echoed into HTML
- `View::money(float $amount)` — formats as `฿1,234.56`
- `View::json($data, int $status)` — JSON response (sets Content-Type, exits)
- `View::render('feature/template', $data)` — `extract()`s `$data` into template scope
- `View::partial('feature/template', $data)` — same but returns string (output buffered)

**AuthService** has two modes:
- Instance methods (`new AuthService($db)->attemptLogin()`, `loginWithPin()`, `verifyPin()`) — require a PDO instance, used for authentication flows
- Static methods (`AuthService::currentUser()`, `AuthService::check()`, `AuthService::hashPassword()`) — read/write the session, usable anywhere after `bootstrap.php`

**Business rules encoded in CartService / PaymentController:**
- Prices are **VAT-inclusive** (7%). VAT is *derived*: `grand_total × 7 / 107` — do not add VAT on top.
- Birthday discount: 10% off if the member's `birthdate` month equals the current month.
- Loyalty points: 1 point per ฿100 spent (`floor(grand_total / 100)`), added to `members.points` and `members.total_spend` at payment confirmation.

**Subfolder hosting:** `APP_BASE_PATH` is auto-detected from `$_SERVER['SCRIPT_NAME']` in `bootstrap.php` — the router strips it before matching. No manual config needed unless you override the constant before including `bootstrap.php`.

## Known gaps / stubs (by design, not oversights)

- PromptPay QR is a visual placeholder (no real payment gateway integration) — the payment popup generates a reference number and lets staff manually confirm receipt, matching the mockup's own placeholder.
- SMS/LINE e-receipt buttons are UI-only, no dispatch integration.
- Stock transfers (`stock_transfers`/`stock_transfer_items` tables exist in the schema) have no controller/UI yet.
