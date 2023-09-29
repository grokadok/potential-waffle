FROM phpswoole/swoole:php8.1-alpine
WORKDIR /var/www

# RUN apk add --no-cache icu-libs icu-dev \
    # && docker-php-ext-install intl \
    # && apk del icu-dev \
    # && apk add --no-cache --virtual .locale-build-deps gettext \
    # && cp /usr/bin/envsubst /usr/local/bin/envsubst \
    # && apk del .locale-build-deps \
    # && rm -rf /var/cache/apk/*

RUN apk add --no-cache icu-libs icu-dev \
    && apk add --no-cache --virtual .locale-build-deps wget \
    && wget -q -O /etc/apk/keys/sgerrand.rsa.pub https://alpine-pkgs.sgerrand.com/sgerrand.rsa.pub \
    && wget -q https://github.com/sgerrand/alpine-pkg-glibc/releases/download/2.34-r0/glibc-2.34-r0.apk \
    && apk add glibc-2.34-r0.apk \
    && docker-php-ext-install intl \
    && apk del icu-dev .locale-build-deps \
    && rm -rf /var/cache/apk/*
# Set the intl extension configuration
RUN echo "extension=intl.so" > /usr/local/etc/php/conf.d/intl.ini \
    && echo "intl.default_locale = fr_FR.UTF-8" >> /usr/local/etc/php/conf.d/intl.ini

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