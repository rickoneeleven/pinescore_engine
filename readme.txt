# pinescore_engine - Laravel Framework 7.18.0
Laraverl project that handles the nuts and bolts of the ping engine for pinescore

composer update (maybe install?)
cp .env.example .env
configure .env
    update APP_URL, SQL and MAIL bits
    

change composer.json so it can use newer versions of the laravel framework, only if you have issues installing the below
    specify the exact version

sudo apt install redis-server
check it works: redis-cli

composer require predis/predis

check horizon is installed: "composer show -i|grep hori"
if not, play with "composer require laravel/horizon"
php artisan horizon:install
php artisan horizon:publish

php artisan key:generate
php artisan config:clear
php artisan migrate

add crontab
* * * * * cd /home/pinescore/domains/engine.pinescore.com/public_html && php artisan schedule:run >> /dev/null 2>&1

test working: php artisan horizon
once working, kill the manual process and setup supervisor below

sudo apt-get install supervisor

vim /etc/supervisor/conf.d/horizon.conf
[program:horizon]
process_name=%(program_name)s
command=php /home/pinescore/domains/engine.pinescore.com/public_html/artisan horizon
autostart=true
autorestart=true
user=pinescore
redirect_stderr=true
stdout_logfile=/home/pinescore/domains/engine.pinescore.com/public_html/storage/logs/horizon.log
stdout_logfile_maxbytes=10240
stdout_logfile_backups=0
stopwaitsecs=3600

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon

#because the tracert engine uses the -I option (ICMP) we need to do some funk
setcap CAP_NET_ADMIN+ep "$(readlink -f /usr/sbin/traceroute)" //sometimes just /bin/
setcap CAP_NET_RAW+ep "$(readlink -f /usr/sbin/traceroute)"
#ref https://unix.stackexchange.com/questions/291019/how-to-allow-traceroute-to-run-as-root-on-ubuntu
