# HTTP Response Contract

## 204 No Content

For endpoints that return `204 No Content` (for example successful delete operations), the API returns an empty response body.

Rules:
- Do not return a JSON envelope with `204`.
- If a JSON envelope is required (`status`, `message`, `message_code`, `data`), use `200` instead.
