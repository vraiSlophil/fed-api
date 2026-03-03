# API Endpoints Reference

This document is the current API reference for `/api/*` routes.
It is aligned with `routes/api/*.php` and the current controllers/requests.

## Global conventions

- Base path: `/api`
- Response envelope: see [HTTP_RESPONSES.md](./HTTP_RESPONSES.md)
- Pagination contract: see [PAGINATION.md](./PAGINATION.md)
- Common protected middleware stack:
    - `auth:sanctum`
    - `access-token`
- Some domains also require `verified` (email verified account).

## Authentication and verification

### Public

1. `POST /api/auth/register` (`auth.register`)

- Middleware: none
- Body:
    - `username` (required)
    - `email` (required, unique)
    - `password` + `password_confirmation` (required)
- Success:
    - `201 auth.register.success`

2. `POST /api/auth/login` (`auth.login`)

- Middleware: `throttle:auth-login`
- Body:
    - `email` (required)
    - `password` (required)
- Success:
    - `200 auth.login.success`

3. `POST /api/auth/refresh` (`auth.refresh`)

- Middleware: `throttle:auth-refresh`
- Headers:
    - `X-Refresh-Token: <token>` (preferred)
    - fallback: `Authorization: Bearer <token>`
- Success:
    - `200 auth.refresh.success`

4. `POST /api/auth/forgot-password` (`auth.password.email`)

- Middleware: none
- Body:
    - `email` (required)
- Success:
    - `200 auth.reset_link.sent`

5. `POST /api/auth/reset-password` (`auth.password.store`)

- Middleware: none
- Body:
    - `token` (required)
    - `email` (required)
    - `password` + `password_confirmation` (required)
- Success:
    - `200 auth.reset.success`

6. `POST /api/email-verifications` (`verification.verify`)

- Middleware: `signed:relative`, `throttle:6,1`
- Query:
    - `id` (required, uuid)
    - `hash` (required)
- Success:
    - `200 auth.verification.success`
    - `200 auth.verification.already_verified`

### Protected (`auth:sanctum`, `access-token`)

1. `POST /api/auth/logout` (`auth.logout`)

- Success:
    - `200 auth.logout.success`

2. `GET /api/auth/ping` (`auth.ping`)

- Success:
    - `200` (message `pong`)

3. `POST /api/email-verification-notifications` (`verification.send`)

- Middleware: `throttle:6,1`
- Success:
    - `200 email.verification.sent`
    - `200 email.verification.already_verified`

## Invitations

### Protected (`auth:sanctum`, `access-token`, `verified`)

1. `POST /api/invitations` (`invitations.store`)

- Body:
    - `invitee_user_id` (required, uuid)
    - `invitable_type` (required, `theme` or `App\Models\Themes\Theme`)
    - `invitable_id` (required, uuid)
    - `payload.permissions` object with required booleans:
        - `can_view`, `can_update_theme`, `can_add_task`, `can_edit_task`, `can_delete_task`, `can_validate_task`
    - `expires_at` (optional)
- Invariant:
    - action flags require `can_view=true`
- Success:
    - `201 theme.invite.sent`

2. `GET /api/invitations` (`invitations.index`)

- Query:
    - `page`, `per_page` (offset pagination)
    - `status` (`pending|accepted|declined|expired|canceled`)
    - `scope` (`inbox|outbox|all`)
- Success:
    - `200 invitation.list.success`

3. `GET /api/invitations/{invitation}` (`invitations.show`)

- Path:
    - `{invitation}` uuid
- Success:
    - `200 invitation.show.success`

4. `DELETE /api/invitations/{invitation}` (`invitations.destroy`)

- Path:
    - `{invitation}` uuid
- Rule:
    - hard delete only when status is `declined` or `canceled`
- Success:
    - `204 No Content`

### Dual mode

1. `PATCH /api/invitations/{invitation}` (`invitations.respond`)

- Middleware: `throttle:6,1`
- Path:
    - `{invitation}` uuid
- Input (query/body merged by request):
    - `status` (required: `accepted|declined|canceled`)
    - `target_playground_id` (optional, accepted only)
- Mode A (authenticated):
    - requires access token ability
    - `accepted|declined`: invitee
    - `canceled`: inviter or admin
- Mode B (unauthenticated):
    - requires valid signed query
    - only `accepted|declined`
- Success:
    - `200 theme.invitation.accepted`
    - `200 theme.invitation.declined`
    - `200 theme.invitation.canceled`

## Playgrounds (CRUD only)

Protected middleware: `auth:sanctum`, `access-token`, `verified`

1. `GET /api/playgrounds` (`playgrounds.index`)

- Query:
    - optional `slug`
- Behavior:
    - without `slug`: list current user playgrounds (`playground.list.success`)
    - with `slug`: return one owned playground (`playground.show.success`)

2. `POST /api/playgrounds` (`playgrounds.store`)

- Body:
    - `name` required
    - `slug` required
    - optional: `icon`, `color`, `background_color`, `style`, `is_default`
- Success:
    - `201 playground.create.success`

3. `GET /api/playgrounds/{playground}` (`playgrounds.show`)

- Path:
    - `{playground}` uuid
- Middleware:
    - `can:view,playground`
- Success:
    - `200 playground.show.success`

4. `PATCH /api/playgrounds/{playground}` (`playgrounds.update`)

- Path:
    - `{playground}` uuid
- Middleware:
    - `can:update,playground`
- Body:
    - optional: `name`, `slug`, `icon`, `color`, `background_color`, `style`, `is_default`
- Success:
    - `200 playground.update.success`

5. `DELETE /api/playgrounds/{playground}` (`playgrounds.destroy`)

- Path:
    - `{playground}` uuid
- Middleware:
    - `can:delete,playground`
