# Mitsubishi CRM

Laravel 12 CRM with Admin, Dealer (also called Manager), Salesman and Customer logins, plus Vite frontend assets.

## Requirements

- PHP 8.2+ and Composer 2; PDO MySQL for the demo database and PDO SQLite for isolated tests.
- Node.js 20.19+ on Node 20, or 22.12+; npm.
- Local MySQL. This Windows workstation uses WAMP PHP 8.3.14 and MySQL 9.1.0.

Install dependencies only when missing: `composer install` and `npm install`. Copy `.env.example` to `.env` only if `.env` is absent, and generate an application key only for a new installation without a key. Preserve existing secrets and keys. Do not use `composer run setup` on an existing database: it automatically runs migrations.

## Client demo setup (Windows PowerShell)

The dedicated database is **mitsubishi_crm_demo**. Demo setup accepts only `APP_ENV=local`, MySQL on `localhost`, `127.0.0.1` or `::1`, and that exact database name. It also accepts testing SQLite `:memory:` for automated tests. Production, remote hosts, split read/write connections and other database names are rejected before migration or seeding.

On a client test server, run the command on that server against its local demo MySQL instance. This is a demo configuration, not a production deployment. Keep the server restricted to the client test audience, use `APP_DEBUG=false`, and use a non-delivering mail driver such as `log`. Demo seeders send no email or external notifications. Configure your own database credentials privately; do not copy credentials into this README.

Create an empty database named `mitsubishi_crm_demo` in your local MySQL administration tool if it does not exist. Do not rename, clear or repurpose an existing CRM database. Inspect an existing demo database before setup.

Use these process-only overrides to keep `.env` and the original database unchanged:

```powershell
$env:APP_ENV = 'local'
$env:APP_DEBUG = 'false'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_DATABASE = 'mitsubishi_crm_demo'
$env:DB_URL = ''
$env:MAIL_MAILER = 'log'
php artisan demo:prepare
npm run build
php artisan serve --host=127.0.0.1 --port=8001 --no-reload
```

Use the WAMP PHP executable (`F:\wamp\bin\php\php8.3.14\php.exe`) if `php` is not on PATH. Open **http://127.0.0.1:8001/login**. Port 8001 avoids an existing application server on 8000. Keep the environment overrides in the same terminal when starting the server; a different terminal or the existing port-8000 process may still use the original database.

If configuration is cached, clear it on this dedicated demo deployment (`php artisan config:clear`) before applying environment overrides. On the client server, configure the equivalent `.env` settings, including the actual test URL, rather than depending on a terminal session. Do not replace `APP_KEY` on an existing deployment. For active frontend work, run `npm run dev` in another terminal; Vite's port 5173 is the asset server, not the CRM login URL.

`demo:prepare` validates the target, runs ordinary pending migrations, calls `ClientDemoSeeder`, and reports records created and total counts. The database must already exist. Migrations and fixture insertion are separate steps: a fixture conflict rolls back all new fixture records, but does not undo applied migrations.

Client setup is explicit. Ordinary `php artisan db:seed` keeps its original behavior: in the local environment it runs `DevelopmentUserSeeder` for the four original authentication accounts, not the client demo orchestrator. Existing standalone seeders retain their original prerequisites, dates and update behavior for compatibility. Do not use them to refresh a client presentation. The create-only client fixtures live under `database/seeders/ClientDemo/` and use the guarded `DevelopmentDemoFixtures` helper; their target checks also apply when invoked individually.

To seed an already migrated dedicated demo database:

```powershell
php artisan db:seed --class=ClientDemoSeeder
```

## Demo accounts

All accounts below use **Password123! on creation**. Existing passwords are preserved. These are fictional development credentials, never production credentials. All roles authenticate by email through `/login`; customers can also use `/customer/login`. Managers use the `dealer` role.

| Role / workspace | Email |
| --- | --- |
| Admin | `admin@mitsubishi.test` |
| Uttara dealer | `dealer@mitsubishi.test` |
| Uttara salesman | `salesman@mitsubishi.test` |
| Arif, original test-drive/booking scenarios | `demo.salesman.testdrive@example.test` |
| Dhanmondi dealer | `dealer.dhanmondi@mitsubishi.test` |
| Dhanmondi salesman | `salesman.dhanmondi@mitsubishi.test` |
| Customer portal | `customer@mitsubishi.test` |
| Additional customer portal accounts | `customer.asha@example.test`, `customer.rafi@example.test`, `customer.tania@example.test` |
| Other dealer accounts | `dealer.{uttara,dhanmondi,gulshan}.{01,02}@example.test` |
| Other salesman accounts | `salesman.{uttara,dhanmondi,gulshan}.{01,02,03}@example.test` |

For patterned emails, choose one location and one number; for example `dealer.gulshan.01@example.test`. The two `dealer.unassigned@example.test` and `salesman.unassigned@example.test` accounts are intentional negative-test fixtures and have no showroom access.

