# Authentication (Stateless API)

This API is stateless and uses Laravel Sanctum personal access tokens.
No session/cookie auth is used for API routes.

It issues two token types:

- `access` token for protected endpoints
- `refresh` token for `POST /api/auth/refresh`

## Response envelope

All API responses follow the same envelope:

```json
{
    "status": "success|error",
    "message": "Human readable message",
    "message_code": "machine.readable.code",
    "message_params": { "...": "..." },
    "data": { "...": "..." },
    "errors": { "field": ["..."] },
    "meta": { "request_id": "uuid" }
}
```

Notes:

- `meta.request_id` is present on API exceptions.
- Header `X-Request-Id` is returned on exception responses.
- `errors` is included only in non-production environments.
- `message_code` / `message_params` are omitted on `5xx` in production.

## Auth headers

- Access token:
    - `Authorization: Bearer <access_token>`
- Refresh token (refresh endpoint only):
    - `X-Refresh-Token: <refresh_token>` (preferred)
    - or `Authorization: Bearer <refresh_token>`

## Token lifecycle

- Register/Login return:
    - `access_token`
    - `refresh_token`
    - `access_expires_at`
    - `refresh_expires_at`
- Refresh rotates refresh token (single-use + replace).
- Reuse detection:
    - inside grace window: refresh still accepted
    - outside grace window: all user tokens revoked + `auth.refresh.reused`
- Logout revokes all tokens of authenticated user.

## Storage model

- Sanctum tokens are stored in `personal_access_tokens`.
- Used refresh token hashes are stored in `revoked_refresh_tokens`.

## Rate limiting

- `POST /api/auth/login`
    - limiter: `auth-login`
    - policy: 5/min per `email+ip`
    - throttle response code: `auth.throttle`
- `POST /api/auth/refresh`
    - limiter: `auth-refresh`
    - policy: 10/min per `ip+token_hash`

## Configuration

Main env vars:

- `ACCESS_TOKEN_TTL_MINUTES` (default `15`)
- `REFRESH_TOKEN_TTL_DAYS` (default `30`)
- `REFRESH_TOKEN_REUSE_GRACE_SECONDS` (default `30`)
- `SANCTUM_EXPIRATION` should stay empty for this stateless flow

Email frontend paths used in auth flows:

- `APP_FRONTEND_URL`
- `APP_FRONTEND_VERIFY_EMAIL_PATH`

## Routes

Base path: `/api`

### Public routes

#### `POST /auth/register`

Creates user and returns token pair.

Request:

```json
{
    "username": "john",
    "email": "john@example.com",
    "password": "secret",
    "password_confirmation": "secret"
}
```

Success:

- `201 auth.register.success`

Errors:

- `422 validation.invalid`

#### `POST /auth/login`

Authenticates and returns token pair.

Request:

```json
{
    "email": "john@example.com",
    "password": "secret"
}
```

Success:

- `200 auth.login.success`

Errors:

- `401 auth.failed`
- `403 auth.blocked`
- `429 auth.throttle`

#### `POST /auth/refresh`

Rotates refresh token and returns new token pair.

Headers:

- `X-Refresh-Token: <refresh_token>` (preferred)
- fallback `Authorization: Bearer <refresh_token>`

Success:

- `200 auth.refresh.success`

Errors:

- `401 auth.refresh.missing`
- `401 auth.refresh.invalid`
- `401 auth.refresh.expired`
- `401 auth.refresh.reused`
- `403 auth.blocked`

#### `POST /auth/forgot-password`

Sends password reset notification.

Request:

```json
{
    "email": "john@example.com"
}
```

Success:

- `200 auth.reset_link.sent`

Errors:

- `400 auth.reset_link.failed`
- `500 auth.reset_link.failed` (dispatch error)
- `422 validation.invalid`

#### `POST /auth/reset-password`

Resets password with token from email.

Request:

```json
{
    "email": "john@example.com",
    "token": "...",
    "password": "new-secret",
    "password_confirmation": "new-secret"
}
```

Success:

- `200 auth.reset.success`

Errors:

- `400 auth.reset.failed`
- `422 validation.invalid`

#### `POST /email-verifications`

Verifies email from signed URL params (`signed:relative`).

Expected query params:

- `id`
- `hash`
- `expires`
- `signature`

JSON Success:

- `200 auth.verification.success`
- `200 auth.verification.already_verified`

JSON Errors:

- `400 auth.verification.invalid`
- `403 signature.invalid`
- `404 resource.not_found`
- `422 validation.invalid`

### Protected routes (access token required)

Middleware stack:

- `auth:sanctum`
- `access-token`

#### `POST /auth/logout`

Revokes all current user tokens.

Success:

- `200 auth.logout.success`

Errors:

- `401 auth.failed`
- `403 permission.denied` (refresh token used on access-only route)

#### `GET /auth/ping`

Simple authenticated check.

Success:

- `200` with message `pong`

Errors:

- `401 auth.failed`
- `403 permission.denied`

#### `POST /email-verification-notifications`

Resends verification email for current user.

Success:

- `200 email.verification.sent`
- `200 email.verification.already_verified`

Errors:

- `401 auth.failed`
- `500 email.verification.failed`

## Related docs

Invitation flows moved to dedicated documentation:

- `docs/auth/ISSUE_21_SPA_FLOWS.md`

## Maintenance command

- `php artisan auth:prune-revoked-refresh`
    - removes expired rows from `revoked_refresh_tokens`
    - scheduled daily in `routes/console.php`
