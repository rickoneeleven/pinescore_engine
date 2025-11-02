DATETIME of last agent review: 02/11/2025 13:08 GMT
# pinescore_engine - Laravel Framework 7.30.3
Laravel project that handles the nuts and bolts of the ping engine for pinescore

Min system req's
- Absolute min of 2GB of RAM for redis/horizon engine to work, and you'll probably need the overcommit tweak below
I'd realistically be looking for min of 4GB of RAM. Used "atop" when engine is running to see if SWP is flashing red,
if so, you're swapping too much, more RAM please.
- php 7.4

composer update
cp .env.example .env
vim .env
    update APP_URL, CONTROL_IP_1 & CONTROL_IP_2, SQL and MAIL settings
    optional: tune TRACE_* values (see Traceroute Behavior Configuration)
    

composer.json pins Laravel to 7.30.3. Do not change unless required.

sudo apt install redis-server
check it works: redis-cli

composer require predis/predis

sooooo... laravel engine needs to run as php7.4, so let's assume you've got your webserver running that version... but what about your command line?
php --version
if that's showing wrong version, consider ssh'ing to server, changing to the relevent user, and adding the below to your .bashrc
alias php='/usr/bin/php7.4'

sudo apt-get install php7.4-mbstring

check horizon is installed: "composer show -i|grep hori"
if not, play with "composer require laravel/horizon"
php artisan horizon:install
php artisan horizon:publish

php artisan key:generate
php artisan config:clear
php artisan migrate

add crontab
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
@reboot /usr/bin/supervisorctl start horizon >> /var/log/horizon-boot.log 2>&1

#flush failed jobs or database grows and gobbles disk space
23 23 * * * cd /path/to/app && php artisan queue:flush

#allow engine to keep running when RAM low
vim /etc/sysctl.conf
vm.overcommit_memory=1
sysctl -p /etc/sysctl.conf
#if you don't want to enable this first without seeing if required, work through setting up the engine below, and then
#tail -f /var/log/redis/redis-server.log #if you see "Can't save in background: fork: Cannot allocate memory" then you may need
#this tweak

test working: php artisan horizon
once working, kill the manual process and setup supervisor below

sudo apt-get install supervisor

vim /etc/supervisor/conf.d/horizon.conf
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
startsecs=10 ; consider the process up after 10s
startretries=5 ; retry a few times before FATAL
stopwaitsecs=3600

sudo supervisorctl stop horizon
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon

# If Horizon shows FATAL after a terminate or quick restart, lower `startsecs` and give a few `startretries` (as above),
# and then recover with: `sudo supervisorctl clear horizon && sudo supervisorctl start horizon`.

#because the tracert engine uses the -I option (ICMP) we need to do some funk
setcap CAP_NET_ADMIN+ep "$(readlink -f /usr/sbin/traceroute)" //sometimes just /bin/
setcap CAP_NET_RAW+ep "$(readlink -f /usr/sbin/traceroute)"
then test traceroute works using the user account for this website, don't use sudo:
'/usr/sbin/traceroute' '-I' '-q1' '-w1' '-m30' '8.8.8.8'
#ref https://unix.stackexchange.com/questions/291019/how-to-allow-traceroute-to-run-as-root-on-ubuntu

########################################################################

TIPS: after any big changes, run the below to re-read all config files
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
composer dump-autoload
php artisan horizon:terminate

sudo supervisorctl stop horizon
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon

Horizon/Engine issues:
cd into public_html
tail -f storage/logs/horizon.log && tail -F storage/logs/laravel-$(date +%F).log
sudo tail -f /var/log/redis/redis-server.log

UPDATES: git pull and then "php artisan test" to make sure you're not missing any migrations 

########################################################################

## Clearing a runaway traceroute queue (September 2025)

If the `traceRoute` queue balloons because old runs re-enqueued duplicates (e.g. 6k+ jobs with only ~265 nodes), reset it and let the new long-lived locks take over:

1. Confirm queue depth and duplicated IPs (replace <prefix> with your Redis prefix, default is `${APP_NAME}_database_`):
   - `redis-cli LLEN <prefix>queues:traceRoute`
   - `redis-cli LRANGE <prefix>queues:traceRoute 0 5000 | grep -o "[0-9]\+\.[0-9]\+\.[0-9]\+\.[0-9]\+" | sort | uniq -c | sort -nr | head`
2. Wipe the queue and related metadata (safe, idempotent for this queue):
   - `redis-cli DEL <prefix>queues:traceRoute <prefix>queues:traceRoute:reserved <prefix>queues:traceRoute:delayed <prefix>queues:traceRoute:notify`
3. Kick the scheduler once to repopulate unique jobs:
   - `/usr/bin/php7.4 artisan run:trace-route`
4. Verify everything is clean:
   - `redis-cli LLEN <prefix>queues:traceRoute` -> should match `SELECT COUNT(DISTINCT ip) FROM ping_ip_table`
   - `redis-cli LRANGE ... | uniq -c` -> every IP count is 1
   - `redis-cli --scan --pattern '<prefix>trace-route-lock:*' | head` followed by `redis-cli TTL <lock>` -> TTL ~604800 confirms new 7-day lock in place

We lifted `TracerouteJob::LOCK_TTL_SECONDS` to 604800 seconds so nodes stay locked until their job completes or times out, preventing fresh duplicates after this reset.

## Traceroute Behavior Configuration

Traceroute is executed via `trace_hybrid.sh` using system `ping` and `traceroute`. Tune behavior with environment variables:
- `TRACE_MAX_TTL` maximum TTL to probe. Default 30.
- `TRACE_TTL_PROBES` attempts per TTL hop. Default 3.
- `TRACE_WAIT_SECS` seconds to wait per probe. Default 1.
- `TRACE_ECHO_PROBES` echo pings per hop for latency. Default 3.
- `TRACE_PROCESS_TIMEOUT` hard timeout for the traceroute process. Default 120.
- `TRACE_IDLE_TIMEOUT` idle timeout for no output. Default 60.
- `TRACE_JOB_TIMEOUT` job timeout in seconds. Default is max(TRACE_PROCESS_TIMEOUT + 30, 180).
- `TRACE_JOB_TRIES` max attempts for the job. Default 1.

Note: The old `TRACEROUTE_BIN_LOCATION` env is not used. Capabilities are applied directly to the system `traceroute` binary as shown above.

########################################################################

## Ping Behavior Configuration

Tune basic ping behavior via environment variables:
- `PING_DEADLINE_SECONDS` total seconds given to the `ping` command (Linux `-w`). Default 2.
- `PING_COUNT` echo requests per `ping` invocation (Linux `-c`). Default 2.
- `PING_ATTEMPTS` how many times to invoke `ping` if a parseable reply is not seen. Default 2.

For higher UI refresh cadence without sacrificing stability (confirmation counters still apply), a common profile is:
- `PING_DEADLINE_SECONDS=1`
- `PING_COUNT=1`
- `PING_ATTEMPTS=2`

When a node's status appears to change, an ICMP control check to `CONTROL_IP_1` then `CONTROL_IP_2` runs. If both fail, the engine suppresses any state transition or alerts for that iteration but still records the attempt and updates `last_ran`.
