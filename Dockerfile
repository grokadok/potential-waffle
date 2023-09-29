# FROM phpswoole/swoole:php8.1
FROM phpswoole/swoole:php8.2-alpine

# RUN apt update && apt install -y libicu-dev && rm -rf /var/lib/apt/lists/* &&\
#     docker-php-ext-install mysqli &&\
#     docker-php-ext-install intl

# ENV MUSL_LOCALE_DEPS cmake make musl-dev gcc gettext-dev libintl
# ENV MUSL_LOCPATH /usr/share/i18n/locales/musl

# RUN apk add --no-cache \
#     $MUSL_LOCALE_DEPS \
#     && wget https://gitlab.com/rilian-la-te/musl-locales/-/archive/master/musl-locales-master.zip \
#     && unzip musl-locales-master.zip \
#       && cd musl-locales-master \
#       && cmake -DLOCALE_PROFILE=OFF -D CMAKE_INSTALL_PREFIX:PATH=/usr . && make && make install \
#       && cd .. && rm -r musl-locales-master

# ENV MUSL_LOCPATH=/usr/local/share/i18n/locales/musl
# RUN apk add --update git cmake make musl-dev gcc gettext-dev libintl
# RUN cd /tmp && git clone https://gitlab.com/rilian-la-te/musl-locales.git
# RUN cd /tmp/musl-locales && cmake . && make && make install

# RUN apk --no-cache add \
#     musl-locales \
#     musl-locales-lang

RUN apk update && apk add --no-cache libintl icu icu-dev icu-data-full musl-locales musl-locales-lang

# RUN apk add --no-cache icu-dev
# RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl
RUN docker-php-ext-install mysqli

WORKDIR /var/www

# RUN apk add --no-cache icu-libs icu-dev \
    # && docker-php-ext-install intl \
    # && apk del icu-dev \
    # && apk add --no-cache --virtual .locale-build-deps gettext \
    # && cp /usr/bin/envsubst /usr/local/bin/envsubst \
    # && apk del .locale-build-deps \
    # && rm -rf /var/cache/apk/*

# RUN apk add --no-cache icu-libs icu-dev
# RUN apk add --no-cache --virtual .locale-build-deps wget
# RUN wget -q -O /etc/apk/keys/sgerrand.rsa.pub https://alpine-pkgs.sgerrand.com/sgerrand.rsa.pub
# RUN wget -q https://github.com/sgerrand/alpine-pkg-glibc/releases/download/2.34-r0/glibc-2.34-r0.apk
# RUN apk add --force glibc-2.34-r0.apk
# RUN docker-php-ext-install intl
# RUN apk del icu-dev .locale-build-deps
# RUN rm -rf /var/cache/apk/*
# # Set the intl extension configuration
# RUN echo "extension=intl.so" > /usr/local/etc/php/conf.d/intl.ini \
#     && echo "intl.default_locale = fr_FR.UTF-8" >> /usr/local/etc/php/conf.d/intl.ini

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
RUN composer update \
    && composer -d ./ install \
    && composer clear-cache
ENV COMPOSER_ALLOW_SUPERUSER=0

CMD [ "php", "server.php"]