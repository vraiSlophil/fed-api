# Breaking Changes Backend -> Frontend Baseline

Date: 2026-02-22  
Scope: Laravel 12 backend rearchitecture (modular monolith), without redesigning the global JSON response envelope.

## 1. Breaking changes (frontend)

### 1.1 Canonical task status
- Previously tolerated legacy status: `doing`
- New canonical status: `in_progress`

Frontend impact:
- Update all enums/types/UI using `doing`.
- Update request filters and status badges.
- Update frontend aggregations/stats that referenced `doing`.

Mapping:
- `doing` -> `in_progress`

### 1.2 Payloads stats
Stats payloads now expose `in_progress` (not `doing`) for task counters.

Frontend impact:
- Replace `stats.doing` access with `stats.in_progress`.

### 1.3 Signed invitation route parameter
The signed backend route uses `invitation` (explicit route-model binding) instead of `invitationId`.

Frontend impact:
- If your router/client parses signed URLs, do not depend on `invitationId`.
- Use the signed URL exactly as provided by the API, without rewriting parameter names.

## 2. Behavioral changes (potentially visible)

### 2.1 Authorization centralized through policies
Some routes can now return `403` more consistently (instead of implicit/variable behavior).

Frontend impact:
- Treat `403` with `message_code = permission.denied` as an expected case.

### 2.2 Validation centralized through FormRequests
`422` errors are now homogeneous across refactored endpoints.

Frontend impact:
- Standardize `422` error parsing.
- Use `message_code = validation.invalid` for generic validation messaging.

### 2.3 Security test routes
Test routes in `web.php` are restricted to `local/testing`.

Frontend impact:
- Do not depend on these routes in shared dev/staging/prod environments.

## 3. What does not change (important)
- Main API URIs remain stable.
- The global JSON envelope (`ApiResponseBuilder`) was not redesigned in this PR.
- The auth token flow contract (access/refresh) is unchanged.

## 4. Frontend migration checklist
1. Replace all `doing` usages with `in_progress` (types, UI, filters, tests).
2. Verify statistics screens (`in_progress` expected).
3. Verify invitation flows with signed URLs provided by the API.
4. Verify standardized handling of `403` and `422`.
5. Re-run core E2E flows: login, task listing, task status update, invitations, stats.

## 5. Backend technical notes (non-contractual for frontend)
- Backend models were reorganized by domain (`App\\Models\\Auth\\User`, etc.).
- This model reorganization is backend-internal and not a frontend contract.
