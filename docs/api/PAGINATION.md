# API Pagination Contract (Offset)

This document defines the reusable offset pagination contract introduced by issue #43.

## Scope

- Stateless JSON API endpoints.
- Standard query params:
  - `page` (default: `1`)
  - `per_page` (default: `15`, max: `100`)
- Standard response shape:
  - `data`
  - `meta`

## Query Contract

### Common parameters

- `page`: integer, minimum `1`
- `per_page`: integer, minimum `1`, maximum `100`

Invalid values return `422` with `message_code: validation.invalid`.

### Endpoint-specific filters

Each endpoint may define additional filters.

Example for invitation listing:
- `status`: `pending|accepted|declined|expired|canceled`
- default status for invitation center listing: `pending`

## Response Contract

Inside the existing API envelope:

```json
{
  "status": "success",
  "message": "OK",
  "message_code": "invitation.list.success",
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 42,
    "last_page": 3,
    "from": 1,
    "to": 15,
    "has_next": true
  }
}
```

## Out-of-bounds behavior

The API keeps Laravel offset pagination behavior:

- Requesting `page` greater than `last_page` returns `200`
- `data` is empty
- `meta.current_page` keeps the requested page
- `meta.has_next` is `false`

Example:

```json
{
  "data": [],
  "meta": {
    "current_page": 999,
    "per_page": 15,
    "total": 12,
    "last_page": 1,
    "from": null,
    "to": null,
    "has_next": false
  }
}
```
