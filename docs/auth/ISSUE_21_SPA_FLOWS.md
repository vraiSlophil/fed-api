# Issue 21 - SPA-friendly email verification and invitations

This document describes the current implementation used by the frontend.
It reflects the code in `routes/api.php` and current controllers/services.

## 1) Scope

Goal of this implementation:
- keep flows stateless (Bearer token + signed URLs, no session/cookies)
- expose JSON-only endpoints under `/api/*`
- keep invitation routes REST-like (`PATCH /api/invitations/{invitation}`)
- make frontend responsible for reading query params from email links, then calling API

## 2) Common response format

Every API endpoint returns the common envelope:

```json
{
  "status": "success",
  "message": "Ok",
  "message_code": "common.ok",
  "message_params": {},
  "data": {},
  "meta": {
    "request_id": "uuid"
  }
}
```

Error example:

```json
{
  "status": "error",
  "message": "Forbidden",
  "message_code": "signature.invalid",
  "meta": {
    "request_id": "uuid"
  }
}
```

Notes:
- `X-Request-Id` is also returned in response headers.
- `errors` is present only in non-production environments.
- `message_code` / `message_params` are omitted on `5xx` in production.

## 3) Email verification flow

### 3.1 Verify email from signed link

Endpoint:
- `POST /api/email-verifications`
- middleware: `signed:relative`, `throttle:6,1`
- auth: not required

Expected query params:
- `id`
- `hash`
- `expires`
- `signature`

The frontend must pass the exact signed query params it received in the email URL.

Example request:

```http
POST /api/email-verifications?id=<user_id>&hash=<sha1_email>&expires=<timestamp>&signature=<sig>
Content-Type: application/json
```

Success:
- `200 auth.verification.success`
- `200 auth.verification.already_verified`

Errors:
- `400 auth.verification.invalid` (hash mismatch)
- `403 signature.invalid` (missing/invalid/expired signature)
- `404 resource.not_found` (unknown user)
- `422 validation.invalid` (missing required params after signature check)

### 3.2 Resend verification email

Endpoint:
- `POST /api/email-verification-notifications`
- middleware: `auth:sanctum`, `access-token`, `throttle:6,1`

Headers:
- `Authorization: Bearer <access_token>`

Example request:

```http
POST /api/email-verification-notifications
Authorization: Bearer <access_token>
```

Success:
- `200 email.verification.sent`
- `200 email.verification.already_verified`

Errors:
- `401 auth.failed`
- `500 email.verification.failed` (dispatch failure)

## 4) Invitation flow

### 4.1 Create invitation (theme owner)

Endpoint:
- `POST /api/themes/{id}/members`
- middleware: `auth:sanctum`, `access-token`, `verified`

Body:

```json
{
  "user_id": "uuid",
  "can_view": true,
  "can_update_theme": false,
  "can_add_task": false,
  "can_edit_task": false,
  "can_delete_task": false,
  "can_validate_task": false
}
```

Success:
- `201 theme.invite.sent`

Example success payload:

```json
{
  "status": "success",
  "message_code": "theme.invite.sent",
  "data": {
    "invitation": {
      "invitation_id": "uuid",
      "user_id": "uuid",
      "username": "john",
      "email": "john@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "status": "pending",
      "created_at": "2026-02-08T17:00:00.000000Z"
    }
  }
}
```

Main errors:
- `401 auth.failed`
- `403 permission.denied` (non-owner or owner inviting self)
- `404 resource.not_found` (theme/user not found)
- `409 theme.member.already_exists`
- `409 theme.invitation.already_exists` (pending invitation already exists)
- `422 validation.invalid`

Re-invite rule:
- allowed after `declined` or `expired`
- blocked only if another `pending` exists for same invitee + invitable

### 4.2 List invitations for invitation center (paginated)

Endpoint:
- `GET /api/invitations`
- middleware: `auth:sanctum`, `access-token`, `verified`

Query params:
- `page` (default `1`)
- `per_page` (default `15`, max `100`)
- `status` (`pending|accepted|declined|expired`, default `pending`)

Example request:

```http
GET /api/invitations?page=1&per_page=15&status=pending
Authorization: Bearer <access_token>
```

Success:
- `200 invitation.list.success`

Example success payload:

