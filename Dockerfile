FROM phpswoole/swoole:php8.1-alpine
WORKDIR /var/www

RUN apk add --no-cache icu-libs icu-dev \
    && docker-php-ext-install intl \
    && apk del icu-dev \
    && apk add --no-cache --virtual .locale-build-deps gettext \
    && cp /usr/bin/envsubst /usr/local/bin/envsubst \
    && apk del .locale-build-deps \
    && rm -rf /var/cache/apk/*

# RUN apk add --no-cache icu-dev
# RUN docker-php-ext-install intl
RUN docker-php-ext-install mysqli
RUN mkdir app
RUN mkdir app/model
RUN mkdir app/jwt
RUN mkdir public
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