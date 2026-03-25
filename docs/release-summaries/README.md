# Release summaries (explicit tags)

Each explicit release tag must have a short, operator-facing summary file:

- Path: `docs/release-summaries/<release-tag>.md`
- Example: `docs/release-summaries/release-20260325-1200.md`
- Purpose: answer “what went into this release?” in 1-3 short lines for operators.

## Minimal format

```md
# <release-tag>

<short summary line>

Created at (UTC): <timestamp>
```

The first non-empty non-heading line is treated as the canonical short summary and is shown by `scripts/show-release.sh` after deploy/rollback.

## How to create

Use the helper:

```bash
bash scripts/create-release-summary.sh release-YYYYMMDD-HHMM "Short operator summary"
```

Then commit the new summary file together with the release-ready code before tagging/deploying.