```json
{
  "status": "success",
  "message_code": "invitation.list.success",
  "data": [
    {
      "invitation_id": "uuid",
      "status": "pending",
      "created_at": "2026-02-22T10:00:00.000000Z",
      "expires_at": "2026-03-01T10:00:00.000000Z",
      "inviter": {
        "user_id": "uuid",
        "username": "owner",
        "email": "owner@example.com",
        "first_name": "Owner",
        "last_name": "User",
        "avatar_path": null
      },
      "invitable": {
        "type": "theme",
        "id": "uuid",
        "title": "Theme title",
        "color": "#0099ff"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 24,
    "last_page": 2,
    "from": 1,
    "to": 15,
    "has_next": true
  }
}
```

Errors:
- `401 auth.failed`
- `422 validation.invalid` (invalid `page`, `per_page`, or `status`)

Out-of-bounds page behavior:
- returns `200` with `data = []`
- keeps `meta.current_page` equal to requested page
- sets `meta.has_next = false`

### 4.3 Accept/decline invitation from signed link

Endpoint:
- `PATCH /api/invitations/{invitation}`
- middleware: `auth:sanctum`, `access-token`, `signed:relative`, `throttle:6,1`

Signed query params:
- `status=accepted|declined`
- `expires`
- `signature`

Optional body:

```json
{
  "target_playground_id": "uuid"
}
```

Request examples:

```http
PATCH /api/invitations/0f9a...a12?status=accepted&expires=1730000000&signature=...
Authorization: Bearer <access_token>
Content-Type: application/json

{"target_playground_id":"f7f7...b01"}
```

```http
PATCH /api/invitations/0f9a...a12?status=declined&expires=1730000000&signature=...
Authorization: Bearer <access_token>
```

Success:
- `200 theme.invitation.accepted`
- `200 theme.invitation.declined`

Accepted response contains created permission object:

```json
{
  "status": "success",
  "message_code": "theme.invitation.accepted",
  "data": {
    "permission": {
      "permission_id": "uuid",
      "theme_id": "uuid",
      "user_id": "uuid",
      "status": "active",
      "target_playground_id": "uuid|null"
    }
  }
}
```

Errors:
- `401 auth.failed`
- `403 signature.invalid`
- `403 permission.denied` (authenticated user is not invitee)
- `404 resource.not_found` (invitation not found, or invalid `target_playground_id` ownership check in accept path)
- `409 invitation.already_responded`
- `410 invitation.expired`
- `422 validation.invalid` (missing/invalid `status`, invalid body)
- `400 invitation.invalid` (unsupported invitable type)

Important front rule:
- do not mutate signed query params (`status`, `expires`, `signature`), otherwise signature fails.

Important compatibility note:
- issue #43 introduces pagination standardization and `GET /api/invitations`.
- it does **not** change the signed response endpoint contract (`PATCH /api/invitations/{invitationId}`).

## 5) Email links and frontend paths

Frontend URLs used in emails are configured by env:
- `APP_FRONTEND_URL`
- `APP_FRONTEND_VERIFY_EMAIL_PATH` (default `/verify-email`)
- `APP_FRONTEND_INVITATION_PATH` (default `/invite/{invitationId}`)

Backend signs API routes relatively (`signed:relative`) and forwards signed query params to frontend links.
The frontend page should read query params and call API endpoints above.

## 6) Async processing (queues + scheduler)

Invitation and auth emails are queued on dedicated queues:
- verification: `MAIL_QUEUE_VERIFICATION` (default `emails-verification`)
- password reset: `MAIL_QUEUE_PASSWORD_RESET` (default `emails-password-reset`)
- invitation: `MAIL_QUEUE_INVITATION` (default `emails-invitation`)

Invitation expiration:
- command: `php artisan invitations:expire`
- schedule: daily (in `routes/console.php`)
- behavior: move `pending` invitations past `expires_at` to `expired`, then queue expiration email to inviter

## 7) Front integration checklist

1. Parse query params from `/verify-email` and the configured invitation page (`/invite/{invitationId}` by default).
2. Use `GET /api/invitations` (paginated) to populate invitation center.
3. Call API endpoints with the exact signed query params.
4. For invitation response, always send access token and `PATCH` with `status` in query.
5. Handle business codes (`message_code`) in UI, not only HTTP status.
6. Handle `410 invitation.expired` as terminal state (show expired UI).
