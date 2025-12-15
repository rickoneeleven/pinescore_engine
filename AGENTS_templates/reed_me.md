# README Template v2.1

## Purpose

Get a new contributor from clone to running code fast. An LLM agent assists with setup, so README points to key info rather than duplicating it. Operational details live in `ops/`.

## Principles

1. **Source of truth is the repo** - scan for commands, don't invent them
2. **Omit sections with no evidence** - no placeholders, no guesses
3. **Prefer wrapper scripts** - `make dev`, `npm run start` over raw commands
4. **Stack versions upfront** - agent needs to know what to install before anything else
5. **Point, don't duplicate** - reference `ops/`, `.env.example`, config files

## Command Discovery Priority

Makefile targets > `bin/*` scripts > `package.json` scripts > `docker compose` services

Detect versions from: `.nvmrc`, `.tool-versions`, `package.json` engines, `pyproject.toml`

## Section Blueprint

### Required
| Section | Content |
|---------|---------|
| Title | Project name |
| Purpose | One sentence: what it does, for whom |
| Stack | Runtime versions with source (e.g., "Node 20 - see `.nvmrc`") |
| Quick Start | Clone, install, run - 3-5 commands max |

### If Applicable
| Section | Content |
|---------|---------|
| Dev Commands | Test (all + single file), lint, build - exact commands |
| Agent Notes | Any notes to help the agentic LLM coding agent perform thier duties |
| Config | Required env vars, point to `.env.example` |
| Deployment | Command or link to deploy script |
| Links | Live docs, dashboards - no dead links |

### Always Omit
- Architecture deep dives (agent reads code, see `ops/` for component indexes)
- Troubleshooting guides (agent debugs live)
- Marketing copy, screenshots, badges, history

---

## Skeleton

```markdown
# Project Name

DATETIME of last agent review: DD MMM YYYY HH:MM (Europe/London)

One sentence: what this does and for whom.

## Stack
- Node 20+ (`.nvmrc`)
- MariaDB 10.5+
- [other runtime requirements]

## Quick Start
```bash
git clone <repo>
cp .env.example .env
npm install
npm run dev
```

## Dev Commands
- `npm test` - run all tests
- `npm test -- path/to/file` - run single file
- `npm run lint` - lint check
- `npm run build` - production build

## Dev Commands
- You have passwordless sudo and authorisation to use it for service restart, or any other functions that require sudo

## Config
Required env vars - see `.env.example` for full list:
- `DATABASE_URL` - MariaDB connection string
- `API_KEY` - external service key

## Deployment
See `ops/DEPLOY.md` or `make deploy`

## Links
- [Dashboard](https://...)
- Operations docs: `ops/`
```

---

## Good Example

```markdown
# PolyScore

DATETIME of last agent review: 06 Dec 2025 15:00 (Europe/London)

Polymarket analytics dashboard tracking smart money activity across prediction markets.

## Stack
- Node 20+ (`.nvmrc`)
- MariaDB 10.5+
- Systemd (user services)

## Quick Start
```bash
cp backend/.env.example backend/.env
npm --prefix backend install
npm --prefix frontend install
npm --prefix backend run dev
```

## Dev Commands
- `npm --prefix backend test` - all tests
- `npm --prefix backend test -- path/to/file` - single file
- `npm --prefix backend run build`
- `npm --prefix frontend run build`

## Dev Commands
- You have passwordless sudo and authorisation to use it for service restart, or any other functions that require sudo

## Config
See `backend/.env.example`. Required:
- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`

## Operations
See `ops/` for service configuration, ingestion runbooks, and systemd setup.
```

**Why it works:** ~40 lines. Stack is clear. Commands are real. Points to ops/ for operational detail.

---

## Bad Example

```markdown
# PolyScore - Smart Money Analytics Platform

[![Build Status](https://...)](https://...)
[![Coverage](https://...)](https://...)

## Overview

PolyScore is a cutting-edge analytics platform designed to help traders identify smart money movements in prediction markets. Our sophisticated algorithms analyze trading patterns, wallet histories, and market dynamics to surface actionable insights.

### Features
- Real-time trade ingestion
- Smart wallet classification
- Comment sentiment analysis
- Beautiful dashboard UI
- And much more!

## Architecture

The system consists of several microservices:
- **Backend API**: Fastify-based REST API serving market data...
- **Frontend**: React SPA with Recharts visualization...
- **Workers**: Multiple systemd services handling...

[200 more lines of architecture, screenshots, contributor guidelines, code of conduct...]
```

**Why it fails:**
- Badges add no value for setup
- Marketing copy ("cutting-edge", "sophisticated")
- Feature list duplicates what agent will discover
- Architecture belongs in `ops/` or code comments
- Screenshots go stale
- 200+ lines when 40 would do

---

## Validation Before Output

- [ ] Every command exists in repo (Makefile/package.json/bin/)
- [ ] Stack versions sourced from actual config files
- [ ] No sections without evidence
- [ ] Points to `ops/` for operational detail
- [ ] Under 60 lines
