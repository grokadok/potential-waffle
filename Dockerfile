FROM phpswoole/swoole:php8.2-alpine
RUN apk update \
    && apk add --no-cache icu-dev icu-data-full \
    && docker-php-ext-install intl \
    && docker-php-ext-install mysqli
WORKDIR /var/www
RUN mkdir app \
    && mkdir app/model \
    && mkdir app/jwt \
    && mkdir public
COPY /server ./
COPY /public ./public
COPY /app/model ./app/model
COPY /app/jwt ./app/jwt
COPY /ressources/css/style.css ./public/css/style.css
COPY /ressources/fonts ./public/fonts
COPY /ressources/img ./public/img
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer update \
    && composer -d ./ install \
    && composer clear-cache
ENV COMPOSER_ALLOW_SUPERUSER=0

CMD [ "php", "server.php"]