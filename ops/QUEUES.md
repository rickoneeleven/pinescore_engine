# Queue Jobs

DATETIME of last agent review: 09 Jan 2026 11:47 (Europe/London)

## Purpose

Ping and traceroute jobs execute ICMP checks, update node status, and trigger notifications (mail queue).

## Key Files

- `app/Jobs/PingJob.php` - ping execution and state transitions
- `app/Jobs/PingJobDribblyBits.php` - helper functions for ping logic
- `app/Jobs/TracerouteJob.php` - traceroute execution with 7-day locks
- `app/Console/Commands/PingCommand.php` - dispatches ping jobs for all nodes
- `app/Console/Commands/RunTraceRoute.php` - dispatches traceroute jobs
- `app/Notifications/` - alert notifications

## Related

- `ops/SERVICES.md` - Horizon/Supervisor management
- `trace_hybrid.sh` - traceroute shell script

## Agent Commands

```bash
php artisan run:trace-route
php artisan horizon:supervisors
PREFIX="$(php -r 'require \"vendor/autoload.php\"; $app=require \"bootstrap/app.php\"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo config(\"database.redis.options.prefix\");')"
redis-cli LLEN "${PREFIX}queues:default" && redis-cli LLEN "${PREFIX}queues:traceRoute" && redis-cli LLEN "${PREFIX}queues:mail"
```

## Intentional Behavior

- Separate queues (default/traceRoute) prevent hourly traceroutes from delaying per-minute pings
- Mail notifications run on dedicated queue (`mail`) to cap SMTP concurrency without slowing pings
- TracerouteJob uses 7-day lock (LOCK_TTL_SECONDS=604800) to prevent duplicate jobs
- Control IP check (CONTROL_IP_1/2) runs before any node state change to detect engine-side outages
- PingCommand stores cycles_per_minute in Redis; healthy is 5-6, low indicates queue drain issues
- Index on ping_ip_table.ip is critical for performance (migration 2025_12_17_131217)
