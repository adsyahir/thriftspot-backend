# ThriftSpot API

Laravel 12 JSON API for ThriftSpot, a directory of thrift shops and secondhand items in Malaysia. People sign up, manage shops and items, and see view analytics. Visitors browse items and shops without an account. There is no checkout, cart or messaging: each shop page shows how to contact the shop.

The Nuxt 4 frontend lives in the sibling `thriftspot-frontend` repo.

## Stack

PHP 8.3, Laravel 12, `tymon/jwt-auth` (access token plus an httpOnly refresh cookie), `spatie/laravel-permission` (roles `admin` and `user`), PHPUnit 11, Postgres (SQLite in tests).

## Setup

### Option 1: `artisan serve`

Needs PHP 8.3 with `pdo_pgsql`, Composer and a Postgres database.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Edit `.env`: set `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` for your Postgres, and set `APP_URL=http://localhost:8000`.

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

The API is at `http://localhost:8000/api`.

### Option 2: Docker

```bash
cp .env.example .env     # set APP_KEY and JWT_SECRET as above, or run the artisan commands inside the container
make up
make fresh               # migrate:fresh --seed
```

Nginx listens on `http://localhost:8080` by default, so set `APP_URL=http://localhost:8080`. Run `docker exec thriftspot-app php artisan storage:link` once. `make help` lists the other targets.

### Image URLs

Item photos are stored on the `public` disk and served from `APP_URL/storage/...`. `APP_URL` must match the origin the browser uses to reach the API, and `storage:link` must have been run, or image URLs will be broken.

### CORS

`CORS_ALLOWED_ORIGINS` must include the frontend origin (default `http://localhost:3000`).

## Tests

```bash
php artisan test
vendor/bin/pint --dirty
```

Tests run on in-memory SQLite with fixed test-only keys from `phpunit.xml`. To run them against Postgres, export `DB_CONNECTION=pgsql` and the `DB_*` variables before `php artisan test`.

## Demo data and login

`php artisan db:seed` always seeds roles and permissions. On a local environment (`APP_ENV=local`) it also runs `DemoSeeder`:

- an admin: `admin@example.com` / `password`
- 20 users, 6 shops, 40 items and 30 days of views

Never use the demo login outside local development.

## API overview

| Area | Endpoints |
| --- | --- |
| Auth | `POST auth/register`, `auth/login`, `auth/refresh`, `auth/logout`, `auth/me` |
| Public | `GET items` (filters: `search`, `category`, `state`, `shop_id`, `min_price`, `max_price`, `sort`), `GET items/{id}`, `POST items/{id}/view`, `GET shops`, `GET shops/{id}` |
| Signed in | `GET my/shops`, `POST/PUT/DELETE shops`, `GET my/items`, `POST/PUT/DELETE items`, `GET my/analytics?days=` |
| Admin | roles, permissions and user-role endpoints (`view-users` to read, `manage-settings` to write) |

Lists return `{data, links, meta}`; `per_page` defaults to 12 and is clamped to 1..50. Validation failures return 422 with `message` and `errors`.

## Production notes

- Set `APP_ENV=production`, `APP_DEBUG=false` and `SESSION_SECURE_COOKIE=true`. The refresh cookie's `secure` flag follows this setting.
- Seed only the roles: `php artisan db:seed --class=RolePermissionSeeder --force`. Do not run `DemoSeeder`, which creates a known admin password.
- Create your first admin by assigning the `admin` role to a registered user (for example with `php artisan tinker`). The last admin cannot be demoted through the API.
- Rate limits (login 5/min, register 10/min, other API traffic 60/min, each counted separately) apply only when `APP_ENV` is `production` or `staging`.
