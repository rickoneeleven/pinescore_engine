# Doc Recreation Process v1.2

## Trigger

Any README.md or ops/*.md file with `DATETIME of last agent review` older than 30 days, or missing entirely.

## Why Rebuild vs Patch

Patching stale docs creates inconsistent Frankenstein files. Rebuilding from scratch using templates ensures docs match current code with no legacy cruft.

## Documentation Standard

All projects follow this structure:
```
README.md              <- single root README, no others allowed
ops/
  SYSTEMD.md           <- or equivalent service doc
  TESTING.md           <- if tests need more than 2-3 lines in README
  DB.md                <- if database setup is complex
  ...                  <- other component docs as needed
```

**Rules:**
- ONE README.md at project root only
- NO README.md files in subfolders (backend/, frontend/, src/, etc.)
- NO scattered docs (backend/TESTING.md, src/ARCHITECTURE.md, etc.)
- ALL operational/detailed docs live in ops/
- Vendor/node_modules/dist/build/venv are excluded from all operations

## Process

### 1. Context Gathering (Read, Don't Preserve)
- Read existing README.md and all ops/*.md files
- Read any other .md files in the project (excluding vendor folders)
- Note what topics they covered (not the content itself)
- Identify any project-specific terminology or naming conventions

### 2. Codebase Crawl

| Target | What to Extract |
|--------|-----------------|
| `package.json` / `Makefile` | Commands, scripts, stack |
| `.nvmrc` / `.tool-versions` | Runtime versions |
| `.env.example` | Required config vars |
| `ops/systemd/` | Service names and types |
| `src/` or equivalent | Component structure, entry points |
| `db/migrations/` | Schema context, table names |
| `tests/` or `test/` | Test commands, framework, complexity |
| `docker-compose.yml` | Container services |

### 3. Delete ALL Documentation
Delete all .md files except vendor/third-party:
```bash
find . -name "*.md" \
  -not -path "./node_modules/*" \
  -not -path "./vendor/*" \
  -not -path "./.git/*" \
  -not -path "./dist/*" \
  -not -path "./build/*" \
  -not -path "./venv/*" \
  -not -path "./AGENTS_templates/*" \
  -delete
```
This removes:
- Root README.md
- All ops/*.md
- Any scattered docs (backend/README.md, frontend/TESTING.md, etc.)

### 4. Recreate README
- Use `AGENTS_templates/reed_me.md` template
- Focus on: purpose, stack, quick start, dev commands, config pointer
- Point to `ops/` for operational details
- Target: under 60 lines

### 5. Recreate ops/ Docs
- Use `AGENTS_templates/ops_doc.md` template
- One doc per logical component/concern
- Each doc: 15 lines max, pointers to key files
- Update timestamp on each file

### 6. Verify
- [ ] README has accurate commands (tested or verified in package.json)
- [ ] Each ops/ doc points to files that exist
- [ ] No orphan references to deleted docs
- [ ] All timestamps updated to current date

## Ops Doc Discovery

Determine which ops/ docs to create based on what exists in codebase:

| If You Find | Create |
|-------------|--------|
| `ops/systemd/` or service files | `ops/SYSTEMD.md` |
| Database migrations / schema | `ops/DB.md` |
| Worker/cron/background jobs | `ops/WORKERS.md` or `ops/INGEST.md` |
| Deployment scripts / CI config | `ops/DEPLOY.md` |
| Complex auth/security setup | `ops/AUTH.md` |
| External API integrations | `ops/INTEGRATIONS.md` |
| Nginx/Apache/proxy config | `ops/WEB.md` |
| Complex test setup (fixtures, DB seeds, mocks, CI) | `ops/TESTING.md` |
| Complex config (multiple env files, secrets, environment-specific) | `ops/CONFIG.md` |

**TESTING.md decision:**
- If tests are simple (`npm test`, maybe single-file command), cover in README Dev Commands
- If tests require setup (DB fixtures, env vars, mocks, special config), create `ops/TESTING.md`
- Move any scattered test docs (backend/TESTING.md, test/README.md) into ops/TESTING.md

**CONFIG.md decision:**
- Simple env vars: document in `.env.example` comments, README Config section just says "see .env.example"
- Complex config (README_ENV.md, multiple env files, secrets management, environment-specific setup): create `ops/CONFIG.md`
- Delete any README_ENV.md or similar scattered env docs after consolidating

Only create docs for components that actually exist. No placeholders.

## Recreation Order

1. **README.md** - establishes project context
2. **ops/SYSTEMD.md** (or equivalent) - how it runs
3. **Remaining ops/ docs** - by importance/complexity

## Example Workflow

```
Agent detects ops/COMMENT_FLOW.md is 45 days old

1. Context gathering - read and note topics from:
   - README.md (stack, commands, architecture notes)
   - ops/COMMENT_FLOW.md (comment ingestion, gates)
   - ops/SYSTEMD_USER.md (services list)
   - backend/TESTING.md (test setup - scattered doc, will be consolidated)
   - ... all other .md files

2. Crawl codebase:
   - package.json: Node 20, scripts available
   - ops/systemd/: 5 service files found
   - backend/src/: component structure mapped
   - db/migrations/: schema understood
   - test/: uses fake DB, has fixtures

3. Delete ALL documentation:
   find . -name "*.md" -not -path "./node_modules/*" ... -delete

   Removed:
   - README.md
   - ops/COMMENT_FLOW.md (verbose, 200 lines)
   - ops/SYSTEMD_USER.md (verbose, 107 lines)
   - ops/INGEST_RUNBOOK.md
   - backend/TESTING.md (scattered, wrong location)
   - ... all other .md files

4. Recreate README.md (~50 lines) using reed_me.md template

5. Recreate ops/ docs using ops_doc.md template:
   - ops/SYSTEMD.md (15 lines - just service names and paths)
   - ops/COMMENTS.md (12 lines - key files only)
   - ops/TESTING.md (10 lines - consolidated from backend/TESTING.md)
   - ops/DB.md (8 lines)
   - ops/INGEST.md (10 lines)

6. Verify all file paths exist, all timestamps current
```

## What NOT to Do

- Don't preserve verbose explanations from old docs
- Don't copy procedural instructions that belong in code comments
- Don't create docs for components that don't exist yet
- Don't include troubleshooting guides (agent debugs live)
- Don't duplicate content across multiple ops/ docs
- Don't create README.md files in subfolders (backend/README.md, etc.)
- Don't leave scattered docs - consolidate into ops/
- Don't skip the delete step - clean slate is the point
