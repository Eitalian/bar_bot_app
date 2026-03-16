FROM php:8.5-cli

RUN mv "$PHP_INI_DIR"/php.ini-development "$PHP_INI_DIR"/php.ini
COPY docker/php/ /usr/local/etc/php/conf.d

# Системные зависимости
RUN apt update \
    && apt install -y --no-install-recommends \
        git  \
        curl  \
        unzip  \
        libpq-dev  \
        libzip-dev \
        nodejs \
        npm \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# PHP расширения
RUN docker-php-ext-install -j"$(nproc)" zip sockets pcntl pdo pdo_pgsql pgsql
RUN pecl install redis && docker-php-ext-enable redis
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# RoadRunner бинарник
RUN curl -L https://github.com/roadrunner-server/roadrunner/releases/download/v2024.1.5/roadrunner-2024.1.5-linux-amd64.tar.gz \
    | tar -xz -C /usr/local/bin --strip-components=1 roadrunner-2024.1.5-linux-amd64/rr

COPY . /var/www
WORKDIR /var/www

RUN npm install --save-dev chokidar

# Права
RUN chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

EXPOSE 8080
