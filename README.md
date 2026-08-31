# Mitsubishi CRM

A Laravel-based dealer CRM prototype for Mitsubishi Motors Bangladesh. The application provides role-scoped workspaces for administrators, managers, salespeople, dealers, and customers, including customer ownership, vehicle allocation, Test Drive scheduling, Booking, and customer-decision workflows.

## Local setup

Requirements: PHP 8.2+, Composer, Node.js, and MySQL.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Configure the local database connection in `.env` before running migrations. Development-only seeders are available in `database/seeders/` and should only be run intentionally in a local environment.

## Verification

```bash
php artisan test
php artisan view:cache
php artisan migrate:status
```

## Project structure

- `app/` — application models, controllers, requests, middleware, enums, and services
- `database/` — migrations, factories, and development seeders
- `resources/` — Blade views, JavaScript, and CSS
- `routes/` — shared and role-specific web routes
- `tests/` — feature and unit verification
- `docs/qa/` — historical UI and browser QA evidence; not runtime assets

Local environment files, dependencies, build output, generated browser profiles, and temporary QA responses are excluded through `.gitignore`.