- Rule:
    - default playground cannot be deleted
- Success:
    - `204 No Content`

## Themes

Protected middleware: `auth:sanctum`, `access-token`, `verified`

1. `GET /api/themes` (`themes.index`)

- Query:
    - optional `playground_id` (uuid)
- Success:
    - `200 theme.list.success`

2. `POST /api/themes` (`themes.store`)

- Body:
    - `title` required
    - `color` required
    - `playground_id` required (owned playground)
- Success:
    - `201 theme.create.success`

3. `GET /api/themes/{theme}` (`themes.show`)

- Middleware:
    - `can:view,theme`
- Success:
    - `200 theme.show.success`

4. `PATCH /api/themes/{theme}` (`themes.update`)

- Middleware:
    - `can:update,theme`
- Body:
    - optional `title`, `color`, `playground_id`
- Rule:
    - `playground_id` change is owner-only and owner-scoped
- Success:
    - `200 theme.update.success`

5. `DELETE /api/themes/{theme}` (`themes.destroy`)

- Middleware:
    - `can:delete,theme`
- Success:
    - `204 No Content`

6. `GET /api/themes/{theme}/stats` (`stats.theme`)

- Middleware:
    - `can:view,theme`
- Success:
    - `200 stats.theme.success`

## Theme members

Protected middleware: `auth:sanctum`, `access-token`, `verified`

1. `GET /api/themes/{theme}/members` (`theme.members.list`)

- Middleware:
    - `can:manageMembers,theme`
- Success:
    - `200 theme.members.list.success`

2. `POST /api/themes/{theme}/members`

- Behavior:
    - intentionally not exposed, returns not found

3. `PATCH /api/themes/{theme}/members/{userId}` (`theme.members.update`)

- Path:
    - `{userId}` uuid
- Body (partial):
    - permissions: `can_view`, `can_update_theme`, `can_add_task`, `can_edit_task`, `can_delete_task`, `can_validate_task`
    - `status` (`active|revoked`)
    - `target_playground_id`
- Rules:
    - moving `target_playground_id` is self-only
    - cannot mix move with permission/status patch
    - invariant: action flags require `can_view=true`
- Success:
    - `200 theme.member.permissions.updated`
    - `200 theme.move.success` (self move flow)

4. `DELETE /api/themes/{theme}/members/{userId}` (`theme.members.remove`)

- Behavior:
    - self target: leave theme
    - other target: owner removes member
- Success:
    - `200 theme.member.left`
    - `200 theme.member.removed`

## Tasks

Protected middleware: `auth:sanctum`, `access-token`, `verified`

1. `GET /api/tasks` (`tasks.index`)

- Query:
    - pagination: `page`, `per_page`
    - filters: `theme_id`, `status`, `statuses`, `archived`, `validated`, `search`, `sort`
- Success:
    - `200 task.list`

2. `POST /api/tasks` (`tasks.store`)

- Body:
    - `theme_id` required
    - `title` required
    - `status` optional (`todo|in_progress|done`)
- Success:
    - `201 task.created`

3. `GET /api/tasks/{task}` (`tasks.show`)

- Middleware:
    - `can:view,task`
- Success:
    - `200 task.show`

4. `PATCH /api/tasks/{task}` (`tasks.update`)

- Middleware:
    - `can:update,task`
- Body:
    - optional `title`, `status`, `archived_at`
- Success:
    - `200 task.updated`

5. `DELETE /api/tasks/{task}` (`tasks.destroy`)

- Middleware:
    - `can:delete,task`
- Success:
    - `204 No Content`

## Users

Protected middleware: `auth:sanctum`, `access-token`, `verified`

1. `GET /api/users` (`users.index`)

- Dual behavior:
    - admin listing mode
    - non-admin theme member search mode (`theme_id + search` and `manageMembers`)
- Query:
    - pagination: `page`, `per_page`
    - filters/sort: `search`, `theme_id`, `role`, `roles`, `status`, `sort_by`, `sort`
- Success:
    - `200` (admin list)
    - `200 theme.users.search.success` (theme search mode)

2. `POST /api/users` (`users.store`)

- Middleware:
    - `admin`
- Body:
    - admin user creation payload (`username`, `email`, `password`, `role_power`, optional profile/avatar)
- Success:
    - `201 user.create.success`

3. `GET /api/users/me` (`users.me`)

- Success:
    - `200 auth.user.fetched`

4. `GET /api/users/{user}` (`users.show`)

- Middleware:
    - `admin`, `can:view,user`
- Success:
    - `200 user.show.success`

5. `PATCH /api/users/{user}` (`users.update`)

- Middleware:
    - `can:update,user`
- Body:
    - unified partial update (profile, password, avatar)
    - admin-only keys: `role_power`, `blocked_at`
- Success:
    - `200 user.update.success`
    - `200 user.update.email` (email changed flow)

6. `DELETE /api/users/{user}` (`users.destroy`)

- Middleware:
    - `admin`, `can:delete,user`
- Success:
    - `200 user.delete.success`

## Metrics

Protected middleware: `auth:sanctum`, `access-token`, `verified`

1. `GET /api/stats` (`stats.global`)

- Success:
    - `200 stats.global.success`

2. `GET /api/user/stats` (`user.metrics`)

- Query:
    - optional `period`: `7_days|30_days|3_months|6_months|12_months|all_time`
- Success:
    - `200 user.metrics.retrieved`

## Media

1. `GET /api/media/{path}` (`media.show`)

- Middleware: none
- Behavior:
    - streams files from public storage with path traversal protection
    - returns `404` if missing/invalid path

## Resources intentionally not exposed (current scope)

- `task_dependencies`
- `reminders`
- `theme_templates`
- `plans` / `subscriptions` (`user_subscriptions`)
- `audit_logs`
