# Contributing

Thank you for contributing! To keep the repository consistent and easy to review, please follow the rules below.

## Language
- All repository content (issues, PR titles and descriptions, comments, commit messages, documentation, and code comments) must be written in English.

## Branch naming
- Create branches using the following pattern:
    - `<type>/<short-description>`
    - Optionally include an issue or ticket id: `<type>/JIRA-123-short-description` or `<type>/123-short-description`
- Allowed `type` prefixes:
    - `feat`, `fix`, `hotfix`, `refactor`, `docs`, `test`, `ci`, `chore`, `perf`, `style`, `build`, `release`, `revert`
- Examples:
    - `feat/add-login-endpoint`
    - `fix/handle-null-response`
    - `refactor/auth-middleware`
    - `ci/update-workflow-triggers`

## Commit messages
- Use Conventional Commits (in English) for all commit messages:
    - Official spec: https://www.conventionalcommits.org/en/v1.0.0/
- Examples:
    - `feat(auth): add refresh token endpoint`
    - `fix(api): return 403 when authorization fails`
    - `chore(deps): bump laravel/framework to vX.Y.Z`
- Keep commits small and focused. If a change requires multiple logical steps, use multiple commits.

## Pull requests
- Open your PR targeting the `dev` branch (not `main`).
- PR title should be descriptive; using Conventional Commit style in the title is encouraged (e.g. `fix(auth): ...`).
- In the PR description include:
    - A short summary of what the PR changes.
    - Any linked issue/ticket (e.g. `Closes #123`).
    - Testing instructions and expected results.
    - Notes about breaking changes, if any.
- Ensure automated checks (CI / linters / tests) pass before requesting a review.

## Reviews and merging
- Request a review from an admin or the repository owner: `vraiSlophil`.
- Do NOT merge your own PR. A PR must be approved by another reviewer (admin or owner) before merging.
- The PR may only be merged after:
    - All required CI checks have passed.
    - Required approvals have been obtained.
- Recommended branch protection (ask repository admins to enable):
    - Require status checks to pass before merging.
    - Require at least one approving review.
    - Dismiss stale approvals when new commits are pushed.
    - Restrict who can push to protected branches (e.g., `dev`, `main`).

## Tests
- Add or update tests for behavioral changes.
- Make sure tests run locally and pass in CI.
- For API changes, include feature tests that cover allowed and forbidden scenarios where applicable.

## Documentation and changelog
- Update relevant documentation and/or README when introducing new features or changing behavior.
- For user-facing changes, consider adding an entry to the changelog or release notes.

## Additional guidelines
- Keep PRs small and focused — they are easier to review.
- When applicable, include screenshots, curl examples, or sample requests/responses.
- Use descriptive commit messages and PR descriptions to help reviewers understand the intent.
