# Production image for App Platform (web / worker / scheduler share this digest).
# DevContainers stay in .devcontainer/ — do not deploy those.

FROM php:8.2-fpm-bookworm AS vendor

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype --with-webp \
    && docker-php-ext-install zip gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative


FROM php:8.2-fpm-bookworm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-jpeg --with-freetype --with-webp \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY docker/production/php.ini /usr/local/etc/php/conf.d/padeliz.ini
COPY docker/production/www.conf /usr/local/etc/php-fpm.d/zz-padeliz.conf
COPY docker/production/nginx.conf /etc/nginx/nginx.conf
COPY docker/production/supervisord.conf /etc/supervisor/conf.d/padeliz.conf
COPY docker/production/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && rm -f /etc/nginx/sites-enabled/default \
    && mkdir -p /var/log/supervisor /run/nginx \
    && chown -R www-data:www-data /var/www/html

COPY --from=vendor --chown=www-data:www-data /app /var/www/html

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/api-docs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

# Bake OpenAPI into the image (gitignored; PRE_DEPLOY filesystem never reaches web).
RUN cp .env.example .env \
    && php artisan key:generate --force \
    && CACHE_DRIVER=array QUEUE_CONNECTION=sync SESSION_DRIVER=array \
        php artisan api:docs:generate-all \
    && test -f storage/api-docs/api-docs-v1.json \
    && rm -f .env \
    && chown -R www-data:www-data storage/api-docs

ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["web"]
