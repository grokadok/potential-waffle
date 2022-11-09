FROM phpswoole/swoole:php8.1
WORKDIR /var/www/
RUN docker-php-ext-install mysqli
RUN mkdir public &&\
    mkdir app &&\
    mkdir config