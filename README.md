# fed-api

Laravel REST API for the `fed-webapp` frontend.

---

## Overview

This project provides the backend API used by the `fed-webapp` client application. It is designed to run locally via Docker (Laravel + PostgreSQL + PgAdmin).

---

## API Routing Structure

API routes are organized by resource under `routes/api/*.php`.

- `routes/api.php` is a thin composition entrypoint that includes resource route files.
- User endpoints are centralized under `/api/users...` (`users.index`, `users.me`, `users.show`, `users.update`, `users.destroy`, etc.).
- Legacy `profile` and `admin/users` route groups are removed.

---

## Tech Stack

- Language: PHP 8.5
- Framework: Laravel 12
- Database: PostgreSQL 18
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

Set host user/group IDs in `.env` so containers run as your local user (avoid file ownership and Git safety issues):

```bash
HOST_UID=$(id -u)
HOST_GID=$(id -g)
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
    - `APP_FRONTEND_INVITATION_PATH` (default `/invite/{invitationId}`)
- Invitation expiry (days): `INVITATION_EXPIRES_DAYS` (default `7`)
- Email queues can be customized via:
    - `MAIL_QUEUE_VERIFICATION`
    - `MAIL_QUEUE_PASSWORD_RESET`
    - `MAIL_QUEUE_INVITATION`

---

## Usage

```bash
# generate APP_KEY before starting the app (required, otherwise encryption errors will happen)
docker compose run --rm laravel php artisan key:generate
```

```bash
# start containers (API on http://localhost:8000, PgAdmin on http://localhost:8080)
docker compose up -d --build
```

> note: migrations and optimize:clear are run automatically by the `bootstrap` service before laravel/queue/scheduler start. re-run manually only if needed:

```bash
# docker compose run --rm laravel php artisan migrate --force
# docker compose run --rm laravel php artisan optimize:clear
```

> workers and scheduler start automatically with docker compose:
>
> - queue-high: emails-verification, emails-password-reset
> - queue-low: emails-invitation, default
> - scheduler: Laravel scheduled tasks (schedule:work)

```bash
# inspect worker/scheduler logs
docker compose logs -f queue-high queue-low scheduler
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
    - Ensure `.env` contains a non-empty `APP_KEY=...`, then restart: `docker compose restart laravel queue-high queue-low scheduler`
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
