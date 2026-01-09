# Services

DATETIME of last agent review: 09 Jan 2026 11:47 (Europe/London)

## Purpose

Laravel Horizon manages queue workers via Redis, running under Supervisor.

## Key Files

- `config/horizon.php` - queue supervisor configuration
- `/etc/supervisor/conf.d/horizon.conf` - Supervisor unit file
- `storage/logs/horizon.log` - Horizon process logs
- Root cron: `sudo crontab -l` (daily Horizon restart)

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
- Horizon is expected to have exactly one master + supervisors (ping, traceroute, mail) on this host

## Intentional Behavior

- Root cron restarts Horizon daily at `04:29`; Supervisor kills child workers with the master (`stopasgroup=true`, `killasgroup=true`)
- `startsecs=10` and `startretries=5` prevent Supervisor from wedging into `FATAL` during restarts
- `stopwaitsecs=3600` allows long-running traceroute jobs to complete on shutdown
