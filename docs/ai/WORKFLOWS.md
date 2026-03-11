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
3. Track pending invitations with `GET /api/invitations`.
4. Inspect a single invitation with `GET /api/invitations/{invitation_id}`.

Notes:

- Invitation creation is limited to supported invitable types. The current controller supports theme invitations.
- Theme sharing is not modeled as direct user assignment on the theme endpoints. It goes through invitations and member permissions.

## Respond to an invitation

Two paths exist:

### Authenticated response

1. Authenticate with a normal access token.
2. Call `PATCH /api/invitations/{invitation_id}`.
3. Send a body with the desired status.
4. If accepting into a target playground, include `target_playground_id` when required by the invitation rules.

### Signed-link response

1. Use the signed URL context provided by the invitation flow.
2. Call `PATCH /api/invitations/{invitation_id}` with the signed query string.
3. Send an allowed response status in the request body.

Notes:

- Signed-link responses are throttled.
- Canceling an invitation requires authenticated access and is not allowed from the signed public flow.

## Read metrics

Use:

- `GET /api/stats` for global counters visible to the current user
- `GET /api/themes/{theme_id}/stats` for one theme
- `GET /api/user/stats` for user-specific metrics exposed by the dedicated user metrics endpoint

These endpoints are read models. They should be treated as derived summaries, not as the source of truth for individual tasks.
