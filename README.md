# fed-api

Laravel REST API for the `fed-webapp` frontend.

---

## Overview

This project provides the backend API used by the `fed-webapp` client application. It is designed to run locally via Docker (Laravel + PostgreSQL + PgAdmin).

---

## Tech Stack

* Language: PHP 8.3
* Framework: Laravel 12
* Database: PostgreSQL 16
* Tooling / CI: Docker Compose, Composer, Pest (via `php artisan test`)

---

## Getting Started

### Prerequisites

- Docker (and Docker daemon running)
- Docker Compose v2 (`docker compose`)

---

## Installation

```bash
git clone <repository-url>
cd fed-api
```

```bash
# install dependencies (populate vendor/ on the host through the bind mount)
docker compose run --rm laravel composer install
```

---

## Configuration

This project uses a `.env` file. Start by copying the example file:

```bash
cp .env.example .env
```

Required variables (minimum):

```env
APP_URL=http://localhost:8000
APP_FRONTEND_URL=http://localhost:3000
APP_KEY= # must be generated (see Usage)

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=fed_db
DB_USERNAME=fed_user
DB_PASSWORD=fed_password

RESEND_API_KEY=YOUR_RESEND_API_KEY
```

---

## Usage

```bash
# optional cleanup if you have leftover services from older setups
docker compose down --remove-orphans

# generate APP_KEY before starting the app (required, otherwise encryption errors will happen)
docker compose run --rm laravel php artisan key:generate

# start containers (API on http://localhost:8000, PgAdmin on http://localhost:8080)
docker compose up -d --build

# run migrations (required for database-backed cache/session/queue)
docker compose exec laravel php artisan migrate

# clear caches (run this after migrations; it can fail before the cache table exists)
docker compose exec laravel php artisan optimize:clear
```

Optional seeders:

```bash
docker compose exec laravel php artisan db:seed
```

---

## Testing

```bash
docker compose exec laravel php artisan test
```

Guidelines:

* Tests are required for behavioral changes
* All tests must pass before opening a PR

---

## Contributing

Contributions are welcome.

Please read the **CONTRIBUTING.md** file before opening an issue or pull request. It contains detailed guidelines on:

* Branch naming
* Commit message conventions
* Pull request process
* Review and merge rules

---

## License

MIT.