The 27 users comprise 1 admin, 9 dealers (including the unassigned fixture), 13 salesmen (including the unassigned fixture), and 4 customer logins. CRM contact emails such as `demo.rahim.testdrive@example.test` and `demo.client.01-01@example.test` are contact records, not additional login accounts.

## Dataset and repeatability

An empty demo database receives:

| Record | Count |
| --- | ---: |
| Dealers/showrooms | 5 |
| Login users | 27 |
| Vehicle models | 8 |
| Dealer/model allocations | 16 |
| CRM customers | 100 |
| Test drives | 100 |
| Bookings | 37: 13 pending, 12 confirmed, 12 cancelled |
| Slots | 392 in the verified 14 September 2026 setup; varies with dates/holidays and occupied windows |

The dataset combines the existing `DevelopmentDemoSeeder` fixtures with eight scenarios for each of 12 assigned salesmen: pending booking, confirmed booking, cancelled booking, completed drive ready to book, follow-up, lost enquiry, upcoming scheduled drive, and upcoming confirmed drive. The initial dataset has 75 completed and 25 upcoming drives. Cancelled bookings have no deposit; pending and confirmed bookings have proportional fictional deposits within vehicle prices. Existing model events calculate paid and due amounts. No actual payment or refund is performed.

Customer names and contact details are fictional, with reserved test email domains and non-deliverable demo phone strings. Historical Lancer Evolution is a catalog example only, not an allocated current vehicle.

Fixture identities use explicit emails, showroom codes, vehicle model/variant/year and scenario markers. Related IDs are resolved from the database. The seeder never updates or deletes existing records. Conflicting identities or fields abort the fixture transaction; existing passwords are neither compared nor reset. Run one setup process at a time.

Scenario allocations come from the declared demo showroom/model plan in a fixed order. Adding unrelated vehicles or allocations does not change an existing scenario's vehicle selection.

Slots use the configured business calendar, exclude Fridays and configured holidays, and avoid occupied allocation slots and simultaneous appointments for a salesman. Repeated runs preserve original appointment dates and data, even after those dates become past. Changing the business calendar may cause a clear conflict rather than silently moving appointments. Do not delete or reset records to resolve a conflict; review it deliberately. For a later presentation, plan a separate approved dataset refresh.

## Presentation workflow

1. Sign in as Admin: inspect the database-backed showrooms, customers and inventory/allocations.
2. Sign in as a dealer: inspect the assigned showroom's customers, sales team and inventory.
3. Sign in as a salesman: use customers, test drives, calendar and follow-ups. A completed drive without a decision is ready for a booking; choose a future free slot to schedule another drive.
4. Create a pending booking from a ready drive. The salesman booking list and existing dashboard counts reflect the database change.
5. Sign in as another showroom or salesman to demonstrate ownership boundaries.

The existing CRM layouts, charts, quick actions, routes and business workflows are unchanged. Seeded records populate the pages that already query the database. Seeders cannot make hardcoded dashboard statistics or reports live. There is no persisted notification/read-state table, delivery channel, or audit history in the existing schema; notification/activity examples remain part of the original UI.

## Remaining prototype boundaries

- Customer authentication works, but customer login users have no schema relationship to CRM contact transactions. Customer dashboard, vehicles, bookings, payments, garage and test-drive screens remain design previews; the additional customer accounts do not imply personal transaction histories.
- Conversations, chassis, deliveries, integrations, settings and dealer profile remain sample/placeholder screens.
- Admin salesman directory/profile and `/dealer/team/{salesman}` remain legacy prototype views; use the database-backed dealer `/salesmen` directory and profiles for staff demonstrations.
- Admin/dealer dashboards, charts, reports, booking/detail pages and calendars retain their original sample content and layouts. The salesman dashboard already reads real counts; its quick actions are preserved. The interactive slot calendar and booking actions remain in the salesman workspace.
- Booking status examples are seeded; the existing application has no complete confirmation, cancellation or refund management workflow.
- Navbar quick-search suggestions and some decorative content remain static.

## Verification

```powershell
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = ''
php artisan test
php artisan test --filter='DevelopmentDemoSeederTest|ClientDemoTest'
npm run build
```

Run verification in a separate terminal from the demo server. The dedicated demo tests check the connection is testing SQLite `:memory:` before applying ordinary migrations. Coverage includes fixture counts, relationships, valid windows and holidays, payment calculations, repeated setup, unrelated allocations, password preservation, rollback on conflicts, local-target guards, normal default seeding, all assigned account logins, original staff pages, cross-showroom isolation, booking a completed drive, and scheduling a free slot.

The complete existing suite uses Laravel's `RefreshDatabase` for its disposable in-memory database. Its resets must never target MySQL or a persistent database. The original standalone-seeder tests remain unchanged and are included in the full suite. `docs/qa/` contains historical UI captures, not database backups. Local environment files, dependencies and build output are excluded by `.gitignore`.
