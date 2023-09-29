FROM phpswoole/swoole:php8.1-alpine
WORKDIR /var/www
RUN docker-php-ext-install mysqli &&\
    docker-php-ext-install intl &&\
    mkdir app &&\
    mkdir app/model &&\
    mkdir app/jwt &&\
    mkdir public
COPY /server ./
COPY /public ./public
COPY /app/model ./app/model
COPY /app/jwt ./app/jwt
COPY /ressources/css/style.css ./public/css/style.css
COPY /ressources/fonts ./public/fonts
COPY /ressources/img ./public/img
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer -d ./ install \
    && composer clear-cache
ENV COMPOSER_ALLOW_SUPERUSER=0

CMD [ "php", "server.php"]