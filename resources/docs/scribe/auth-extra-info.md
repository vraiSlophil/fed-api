Use a valid access token for protected routes.

Refresh-token rotation is documented on `POST /api/auth/refresh` and accepts the refresh token through `X-Refresh-Token`, with a Bearer fallback on that endpoint only.

Refresh tokens are rotated on every successful refresh. Reusing a refresh token outside the grace window revokes all active tokens for the user. Logout and password changes also revoke active tokens.
