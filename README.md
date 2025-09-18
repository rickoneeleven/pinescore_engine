# pinescore_engine - Laravel Framework 7.18.0
Laraverl project that handles the nuts and bolts of the ping engine for pinescore

Min system req's
- Absolute min of 2GB of RAM for redis/horizon engine to work, and you'll probably need the overcommit tweak below
I'd realistically be looking for min of 4GB of RAM. Used "atop" when engine is running to see if SWP is flashing red,
if so, you're swapping too much, more RAM please.
- php 7.4

composer update
cp .env.example .env
vim .env
    update APP_URL, TRACEROUTE_BIN_LOCATION, CONTROL_IP_1&2, SQL and MAIL bits
    

change composer.json so it can use newer versions of the laravel framework, only if you have issues installing the below
    specify the exact version

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
* * * * * cd /home/pinescore/domains/engine.pinescore.com/public_html && php artisan schedule:run >> /dev/null 2>&1
@reboot /usr/bin/supervisorctl start horizon >> /var/log/horizon-boot.log 2>&1

#flush failed jobs or database grows and gobbles disk space
23 23 * * * cd /home/pinescore/domains/engine.pinescore.com/public_html && php artisan queue:flush

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
command=/bin/sh -c 'sleep 1 && php /home/loopnova/domains/cribengine.pinescore.com/public_html/artisan horizon'
autostart=true
autorestart=true
user=pinescore
redirect_stderr=true
stdout_logfile=/home/pinescore/domains/engine.pinescore.com/public_html/storage/logs/horizon.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=10
startsecs=600 ; process has to run for 600s before considered OK
startretries=0 ; try to start an infinite amount of times
stopwaitsecs=3600

sudo supervisorctl stop horizon
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon

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
tail -f storage/logs/horizon.log && tail -f storage/logs/laravel.log
sudo tail -f /var/log/redis/redis-server.log

UPDATES: git pull and then "php artisan test" to make sure you're not missing any migrations 

########################################################################

## Clearing a runaway traceroute queue (September 2025)

If the `traceRoute` queue balloons because old runs re-enqueued duplicates (e.g. 6k+ jobs with only ~265 nodes), reset it and let the new long-lived locks take over:

1. Confirm queue depth and duplicated IPs:
   - `redis-cli LLEN pinescore_database_queues:traceRoute`
   - `redis-cli LRANGE pinescore_database_queues:traceRoute 0 5000 | grep -o "[0-9]\+\.[0-9]\+\.[0-9]\+\.[0-9]\+" | sort | uniq -c | sort -nr | head`
2. Wipe the queue and related metadata (safe, idempotent for this queue):
   - `redis-cli DEL pinescore_database_queues:traceRoute pinescore_database_queues:traceRoute:reserved pinescore_database_queues:traceRoute:delayed pinescore_database_queues:traceRoute:notify`
3. Kick the scheduler once to repopulate unique jobs:
   - `/usr/bin/php7.4 artisan run:trace-route`
4. Verify everything is clean:
   - `redis-cli LLEN pinescore_database_queues:traceRoute` → should match `SELECT COUNT(DISTINCT ip) FROM ping_ip_table`
   - `redis-cli LRANGE ... | uniq -c` → every IP count is 1
   - `redis-cli --scan --pattern 'pinescore_database_trace-route-lock:*' | head` followed by `redis-cli TTL <lock>` → TTL ~604800 confirms new 7-day lock in place

We lifted `TracerouteJob::LOCK_TTL_SECONDS` to 604800 seconds so nodes stay locked until their job completes or times out, preventing fresh duplicates after this reset.
