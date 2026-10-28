FROM dunglas/frankenphp:1.13.4-php8.5

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN install-php-extensions zip
WORKDIR /app

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_DATABASE=/data/database.sqlite

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p /data storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

COPY Caddyfile /etc/frankenphp/Caddyfile

EXPOSE 3000 443
CMD ["sh", "-c", "php artisan migrate --force --seed && php artisan optimize && exec frankenphp run --config /etc/frankenphp/Caddyfile"]
