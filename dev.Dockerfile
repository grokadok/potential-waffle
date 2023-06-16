FROM phpswoole/swoole:php8.2
# ENV AUTORELOAD_PROGRAMS: "swoole"
# ENV AUTORELOAD_ANY_FILES: 0
# ENV MYSQL_ADDON_HOST: "mysql"
# ENV MYSQL_ADDON_USER: "user"
# ENV MYSQL_ADDON_PASSWORD: "devonly"
# ENV MYSQL_ADDON_DB: "db"
# ENV ISLOCAL: TRUE
WORKDIR /var/www/
RUN docker-php-ext-install mysqli
# ADD https://docs.aws.amazon.com/aws-sdk-php/v3/download/aws.zip ./
# RUN curl -sS https://getcomposer.org/installer | php \
#     && mv composer.phar /usr/local/bin/composer \
#     && chmod +x /usr/local/bin/composer
# RUN composer -d ./ require aws/aws-sdk-php \
#     && composer clear-cache

RUN mkdir config &&\
    mkdir app
    # mkdir public
# COPY /server ./
# COPY /config/env.php ./config/
# COPY /app ./app
# COPY /public ./public
# COPY /ressources/fonts ./public/assets/fonts
# ENV COMPOSER_ALLOW_SUPERUSER=1
# RUN composer -d ./ install &&\
#     composer clear-cache
# ENV COMPOSER_ALLOW_SUPERUSER=0