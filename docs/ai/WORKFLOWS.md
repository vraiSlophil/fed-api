# Common Workflows

## Authenticate and bootstrap a client session

1. Call `POST /api/auth/register` or `POST /api/auth/login`.
2. Store the returned `access_token` and `refresh_token`.
3. Use the access token as `Authorization: Bearer <access_token>`.
4. Call `GET /api/users/me` to fetch the authenticated user.
5. If protected routes fail because the user is unverified, trigger the email verification flow.

Notes:

- `POST /api/auth/refresh` accepts the refresh token through `X-Refresh-Token` or Bearer fallback.
- Refresh rotates the refresh token. Clients must replace the old token pair after a successful refresh.

## Create a playground, then a theme

1. Call `POST /api/playgrounds`.
2. Read the returned `playground_id`.
3. Call `POST /api/themes` with that `playground_id`.
4. Read the returned `theme_id`.

Use this flow when starting from an empty account.

## Create and manage tasks

1. Resolve a target theme visible to the user.
2. Call `POST /api/tasks` with `theme_id`.
3. List tasks with `GET /api/tasks`.
4. Read one task with `GET /api/tasks/{task_id}`.
5. Update task fields with `PATCH /api/tasks/{task_id}`.
6. Delete tasks with `DELETE /api/tasks/{task_id}` when hard deletion is intended.

Notes:

- Task visibility is policy-based.
- Task list responses include pagination metadata in `meta`.
- A task can only be created if the user has permission to add tasks in the target theme.

## Share a theme with another user

1. Resolve the target `theme_id`.
2. Call `POST /api/invitations` with:
   - `invitable_type`
   - `invitable_id`
   - recipient information expected by the invitation endpoint
3. As the inviter, track sent invitations with `GET /api/invitations?scope=outbox&status=pending`.
4. Inspect one invitation with `GET /api/invitations/{invitation}` when you need the full payload.
5. Deliver the invitation email links as frontend deep links only.

Notes:

- Invitation creation is limited to supported invitable types. The current controller supports theme invitations.
- Theme sharing is not modeled as direct user assignment on the theme endpoints. It goes through invitations and member permissions.
- Invitation emails do not call a public mutation API. They open the frontend invitation screen and let the user answer after authentication.
- The generated frontend links can carry `intent=accept` or `intent=decline` as a UI hint only. That query parameter is not part of the API contract and must never be treated as an authoritative status change.

## Open an invitation from the email link

1. The invitee clicks the frontend link generated from `APP_FRONTEND_URL` and `APP_FRONTEND_INVITATION_PATH`.
2. The frontend keeps the invitation identifier from the path and the optional `intent` query as local UI state.
3. If no authenticated API session exists, redirect the user to login or registration, then return to the same frontend invitation URL.
4. If protected API calls fail because the account is not verified, complete the email-verification flow before continuing.
5. Load `GET /api/invitations/{invitation}` to fetch the invitation details for the authenticated participant.
6. Optionally load `GET /api/invitations?scope=inbox&status=pending` to render the full invitation inbox around the focused invitation.
7. If the invitee may choose a non-default destination playground before accepting, load `GET /api/playgrounds` and let the invitee pick one of their own playgrounds.
8. Render the allowed actions from the authenticated user role and the invitation state:
   - invitee: `accepted` or `declined`
   - inviter or admin: `canceled`
9. Keep the invitation screen read-only when the invitation is already terminal (`accepted`, `declined`, `expired`, `canceled`) or inaccessible.

Notes:

- Clicking the email link never changes backend state by itself.
- `intent=accept|decline` is a frontend preselection hint only. The backend only trusts the authenticated `PATCH` body.
- `GET /api/invitations/{invitation}` is subject to the normal invitation policy:
  - invitee, inviter, or admin can read it
  - unrelated authenticated users receive `403 permission.denied`
  - unauthenticated users receive `401 auth.failed`
- The API still enforces invitation expiration and valid transitions even if the frontend reached the invitation page from a stale email.

## Respond to an invitation from the authenticated inbox

### Invitee accepts or declines

1. Authenticate with a normal access token.
2. Call `PATCH /api/invitations/{invitation}`.
3. Send the desired business state in the JSON body.
4. When accepting into a specific destination playground, include `target_playground_id` in the body.

Example accept body:

```json
{
  "status": "accepted",
  "target_playground_id": "5e4f4aa4-a102-4878-8b86-9623a02f2f01"
}
```

Example decline body:

```json
{
  "status": "declined"
}
```

Success semantics:

- `accepted` returns `200` with `message_code = theme.invitation.accepted` and a `permission` object in `data`
- `declined` returns `200` with `message_code = theme.invitation.declined`
- if `target_playground_id` is omitted on acceptance, the backend uses the invitee default playground when one exists

### Inviter or admin cancels

1. Authenticate as the original inviter or an admin user.
2. Call `PATCH /api/invitations/{invitation}`.
3. Send:

```json
{
  "status": "canceled"
}
```

Success semantics:

- `canceled` returns `200` with `message_code = theme.invitation.canceled`
- invitees cannot cancel invitations

### Contract rules

- `PATCH /api/invitations/{invitation}` is an authenticated business endpoint. It is not a public email-link endpoint.
- `status` must be sent in the JSON body.
- `target_playground_id` must be sent in the JSON body when used.
- Query-string values such as `status`, `expires`, `signature`, or `intent` are not part of the response mutation contract.
- The endpoint is protected by the normal business middleware stack:
  - `auth:sanctum`
  - `access-token`
  - `verified`
  - route throttling

### Error handling

- `401 auth.failed`: no authenticated session
- `403 permission.denied`: authenticated user is not allowed to apply the requested transition
- `404 resource.not_found`: invitation does not exist
- `409 invitation.invalid_transition`: invitation is no longer pending
- `410 invitation.expired`: invitation is expired and cannot transition
- `422 validation.invalid`: body is malformed, `status` is missing, or `target_playground_id` is invalid for the requested action

### Recommended frontend behavior

- Persist the current frontend invitation URL through login and email-verification redirects.
- Read the optional `intent` query only to preselect UI state.
- Re-fetch `GET /api/invitations/{invitation}` after a successful `PATCH` when the screen needs fresh invitation data.
- On successful acceptance, refresh the user theme lists or related caches because the user may now see the shared theme through normal theme endpoints.
- Offer invitation deletion with `DELETE /api/invitations/{invitation}` only after a terminal state that allows deletion (`declined` or `canceled`).

## Read metrics

Use:

- `GET /api/stats` for global counters visible to the current user
- `GET /api/themes/{theme_id}/stats` for one theme
- `GET /api/user/stats` for user-specific metrics exposed by the dedicated user metrics endpoint

These endpoints are read models. They should be treated as derived summaries, not as the source of truth for individual tasks.
