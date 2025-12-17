DATETIME of last agent review: 16 Dec 2025 16:40 (Europe/London)

# pinescore_engine

ICMP ping monitoring engine for pinescore. Handles scheduled pings, traceroutes, and alert notifications via Laravel Horizon queues.

## Stack

- PHP 7.4+
- Laravel 7.30.3 (pinned in composer.json)
- Redis (queue backend)
- MariaDB/MySQL
- Supervisor (process management)
- Min 2GB RAM (4GB recommended)

## Quick Start

```bash
git clone <repo>
cp .env.example .env
composer install
# Edit .env: APP_URL, CONTROL_IP_1, CONTROL_IP_2, DB_*, MAIL_*
php artisan key:generate
php artisan migrate
php artisan horizon
```

## First-Time Server Setup

### PHP CLI Version

If `php --version` shows wrong version, add to ~/.bashrc:
```bash
alias php='/usr/bin/php7.4'
```

Required extension:
```bash
sudo apt-get install php7.4-mbstring
```

### Redis

```bash
sudo apt install redis-server
redis-cli  # test connection
composer require predis/predis
```

### Horizon

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon:publish
```

### Supervisor

```bash
sudo apt-get install supervisor
```

Create `/etc/supervisor/conf.d/horizon.conf`:
```ini
[program:horizon]
process_name=%(program_name)s
command=/bin/sh -c 'sleep 1 && /usr/bin/php7.4 /path/to/app/artisan horizon'
autostart=true
autorestart=true
user=<app_user>
redirect_stderr=true
stdout_logfile=/path/to/app/storage/logs/horizon.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=10
startsecs=10
startretries=5
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start horizon
```

### Traceroute Capabilities

Engine uses ICMP traceroute (-I flag). Grant capabilities:
```bash
setcap CAP_NET_ADMIN+ep "$(readlink -f /usr/sbin/traceroute)"
setcap CAP_NET_RAW+ep "$(readlink -f /usr/sbin/traceroute)"
```

Test as app user (not root):
```bash
'/usr/sbin/traceroute' '-I' '-q1' '-w1' '-m30' '8.8.8.8'
```

### Crontab

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
@reboot /usr/bin/supervisorctl start horizon >> /var/log/horizon-boot.log 2>&1
23 23 * * * cd /path/to/app && php artisan queue:flush
```

### Memory Overcommit (Low RAM Systems)

If redis logs show "Can't save in background: fork: Cannot allocate memory":
```bash
echo 'vm.overcommit_memory=1' >> /etc/sysctl.conf
sysctl -p /etc/sysctl.conf
```

## Configuration

Required env vars (see `.env.example`):
- `CONTROL_IP_1`, `CONTROL_IP_2` - failsafe ping targets; engine checks these before marking nodes down
- `PING_DEADLINE_SECONDS` - total time for ping command (default 2)
- `PING_COUNT` - echo requests per ping (default 2)
- `PING_ATTEMPTS` - retry attempts if no parseable reply (default 2)
- `TRACE_MAX_TTL` - maximum TTL to probe (default 30)
- `TRACE_PROCESS_TIMEOUT` - hard timeout for traceroute (default 120)
- `TRACE_JOB_TIMEOUT` - job timeout in seconds (default max(TRACE_PROCESS_TIMEOUT + 30, 180))

## Common Operations

```bash
# After code changes
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
composer dump-autoload
php artisan horizon:terminate
sudo supervisorctl restart horizon

# After git pull
php artisan test  # check for missing migrations
```

## Troubleshooting

### Horizon FATAL After Restart

If Horizon shows FATAL after terminate:
```bash
sudo supervisorctl clear horizon && sudo supervisorctl start horizon
```

### Runaway Traceroute Queue

If traceRoute queue balloons with duplicates (e.g. 6k+ jobs for ~265 nodes):

1. Check queue depth (replace `<prefix>` with your Redis prefix, default `${APP_NAME}_database_`):
```bash
redis-cli LLEN <prefix>queues:traceRoute
redis-cli LRANGE <prefix>queues:traceRoute 0 5000 | grep -o "[0-9]\+\.[0-9]\+\.[0-9]\+\.[0-9]\+" | sort | uniq -c | sort -nr | head
```

2. Clear the queue:
```bash
redis-cli DEL <prefix>queues:traceRoute <prefix>queues:traceRoute:reserved <prefix>queues:traceRoute:delayed <prefix>queues:traceRoute:notify
```

3. Repopulate:
```bash
/usr/bin/php7.4 artisan run:trace-route
```

### View Logs

```bash
tail -f storage/logs/horizon.log
tail -F storage/logs/laravel-$(date +%F).log
sudo tail -f /var/log/redis/redis-server.log
```

## Links

- Operations docs: `ops/`
