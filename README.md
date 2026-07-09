# Backend API — Sprint 1 Setup Guide

Laravel 12 backend API with Sanctum token authentication, MySQL, and role-based organisation scoping (`nep_admin`, `nep_coordinator`, `member_org`).

## Requirements

- PHP 8.3+
- Composer
- MySQL 8+ (running locally or via XAMPP/WAMP)
- Node.js (only needed if you touch frontend assets)

---

## 1. Clone and install dependencies

```bash
git clone <repo-url>
cd backend-api
composer install
```

## 2. Environment setup

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your database connection:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pnc-nep-system
DB_USERNAME=root
DB_PASSWORD=
```

Make sure MySQL is running before continuing:

```bash
# XAMPP/WAMP: start MySQL from the control panel
# Native Windows service:
net start mysql
```

## 3. Create the database

Create an empty database matching `DB_DATABASE` in your `.env` (via phpMyAdmin, MySQL Workbench, or CLI):

```sql
CREATE DATABASE `pnc-nep-system`;
```

## 4. Run migrations

```bash
php artisan migrate
```

Confirm all tables were created:

```bash
php artisan migrate:status
```

Every migration should show as `Ran`. If any table conflicts, see Troubleshooting below.

## 5. Seed reference data

```bash
php artisan db:seed
```

This populates provinces, districts, education levels, budget bands, and taxonomy reference data used by programme entries.

## 6. Serve the application

```bash
php artisan serve
```

API will be available at `http://127.0.0.1:8000`.

## 7. Test users (seeded)

The `db:seed` command (step 5) already creates the following test users via `UserSeeder`:

| Email | Password | Role |
|-------|----------|------|
| `admin@example.com` | `password` | `nep_admin` |
| `coordinator@example.com` | `password` | `nep_coordinator` |
| `orgadmin@example.com` | `password` | `member_org` |

## 8. Authentication flow (Postman / API client)

**Login:**
```
POST /api/login
Body: { "email": "admin@example.com", "password": "password" }
```
Returns a token — use it as `Authorization: Bearer <token>` on all subsequent requests.

**Authenticated check:**
```
GET /api/user
Authorization: Bearer <token>
```

**Logout:**
```
POST /api/logout
Authorization: Bearer <token>
```

---

## Key endpoints (Sprint 1)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login` | Authenticate, returns Sanctum token |
| POST | `/api/logout` | Revoke current token |
| GET | `/api/user` | Get authenticated user |
| POST | `/api/programme-entries` | Create a programme entry (scoped to caller's org) |
| PUT | `/api/programme-entries/{id}` | Update a programme entry (own org only, unless nep_admin) |
| GET | `/api/programme-entries/{id}` | Retrieve a single programme entry |
| GET | `/api/organisations/{organisation}/programme-entries` | List all entries for an organisation |

## Roles

| Role | Access |
|------|--------|
| `nep_admin` | Full access across all organisations |
| `nep_coordinator` | Restricted to own organisation |
| `member_org` | Restricted to own organisation |

---

## Troubleshooting

**"Table already exists" on migrate**
Check `php artisan migrate:status` — if a table exists but shows `Pending`, either drop the conflicting table manually or remove its row from the `migrations` table, then re-run `php artisan migrate`.

**"No connection could be made" (SQLSTATE[HY000] [2002])**
MySQL isn't running. Start it via XAMPP/WAMP or `net start mysql`, then retry.

**"Column not found" during seeding**
The live table schema doesn't match the migration — usually caused by editing a migration after it already ran. Drop the affected table and re-run `php artisan migrate`, then re-seed.

**Cannot drop a table due to foreign key constraint**

```sql
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE table_name;
SET FOREIGN_KEY_CHECKS = 1;
```

Or via Tinker:

```php
DB::statement('SET FOREIGN_KEY_CHECKS=0');
Schema::dropIfExists('table_name');
DB::statement('SET FOREIGN_KEY_CHECKS=1');
```

**Passwords not authenticating**
Confirm the User model casts `password` as hashed (Laravel 12 `casts()` method). Never call `Hash::make()` manually when creating users — it will double-hash the password.
