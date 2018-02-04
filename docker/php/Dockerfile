FROM php:7.1-cli

COPY composer.lock composer.json balance config.json operations.json /app/
COPY src /app/src
WORKDIR /app

RUN apt-get update \
    && apt-get install -y unzip libgearman-dev\
    && docker-php-ext-install -j$(nproc) pdo_mysql pcntl sockets sysvsem sysvmsg sysvshm\
    && mkdir -p /tmp/gearman \
    && curl -fsSL 'https://github.com/wcgallego/pecl-gearman/archive/master.zip' -o gearman.zip \
    && unzip gearman.zip -d /tmp/gearman \
    && rm gearman.zip \
    && docker-php-ext-configure /tmp/gearman/pecl-gearman-master \
    && docker-php-ext-install /tmp/gearman/pecl-gearman-master \
    && rm -r /tmp/gearman

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer
RUN composer install --prefer-dist --no-interaction

CMD ["/app/balance", "worker", "-vvv"]
