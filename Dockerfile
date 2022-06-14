FROM rathaheang/nginx-php:7.4

RUN apt-get update -y && apt-get install ffmpeg -y && apt install imagemagick -y

RUN echo "0 * * * * www-data /usr/bin/php /var/www/artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/hi-task-scheduler
RUN echo "15 1 * * * www-data /usr/bin/php /var/www/artisan hi:sync-patient-data --all >> /dev/null 2>&1" > /etc/cron.d/hi-task-scheduler-sync
