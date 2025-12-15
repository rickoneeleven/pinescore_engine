# Ops Doc Template v1.1

## Why This Matters

All ops/ docs are ingested at session start. Verbose docs waste context on details the agent may never need. Keep docs as concise indexes that make the agent *aware* of components and *where to look* for details.

The agent reads source files on-demand when actually working on a component.

## Template

```markdown
# [Component Name]

DATETIME of last agent review: DD MMM YYYY HH:MM (Europe/London)

## Purpose
One sentence describing what this component does.

## Key Files
- `path/to/main.ts` - brief role
- `path/to/other.ts` - brief role

## Related
- `ops/OTHER_DOC.md` - if cross-cutting dependency
- `ops/systemd/service-name.service` - if applicable

## Agent Service Commands
- any commands that will help the agent do stuff related to operations in this doc

## Notes
- Optional 1-2 bullets only if critical context not obvious from code
- Delete this section if empty

## Intentional Behavior
- Document non-obvious design decisions that future agents might question
- Prevents agents from "fixing" things that aren't broken
- Delete this section if empty
```

---

## Good Example: SYSTEMD_USER.md

```markdown
# Systemd Services

DATETIME of last agent review: 06 Dec 2025 14:00 (Europe/London)

## Purpose
Rootless systemd user services for backend, ingestion, and enrichment workers.

## Key Files
- `ops/systemd/polyscore-backend.service` - API server
- `ops/systemd/polyscore-ingest.service` + `.timer` - trades/prices/comment backfill
- `ops/systemd/polyscore-prune.service` + `.timer` - data cleanup
- `ops/systemd/polyscore-commenter-age.service` + `.timer` - trade age lookup
- `ops/systemd/polyscore-commenter-pnl.service` + `.timer` - PnL enrichment

## Agent Service Commands
User services require environment variables to access the systemd bus:
\`\`\`bash
XDG_RUNTIME_DIR=/run/user/1001 DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/1001/bus systemctl --user restart polyscore-backend
XDG_RUNTIME_DIR=/run/user/1001 DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/1001/bus systemctl --user status polyscore-backend
\`\`\`

## Notes
- `polyscore-comments` runs as system service (not user) at `/etc/systemd/system/`
- Requires `loginctl enable-linger $USER`
- User ID 1001 = loopnova

## Intentional Behavior
Position Logging Every Run - The trade monitor logs every active position on every cron run (per minute). This is intentional - not a bug or oversight. The logs serve as a health check / heartbeat, allowing quick verification that positions are being actively monitored by browsing the activity log.
```

**Why it works:** Agent knows what services exist, where unit files live, and how to restart them. Reads `.service` files when implementing changes.
---

## Bad Example: SYSTEMD_USER.md (verbose)

```markdown
# PolyScore Systemd User Services Guide

DATETIME of last agent review: 04 Dec 2025 19:35 (Europe/London)

This project is designed to run as a standard user (rootless) using systemd user services.

## Services Overview

| Service | Type | Schedule | Purpose |
|---------|------|----------|---------|
| polyscore-backend | continuous | always | Fastify API server |
| polyscore-comments* | continuous | always | Primary comment ingestion via WebSocket |
| polyscore-ingest | timer | every 1 min | Backup comment backfill, trades, prices |
...

## Prerequisites

1.  **Enable Linger:** This allows user services to run even when you are not logged in.
    ```bash
    sudo loginctl enable-linger $USER
    ```

2.  **Verify Runtime Directory:**
    Ensure `XDG_RUNTIME_DIR` is available...

## Installation

1.  **Create Config Directory:**
    ```bash
    mkdir -p ~/.config/systemd/user
    ```

2.  **Link Unit Files:**
    Link the provided service files to your configuration directory.
    ```bash
    ln -sf $PWD/ops/systemd/polyscore-backend.service ~/.config/systemd/user/
    ...
```

**Why it fails:**
- 107 lines ingested at startup
- Full bash commands the agent can read from the `.service` files themselves
- Installation instructions belong in the service file comments or a one-time setup script
- Table duplicates info already in file names

---

## Principles

1. **Awareness over instruction** - Agent needs to know what exists, not how to use it
2. **Pointers over content** - List key file paths, let agent read them when needed
3. **One sentence purpose** - If you need a paragraph, the component is too complex or you're over-explaining
4. **Notes are exceptions** - Only include if critical context that cannot be inferred from code
5. **100 lines max** - If longer, you're writing a guide not an index