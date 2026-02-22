# Backend Architecture - Modular Monolith (Laravel 12)

## Goals
- Keep controllers thin (HTTP orchestration only).
- Centralize authorization in policies.
- Move business use-cases to `app/Domain/*`.
- Remove inline validation from controllers using `FormRequest` classes.

## Structure
- `app/Domain/*/Actions`: commands / write use-cases.
- `app/Domain/*/Queries`: read use-cases.
- `app/Domain/*/Services`: reusable domain services.
- `app/Policies/*`: authorization rules by aggregate.
- `app/Http/Requests/*`: endpoint validation and normalization.

## Controller Rules
- A controller method should only:
  1. validate request (via `FormRequest`),
  2. authorize,
  3. delegate to `Domain` layer,
  4. return API envelope.

## Status Model
`TaskStatus` is canonicalized to:
- `todo`
- `in_progress`
- `done`

Legacy `doing` is removed from the schema baseline directly in the creation migrations:
- `database/migrations/database_migrations_2025_10_01_000050_create_tasks_table.php`
- `database/migrations/database_migrations_2025_10_01_000070_create_theme_templates_tables.php`

## Routing
Key resources now use explicit binding keys:
- `/themes/{theme:theme_id}`
- `/tasks/{task:task_id}`
- `/playgrounds/{playground:playground_id}`
- `/playgrounds/by-slug/{playground:slug}`
- `/invitations/{invitation:invitation_id}`
- `/admin/users/{user:user_id}`

## Security
Security test routes in `routes/web.php` are restricted to `local` and `testing` environments.
