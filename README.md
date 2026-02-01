# fed-api

Laravel REST API for the `fed-webapp` frontend.

---

## Overview

This project provides the backend API used by the `fed-webapp` client application. It is designed to run locally via Docker (Laravel + PostgreSQL + PgAdmin).

---

## Tech Stack

- Language: PHP 8.3
- Framework: Laravel 12
- Database: PostgreSQL 16
- Tooling / CI: Docker Compose, Composer, Pest (via `php artisan test`)

---

## Getting Started

### Prerequisites

- Docker (and Docker daemon running)
- Docker Compose v2 (`docker compose`)

---

## Installation

```bash
git clone git@github.com:vraiSlophil/fed-api.git
cd fed-api
```

---

## Configuration

This project uses a `.env` file. Start by copying the example file:

```bash
cp .env.example .env
```

Once your `.env` file is in place, install dependencies (populate `vendor/` on the host through the bind mount):

```bash
docker compose run --rm --remove-orphans laravel composer install
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

Notes:

- `APP_KEY` is the Laravel application encryption key (required). If you generate/change it after the `laravel` container is already running, restart the container so it picks up the new value.
- Email verification is sent on registration. If you don’t have a Resend key locally yet, set `MAIL_MAILER=log` in `.env` to avoid failing the `/api/register` flow during setup.
- Frontend URLs for email flows are configured via:
  - `APP_FRONTEND_VERIFY_EMAIL_PATH` (default `/verify-email`)
  - `APP_FRONTEND_INVITATION_PATH` (default `/invite/{invitation}`)
- Email queues can be customized via:
  - `MAIL_QUEUE_VERIFICATION`
  - `MAIL_QUEUE_PASSWORD_RESET`
  - `MAIL_QUEUE_INVITATION`

---

## Usage

```bash
# generate APP_KEY before starting the app (required, otherwise encryption errors will happen)
docker compose run --rm laravel php artisan key:generate

# start containers (API on http://localhost:8000, PgAdmin on http://localhost:8080)
docker compose up -d --build

# run migrations (required for database-backed cache/session/queue)
docker compose exec laravel php artisan migrate

# clear caches (run this after migrations; it can fail before the cache table exists)
docker compose exec laravel php artisan optimize:clear

# start workers (priority order)
docker compose exec laravel php artisan queue:work --queue=emails-verification,emails-password-reset,emails-invitation,default

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

---

## Troubleshooting

- `No application encryption key has been specified.`
    - Ensure `.env` contains a non-empty `APP_KEY=...`, then restart: `docker compose restart laravel`
- Registration fails after creating the user (retry says “email already taken”)
    - This typically means the user was inserted, then an email-related step failed (e.g. missing `RESEND_API_KEY` while `MAIL_MAILER=resend`). Set `MAIL_MAILER=log` or configure `RESEND_API_KEY`, then retry with a new email or delete the created user in DB.

Guidelines:

- Tests are required for behavioral changes
- All tests must pass before opening a PR

---

## Contributing

Contributions are welcome.

Please read the **CONTRIBUTING.md** file before opening an issue or pull request. It contains detailed guidelines on:

- Branch naming
- Commit message conventions
- Pull request process
- Review and merge rules

---

## License

MIT.
