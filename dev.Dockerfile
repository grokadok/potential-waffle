FROM phpswoole/swoole:php8.2
# ENV AUTORELOAD_PROGRAMS: "swoole"
# ENV AUTORELOAD_ANY_FILES: 0
# ENV MYSQL_ADDON_HOST: "mysql"
# ENV MYSQL_ADDON_USER: "user"
# ENV MYSQL_ADDON_PASSWORD: "devonly"
# ENV MYSQL_ADDON_DB: "db"
# ENV ISLOCAL: TRUE

# Copy Chromium binaries from the chromium image
# COPY --from=chromium /usr/bin/chromium-browser /usr/bin/chromium-browser
# COPY --from=chromium /usr/lib/chromium /usr/lib/chromium
# Set environment variable CHROME_PATH
# ENV CHROME_PATH=/usr/bin/chromium-browser
RUN apt update && apt install -y libicu-dev && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install mysqli \
    && docker-php-ext-install intl \
    && echo "memory_limit=256M" > /usr/local/etc/php/conf.d/memory-limit.ini

WORKDIR /var/www/
# ADD https://docs.aws.amazon.com/aws-sdk-php/v3/download/aws.zip ./
# RUN curl -sS https://getcomposer.org/installer | php \
#     && mv composer.phar /usr/local/bin/composer \
#     && chmod +x /usr/local/bin/composer
# RUN composer -d ./ require aws/aws-sdk-php \
#     && composer clear-cache

# RUN mkdir config &&\
#     mkdir app
#     mkdir public
# COPY /server ./
# COPY /config/env.php ./config/
# COPY /app ./app
# COPY /public ./public
# COPY /ressources/fonts ./public/assets/fonts
# ENV COMPOSER_ALLOW_SUPERUSER=1
# RUN composer -d ./ install &&\
#     composer clear-cache
# ENV COMPOSER_ALLOW_SUPERUSER=0