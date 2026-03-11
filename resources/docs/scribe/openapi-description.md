Generated API reference for the fed-webapp backend.

## Response envelope

Unless an endpoint explicitly returns `204 No Content`, successful JSON responses use the standard envelope:

```json
{
  "status": "success",
  "message": "Ok",
  "message_code": "machine.readable.code",
  "message_params": {},
  "data": {},
  "errors": {
    "field": [
      "Validation message"
    ]
  },
  "meta": {
    "request_id": "uuid"
  }
}
```

Notes:

- `errors` is only included outside production for error responses.
- `meta.request_id` and the `X-Request-Id` response header are available on API exception responses.
- `message_code` and `message_params` may be omitted on `5xx` responses in production.

## 204 No Content

Delete operations and other endpoints documented as `204 No Content` return an empty body. They do not use the JSON envelope.

## Offset pagination

Paginated endpoints use `page` and `per_page` query parameters and return pagination details in `meta`:

- `current_page`
- `per_page`
- `total`
- `last_page`
- `from`
- `to`
- `has_next`

Requesting a page above `last_page` still returns `200`, with an empty `data` payload and `meta.has_next = false`.
