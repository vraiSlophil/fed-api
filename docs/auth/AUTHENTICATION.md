# Authentication (Stateless API)

This API is stateless and uses Laravel Sanctum personal access tokens. It issues two token types:
- **Access token** (ability: `access`) for protected routes.
- **Refresh token** (ability: `refresh`) for `/auth/refresh` only.

Refresh tokens are rotated on every refresh. Reuse detection is enabled with a configurable grace window.

## Response Envelope
All JSON responses follow the same envelope:

```json
{
  "status": "success|error",
  "message": "Human-readable message",
  "message_code": "machine.readable.code",
  "message_params": {"...": "..."},
  "data": {"...": "..."},
  "errors": {"field": ["..."]},
  "meta": {"request_id": "uuid"}
}
```

Notes:
- `message_code` and `message_params` are omitted for 5xx responses in production.
- `errors` is only included in non-production environments.
- For API exceptions, the response includes `meta.request_id` and the response header `X-Request-Id`.

## Headers
- **Access token**: `Authorization: Bearer <access_token>`
- **Refresh token**: `X-Refresh-Token: <refresh_token>` (preferred) or `Authorization: Bearer <refresh_token>`
- **Request id (optional)**: `X-Request-Id: <uuid>` is echoed back for errors.

## Token Lifecycle
- **Register / Login** return a pair of tokens:
  - `access_token`, `refresh_token`, plus ISO8601 expiration timestamps.
- **Refresh** rotates the refresh token and returns a new pair.
- **Logout** revokes *all* tokens for the current user.
- **Refresh token reuse**:
  - If reuse is detected **within the grace window**, a new token pair is issued.
  - If reuse is detected **outside the grace window**, all tokens for the user are revoked and the request fails.

## Rate Limiting
- `POST /api/auth/login` uses the `auth-login` limiter: **5/min per email + IP** with a custom `auth.throttle` response.
- `POST /api/auth/refresh` uses the `auth-refresh` limiter: **10/min per IP + token hash**.

## Configuration
Env vars (see `.env.example`):
- `ACCESS_TOKEN_TTL_MINUTES` (default: 15)
- `REFRESH_TOKEN_TTL_DAYS` (default: 30)
- `REFRESH_TOKEN_REUSE_GRACE_SECONDS` (default: 30)
- `SANCTUM_EXPIRATION` should be empty for stateless auth (use `expires_at` instead).

## Routes
Base URL: `/api`

### Public (no auth)

#### POST `/auth/register`
Create a new user and issue tokens.

Request body:
```json
{
  "username": "john",
  "email": "john@example.com",
  "password": "secret",
  "password_confirmation": "secret"
}
```

Success (201) - `auth.register.success`:
```json
{
  "status": "success",
  "message": "Account created",
  "message_code": "auth.register.success",
  "data": {
    "user": {"user_id": "...", "email": "..."},
    "access_token": "...",
    "refresh_token": "...",
    "access_expires_at": "2026-01-31T12:00:00Z",
    "refresh_expires_at": "2026-03-01T12:00:00Z"
  }
}
```

Errors:
- `422 validation.invalid`

#### POST `/auth/login`
Authenticate and issue tokens.

Request body:
```json
{
  "email": "john@example.com",
  "password": "secret"
}
```

Success (200) - `auth.login.success`:
```json
{
  "status": "success",
  "message_code": "auth.login.success",
  "data": {
    "user": {"user_id": "...", "email": "..."},
    "access_token": "...",
    "refresh_token": "...",
    "access_expires_at": "...",
    "refresh_expires_at": "..."
  }
}
```

Errors:
- `401 auth.failed`
- `403 auth.blocked`
- `429 auth.throttle` (too many attempts)

#### POST `/auth/refresh`
Rotate refresh token and issue a new pair.

Headers:
- `X-Refresh-Token: <refresh_token>` (preferred)

Success (200) - `auth.refresh.success`:
```json
{
  "status": "success",
  "message_code": "auth.refresh.success",
  "data": {
    "access_token": "...",
    "refresh_token": "...",
    "access_expires_at": "...",
    "refresh_expires_at": "..."
  }
}
```

Errors:
- `401 auth.refresh.missing`
- `401 auth.refresh.invalid`
- `401 auth.refresh.expired`
- `401 auth.refresh.reused` (outside grace window)
- `403 auth.blocked`

#### POST `/auth/forgot-password`
Send a password reset link.

Request body:
```json
{"email": "john@example.com"}
```

Success (200) - `auth.reset_link.sent`

Errors:
- `400 auth.reset_link.failed`
- `422 validation.invalid`

#### POST `/auth/reset-password`
Reset password using token from email.

Request body:
```json
{
  "email": "john@example.com",
  "token": "...",
  "password": "new-secret",
  "password_confirmation": "new-secret"
}
```

Success (200) - `auth.reset.success`

Errors:
- `400 auth.reset.failed`
- `422 validation.invalid`

#### GET `/verify-email/{id}/{hash}`
Verify email via signed URL.

- If `Accept: application/json` is sent, returns JSON responses.
- Otherwise returns an HTML view.

JSON Success:
- `200 auth.verification.success`
- `200 auth.verification.already_verified`

JSON Errors:
- `400 auth.verification.invalid`
- `404 resource.not_found`

### Protected (access token required)

#### POST `/logout`
Revokes **all** tokens for the current user.

Headers:
- `Authorization: Bearer <access_token>`

Success (200) - `auth.logout.success`

Errors:
- `401 auth.failed`
- `403 permission.denied` (refresh token used on access-only route)

#### POST `/email/verification-notification`
Send a verification email.

Headers:
- `Authorization: Bearer <access_token>`

Notes:
- This route is currently behind the `verified` middleware (see `routes/api.php`).
- If already verified: `email.verification.already_verified`
- Else: `email.verification.sent`

#### GET `/ping`
Simple authenticated ping (used for tests).

Headers:
- `Authorization: Bearer <access_token>`

Success (200): `pong`

## Maintenance
- `php artisan auth:prune-revoked-refresh` removes expired rows from `revoked_refresh_tokens`.
- Schedule it with Laravel scheduler or cron as needed.
