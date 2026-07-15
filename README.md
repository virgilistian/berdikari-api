# Berdikari API

Backend for **Berdikari**, a mobile-first ERP for Indonesian UMKM (micro/small businesses). Built with Laravel 13, PHP 8.3, and [nwidart/laravel-modules](https://github.com/nWidart/laravel-modules) as a modular monolith. The pilot deployment is an Angkringan (traditional Indonesian street-food stall).

The API is stateless (token-based auth via Sanctum) so the same endpoints can serve `berdikari-web` today and a future mobile app.

## Modules

| Module | Domain | Status |
|---|---|---|
| `Core` | Tenancy (`Tenantable`), shared interfaces/utilities, in-app notifications | Active |
| `IAM` | Auth, RBAC (`spatie/laravel-permission`), token generation | Active |
| `Catalog` | Products, categories, variants | Active |
| `Inventory` | Stock management, daily stock opname, stock movements, valuation | Active |
| `Sales` | POS checkout, orders, cashier shifts, payments, refunds | Active |
| `Finance` | Cash flow (pemasukan/pengeluaran), summary | Active |
| `HR` | Employee CRUD, attendance, leave requests & approvals | Active |
| `Purchasing` | Purchase orders to suppliers | Planned |
| `CRM` | Customer data, loyalty points | Planned |
| `Production` | Angkringan production recommendation logic | Planned |

Each module lives under `Modules/<Name>/` with its own `routes/api.php`, `app/Http/Controllers`, `app/Services`, `app/Models`, `app/Events`, `app/Providers`, and `database/migrations`. Modules do not query each other's tables directly — cross-module reads go through Service Contracts, side effects through the Event Dispatcher.

## Requirements

Everything runs in Docker. Do not rely on host-installed PHP/Composer versions — `docker-compose.yml` (repo root) is the source of truth for services, ports, and credentials.

Services: PostgreSQL 16, Redis 7, MinIO, Mailpit.

## Getting started

From the repo root:

```bash
docker compose up -d
docker compose exec api composer install
docker compose exec api php artisan migrate --seed
```

The API is served at `http://localhost:8000` (direct) or `http://berdikari.test` via nginx (requires a matching `/etc/hosts` entry).

Run any `artisan`/`composer` command inside the container:

```bash
docker compose exec api php artisan <command>
docker compose exec api composer <command>
```

## Testing

Feature tests live in `tests/Feature/` (subdirs per module: `IAM/`, `Finance/`, `HR/`, `Inventory/`, `Sales/`, `Catalog/`). Run against an in-memory SQLite database — never run the suite without these flags, or `RefreshDatabase` will wipe the dev Postgres database:

```bash
docker exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_HOST= -e DB_URL= berdikari-api-1 php artisan test
```

## Deployment (Fly.io)

Production runs on [Fly.io](https://fly.io) (Docker) with Supabase (Postgres), Upstash (Redis), and Cloudflare R2 (file storage). Config lives in `fly.toml`; app secrets (DB, Redis, R2, `APP_KEY`, etc.) are set via `fly secrets set`, not committed.

```bash
# one-time setup
fly auth login
fly launch --name berdikari-api --region sin --no-deploy
fly secrets set DB_HOST=... DB_PASSWORD=... REDIS_HOST=... AWS_ACCESS_KEY_ID=... # see docs/15-deploy-api.md for the full list

# deploy
fly deploy

# verify
curl https://berdikari-api.fly.dev/api/v1/health
fly logs -a berdikari-api
```

`entrypoint.sh` runs `migrate --force` and `db:seed` automatically on container start. Pushes to `main` touching `berdikari-api/**` also deploy via the `.github/workflows/deploy-api.yml` GitHub Actions workflow (requires the `FLY_API_TOKEN` repo secret).

Rollback: `fly releases -a berdikari-api` to list versions, then `fly deploy --image <image-id>`.

Full step-by-step guide (Bahasa Indonesia), including external service setup: [`docs/15-deploy-api.md`](../docs/15-deploy-api.md).

## Authorization

Access control is database-driven RBAC (deny-by-default, least-privilege), scoped per `business_id`. Permissions follow `resource.action` (e.g. `finance.view`, `pos.open`). New permissions are defined in `Modules/IAM/database/seeders/PermissionSeeder.php` — never hardcoded elsewhere. See `docs/06-api-specification.md` and `docs/10-security.md` for the full contract.

## Docs

Architecture, database design, event design, and deployment specs live in [`../docs`](../docs).
