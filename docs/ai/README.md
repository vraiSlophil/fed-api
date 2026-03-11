# AI Context

These notes are complementary to the generated OpenAPI spec in `public/docs/openapi.yaml`.

Use them when an AI agent needs fast project context without reading the full codebase.

## Recommended reading order

1. `public/docs/openapi.yaml`
2. `docs/auth/AUTHENTICATION.md`
3. `docs/ai/DOMAIN_OVERVIEW.md`
4. `docs/ai/WORKFLOWS.md`

## Scope

- `openapi.yaml` is the canonical endpoint reference.
- `AUTHENTICATION.md` explains token lifecycle and auth-specific constraints.
- `DOMAIN_OVERVIEW.md` explains the business entities and access model.
- `WORKFLOWS.md` explains common multi-step API flows that are harder to infer from OpenAPI alone.

## Usage guidance for agents

- Prefer `message_code` over `message` for machine decisions.
- Expect the standard JSON envelope on successful responses, except for endpoints documented as `204 No Content`.
- Treat authorization failures as domain constraints, not transport failures.
- Prefer UUID identifiers returned by the API instead of reconstructing slugs or names.
