# Contributing

Thank you for contributing to Poof Backend.

## Workflow rules

- Use **feature branches** for all work (no direct commits to `main`).
- A **pull request is required** for every merge to `main`.
- Write **descriptive commit messages** that clearly explain the change.
- **CI must pass** before merge.

## Branch protection recommendations

Configure `main` branch protection in GitHub repository settings:

- Require a pull request before merging.
- Require status checks to pass (`CI`).
- Require branches to be up to date before merging.
- Restrict direct pushes to `main`.

## Local development expectations

Before opening a pull request, run:

```bash
composer install
npm ci
npm run build
```

And ensure no business logic changes are introduced unless explicitly required.
