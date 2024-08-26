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
#ref https://unix.stackexchange.com/questions/291019/how-to-allow-traceroute-to-run-as-root-on-ubuntu

########################################################################

TIPS: after any big changes, run the below to re-read all config files
php artisan config:clear
php artisan cache:clear
composer dump-autoload
php artisan view:clear
php artisan route:clear
php artisan horizon:terminate

sudo supervisorctl stop horizon
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon

Horizon/Engine issues:
cd into public_html
tail -f storage/logs/horizon.log && tail -f storage/logs/laravel.log
sudo tail -f /var/log/redis/redis-server.log
