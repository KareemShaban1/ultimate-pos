# UltimatePOS Developer System Guide

## 1) Purpose of this document

This file is a practical discovery and developer guide for the current codebase.  
It explains how the system is organized, how the main business flows work, where to start debugging, and what architectural strengths and weaknesses exist.

## 2) Stack and architecture at a glance

- Framework: Laravel 9 (`laravel/framework` in `composer.json`)
- Language/runtime: PHP 8+
- Auth:
  - Session auth (`web` guard)
  - API auth via Passport (`api` guard, `laravel/passport`)
  - Additional `customer` guard for contact login
- Authorization: Spatie roles/permissions (`spatie/laravel-permission`)
- Modular architecture: `nwidart/laravel-modules` with modules in `Modules/`
- UI rendering: Blade templates + AdminLTE/static assets under `resources/` + `public/`
- Data-heavy screens: Yajra DataTables (`yajra/laravel-datatables-oracle`)
- Integrations present in dependencies: Stripe, Razorpay, Paystack, Pesapal, PayPal, MyFatoorah, Twilio, WooCommerce, Pusher, S3/Dropbox, backup/logging packages

## 3) Top-level project map

- `app/`
  - Core MVC + utilities + middleware + events/listeners
  - Important subareas:
    - `app/Http/Controllers/` (core business controllers)
    - `app/Utils/` (heavy reusable business logic, especially transactions/module logic)
    - `app/Providers/` (bootstrapping + route/event wiring)
- `routes/`
  - `web.php` is the main route hub (large business route surface)
  - `api.php` currently minimal
- `Modules/`
  - Feature modules: `Essentials`, `Superadmin`, `Repair`, `Manufacturing`, `Crm`
  - Each module has its own controllers, routes, views, migrations, and service provider
- `database/`
  - Core migrations/seeders
- `resources/`
  - Blade views, frontend assets, language
- `config/`
  - Auth, queue, modules, permission, payment configs, etc.
- `tests/`
  - Currently default/example tests only

## 4) Request lifecycle and runtime flow

1. Entry point: `public/index.php`
2. Bootstrap app container: `bootstrap/app.php`
3. Request passes through `app/Http/Kernel.php` global middleware
4. Route middleware groups (`web`, `api`) + route-level middleware execute
5. Routes loaded by `app/Providers/RouteServiceProvider.php` from:
   - `routes/web.php`
   - `routes/api.php`
   - Module route files via module providers (`Modules/*/Routes` or `Modules/*/Http/routes.php`)
6. Controller actions execute business logic (often with `app/Utils/*`)
7. Events/listeners run (sync by default unless queue driver changed)
8. Response rendered (Blade or JSON) and returned

## 5) Core middleware and session model

From `app/Http/Kernel.php`, common business-request middleware stack includes:

- `auth`
- `SetSessionData`
- `language`
- `timezone`
- `AdminSidebarMenu`
- `CheckUserLogin`

There is also `setData` and `authh` aliasing `IsInstalled`, used broadly to gate installation state.

Business context is session-driven (business/user/currency/modules), and many controllers/utilities assume this context exists.

## 6) Module system discovery

Module package config (`config/modules.php`) indicates:

- Modules root is `Modules/`
- Module assets are published under `public/modules`
- Activation uses file activator (`modules_statuses.json`)

Installed business modules discovered in this project:

- `Essentials` (HRM/payroll/attendance/docs/messages)
- `Superadmin` (subscription/packages/multi-business ops)
- `Repair` (repair jobs, job sheets, statuses)
- `Manufacturing` (recipes/production)
- `Crm` (leads/follow-ups/campaigns/contact portal)

`app/Utils/ModuleUtil.php` is the core module orchestration utility:

- Checks installed/active module versions
- Checks subscription and quotas
- Pulls module-provided extension data via `DataController` convention (`getModuleData()`)

## 7) Main business flows

### 7.1 Authentication and authorization flow

- Public/auth routes are in `routes/web.php` (`Auth::routes()` and registration/business setup endpoints)
- Authenticated route group applies core middleware and session context
- Permission checks are commonly done in controller actions using `auth()->user()->can(...)`
- Role checks are used, including business-scoped role naming patterns
- API auth uses Passport (`auth:api` in `routes/api.php`)

### 7.2 POS sales flow (core revenue flow)

Primary endpoints and handlers are in `routes/web.php` and `app/Http/Controllers/SellPosController.php`.

Typical flow:

1. User opens POS (`Route::resource('pos', SellPosController::class)`)
2. Product/payment rows are fetched dynamically via POS helper endpoints
3. On submit/store:
   - Permission checks happen
   - Business subscription/quota checks may happen
   - Totals are computed
   - Transaction record and sell lines are created/updated via `TransactionUtil`
   - Payment lines created/updated when applicable
4. Related events (for payments/other hooks) are dispatched
5. Invoice/receipt paths include tokenized public invoice/payment endpoints (`/invoice/{token}`, `/pay/{token}`, `/confirm-payment/{id}`)

Core dependency here:

- `app/Utils/TransactionUtil.php` (critical for sell creation/payment/accounting links)

### 7.3 Purchase and inventory flow

