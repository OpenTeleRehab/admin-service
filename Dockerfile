FROM wehostasia/laravel:nginx-php-8.3

RUN apt-get update -y && apt-get install ffmpeg -y && apt install imagemagick -y

# Modify ImageMagick policy to allow PDF conversion
RUN sed -i 's#<policy domain="coder" rights="none" pattern="PDF" />#<policy domain="coder" rights="read|write" pattern="PDF" />#' /etc/ImageMagick-6/policy.xml

RUN echo "* * * * * www-data /usr/bin/php /var/www/artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/hi-task-scheduler
