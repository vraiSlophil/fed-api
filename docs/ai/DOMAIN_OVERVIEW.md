# Domain Overview

## Main entities

### User

- Authenticated actor of the API.
- Reads its own profile through `GET /api/users/me`.
- Admin routes also exist under `/api/users`, but they are not the normal entry point for a regular client.

### Playground

- Top-level workspace owned by one user.
- A user can list, create, update, and delete only its own playgrounds.
- Themes belong to a playground.
- `GET /api/playgrounds` can either list playgrounds or return a single playground when called with a `slug` query parameter.

### Theme

- A themed collection of tasks inside one playground.
- Owned by a user, but can also be shared with other users through invitations and per-member permissions.
- Theme visibility is not limited to ownership: a user can access themes shared with them.

### Task

- Work item attached to a theme.
- Visibility and mutation rights are derived from the theme membership / policy rules.
- Task lists use offset pagination metadata in the response `meta`.

### Invitation

- Used to grant access to a theme.
- Created by an authenticated, verified user with permission to manage theme members.
- Invitation emails open a frontend deep link, typically `/invite/{invitationId}`, with an optional UI-only `intent` query.
- The actual invitation response is applied only by an authenticated API call on `PATCH /api/invitations/{invitation}`.

### Metrics

- Aggregated counters derived from tasks.
- Available globally for the current user and per theme.

## Access model

Protected business routes generally require all of:

- `auth:sanctum`
- `access-token`
- verified email via `verified`

Typical implications:

- authentication alone is not sufficient for most read/write routes
- refresh tokens are not valid access tokens for business endpoints
- unverified users cannot use normal protected resources

## Ownership and sharing

- Playgrounds are user-owned resources.
- Themes belong to one playground.
- Themes can be shared with other users.
- Shared theme access is permission-based, not owner-based.
- Some theme member operations require `manageMembers`.

## Response conventions

- Most JSON responses use the standard envelope:
  - `status`
  - `message`
  - `message_code`
  - `data`
  - optional `errors`
  - optional `meta`
- Delete endpoints return `204 No Content` and do not use the envelope.

## Pagination conventions

Paginated list endpoints expose pagination state in `meta`, including:

- `current_page`
- `per_page`
- `total`
- `last_page`
- `from`
- `to`
- `has_next`

The codebase documents this as offset pagination. Clients should rely on the returned `meta` instead of deriving navigation state locally.