In `routes/web.php` and purchase/product controllers:

- Purchase create/update/import routes
- Product management, variation templates, stock history, bulk operations
- Stock operations include opening stock, transfers, adjustments

These flows interconnect with contacts/suppliers, taxes, and reporting.

### 7.4 Payroll and HRM flow (Essentials module)

Under `Modules/Essentials/Http/routes.php` in `hrm` group:

- Attendance, leave, shifts, payroll resources
- `PayrollController` handles payroll listing/creation/payment

Payroll behavior observed in `Modules/Essentials/Http/Controllers/PayrollController.php`:

- Payroll stored as `Transaction` with `type = 'payroll'`
- Allowance/deduction calculation is encoded in payroll records
- Payroll group batching exists
- Posting payment dispatches `TransactionPaymentAdded`

### 7.5 Subscription/billing flow (Superadmin module)

Under `Modules/Superadmin/Http/routes.php`:

- Package management
- Subscription create/confirm/pay flows
- Payment callbacks for multiple gateways
- Multi-business administration operations

### 7.6 CRM flow (CRM module)

Under `Modules/Crm/Routes/web.php`:

- Internal CRM (`/crm/...`): leads, follow-ups, campaigns, reports, proposals, settings
- Contact portal (`/contact/...`): contact dashboard, profile, purchases/sales/ledger, booking/order requests

### 7.7 Repair flow (Repair module)

Under `Modules/Repair/Http/routes.php`:

- Repair tickets
- Status management
- Job sheets (docs/status/parts/print)
- Customer-facing repair status lookup endpoints

### 7.8 Manufacturing flow (Manufacturing module)

Under `Modules/Manufacturing/Http/routes.php`:

- Recipe management
- Production transactions
- Manufacturing report endpoints
- Settings/update product price helper endpoints

## 8) Events, listeners, and queue behavior

`app/Providers/EventServiceProvider.php` wires payment events to account listeners:

- `TransactionPaymentAdded` -> `AddAccountTransaction`
- `TransactionPaymentUpdated` -> `UpdateAccountTransaction`
- `TransactionPaymentDeleted` -> `DeleteAccountTransaction`

Queue config (`config/queue.php`) default is:

- `env('QUEUE_CONNECTION', 'sync')`

Meaning: listeners/jobs run inline by default unless deployment overrides queue connection.

## 9) Strength points

- Strong modular architecture with clear domain expansion model (`Modules/*`)
- Rich business-domain coverage (POS + inventory + payroll + CRM + manufacturing + repair + subscriptions)
- Centralized transaction utility (`TransactionUtil`) reduces duplicated accounting logic across controllers
- Session/business context middleware standardizes multibusiness behavior across routes
- Large integration surface (payments, WooCommerce, messaging, storage, notifications)
- Event-driven hooks for payment/accounting updates

## 10) Weaknesses and technical risks

- Very large controllers/utilities (notably POS/sales areas), making maintenance and testing harder
- Route sprawl in `routes/web.php` (high coupling and discoverability cost)
- Default synchronous queue can hurt response time on heavy operations/events
- Test suite is minimal (currently example tests only), so regression risk is high
- Mixed legacy and modern route/controller declaration styles in some files
- Some duplicated route blocks in `routes/web.php` suggest cleanup opportunity
- Domain logic is partly split between controllers and utils without strict service boundaries

## 11) Developer onboarding checklist

1. Install dependencies:
   - `composer install`
2. Environment:
   - Copy `.env.example` to `.env`
   - Configure DB/cache/queue/mail/payment credentials
3. App key/migrations:
   - `php artisan key:generate`
   - `php artisan migrate --seed`
4. Optional module operations:
   - `php artisan module:list`
5. Serve app:
   - `php artisan serve`
6. If using Passport APIs:
   - `php artisan passport:install`

## 12) How to quickly understand any feature

For a feature (example: payroll, CRM leads, repair job sheet):

1. Start in module/core route file and identify endpoint
2. Open linked controller method
3. Trace calls into `app/Utils/*` and models
4. Check related events/listeners (payment/account side effects are common)
5. Check module `DataController` if UI/menu/scripts are injected dynamically
6. Validate middleware stack and permission checks for access rules

## 13) Suggested documentation expansion path

To make this guide even stronger over time, add:

- ERD/domain model maps (Transaction, Contact, Product, User, module entities)
- Sequence diagrams for:
  - POS checkout
  - Payroll generation + payment posting
  - Subscription purchase callback flow
- Per-module API/routing inventory tables
- A permission matrix (`permission` -> `route` -> `controller action`)
- Production runbook (queue workers, scheduler, backups, monitoring)

## 14) Recommended refactoring priorities

1. Introduce feature services/use-cases for POS and payroll flows to reduce controller size
2. Split `routes/web.php` into domain route files loaded by a route registrar/provider
3. Enforce async queues for non-critical in-request tasks in production
4. Add integration tests for high-risk flows (sell creation, payment posting, payroll payment, subscription callbacks)
5. Standardize route declaration style and remove duplicate definitions

---

This guide is a living document; update it when adding modules, changing middleware/auth patterns, or refactoring transaction/payment flows.
