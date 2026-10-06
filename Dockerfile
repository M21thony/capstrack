# CapsTrack app image: nginx + PHP-FPM + queue worker + scheduler in one container.
# Stage 1 builds the React/CSS assets; stage 2 is the runtime.
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.4-fpm AS app
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor git unzip \
        libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libicu-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql gd zip intl mbstring bcmath exif pcntl opcache \
    && apt-get clean

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY deploy/php.ini /usr/local/etc/php/conf.d/99-capstrack.ini
COPY deploy/docker/nginx.conf /etc/nginx/sites-available/default
COPY deploy/docker/supervisord.conf /etc/supervisor/conf.d/capstrack.conf
COPY deploy/docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/capstrack
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction
COPY . .
COPY --from=assets /app/public/build public/build
RUN composer dump-autoload --optimize --no-dev --no-scripts --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
