FROM phpswoole/swoole:php8.1-alpine
WORKDIR /var/www
RUN docker-php-ext-install mysqli
RUN mkdir app
    # mkdir public
COPY /server ./
# COPY /public ./public
COPY /app ./app
# COPY /ressources/fonts ./public/assets/fonts
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer -d ./ install \
    && composer clear-cache
ENV COMPOSER_ALLOW_SUPERUSER=0

CMD [ "php", "server.php"]