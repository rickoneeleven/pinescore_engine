# Services

DATETIME of last agent review: 15 Dec 2025 16:40 (Europe/London)

## Purpose

Laravel Horizon manages queue workers via Redis, running under Supervisor.

## Key Files

- `config/horizon.php` - queue supervisor configuration
- `/etc/supervisor/conf.d/horizon.conf` - Supervisor unit file
- `storage/logs/horizon.log` - Horizon process logs

## Related

- Redis on localhost:6379
- `ops/QUEUES.md` - job definitions

## Agent Commands

```bash
php artisan horizon:terminate
sudo supervisorctl restart horizon
sudo supervisorctl status horizon
sudo supervisorctl clear horizon
tail -f storage/logs/horizon.log
```

## Notes

- Dashboard at `/horizon` (web middleware only)
- Horizon runs as single supervisor process managing both ping and traceroute workers

## Intentional Behavior

- `startsecs=10` and `startretries=5` in supervisor conf prevent FATAL after quick restarts
- `stopwaitsecs=3600` allows long-running traceroute jobs to complete on shutdown
