FROM php:8.4-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpq-dev libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_pgsql mbstring intl zip gd bcmath opcache pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/smartaulaues-uploads.ini
COPY docker/php/development.ini /usr/local/etc/php/conf.d/smartaulaues-development.ini
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --optimize-autoloader \
    && rm -rf /root/.composer/cache
RUN useradd -m -u 1000 app \
    && mkdir -p storage/framework/cache/data storage/framework/views storage/framework/sessions \
    && chown -R app:app /var/www/html
USER app
EXPOSE 8080
# Preserve Compose's DB_HOST/DB_PORT in the PHP HTTP subprocess.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080", "--no-reload"]
