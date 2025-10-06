# =======================
# Stage 1: Composer vendor
# =======================
FROM composer:2 AS vendor
WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./

ARG COMPOSER_DEV=0
# dev: COMPOSER_DEV=1 → с dev-зависимостями, prod: 0 → без dev
RUN if [ "$COMPOSER_DEV" = "1" ]; then \
      composer install --prefer-dist --no-interaction --no-ansi --no-progress --no-scripts; \
    else \
      composer install --no-dev --prefer-dist --no-interaction --no-ansi --no-progress --no-scripts; \
    fi

# =======================
# Stage 2: Vite build
# =======================
FROM node:20-alpine AS web
WORKDIR /web
COPY package.json package-lock.json* ./
RUN npm ci
# Нужны ресурсы фронта (vite), поэтому копируем проект
COPY . .
RUN npm run build || mkdir -p /web/dist

# =======================
# Stage 3: PHP base (extensions, composer)
# =======================
FROM php:8.4-cli-alpine AS php-base
RUN apk add --no-cache \
      bash git curl icu-dev oniguruma-dev libzip-dev zip unzip \
      libstdc++ openssl-dev brotli-dev brotli-libs pkgconf \
      $PHPIZE_DEPS inotify-tools
RUN docker-php-ext-install -j"$(nproc)" pdo_mysql intl mbstring zip bcmath pcntl opcache \
 && pecl install swoole inotify \
 && docker-php-ext-enable swoole inotify
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# =======================
# Stage 4: PHP runtime (Octane)
# =======================
FROM php-base AS php-octane
WORKDIR /var/www/html

# Код приложения и зависимости
COPY . /var/www/html
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY --from=web /web/dist /var/www/html/public/build

# Prod OPCache (dev-оверрайды кладём в /usr/local/etc/php-dev через entrypoint)
RUN { \
      echo "opcache.enable=1"; \
      echo "opcache.enable_cli=1"; \
      echo "opcache.memory_consumption=192"; \
      echo "opcache.interned_strings_buffer=16"; \
      echo "opcache.max_accelerated_files=65407"; \
      echo "opcache.validate_timestamps=0"; \
      echo "opcache.jit_buffer_size=128M"; \
    } > /usr/local/etc/php/conf.d/zzz-prod-opcache.ini \
 && mkdir -p /usr/local/etc/php-dev \
 && chmod 0777 /usr/local/etc/php-dev \
 && mkdir -p storage bootstrap/cache

# Entrypoint
COPY ./deploy/app-entrypoint.sh /entry/app-entrypoint.sh
RUN chmod +x /entry/app-entrypoint.sh

ENV LARAVEL_PORT=8000
EXPOSE 8000
ENTRYPOINT ["/entry/app-entrypoint.sh"]

# =======================
# Stage 5: NGINX static (только /public)
# =======================
FROM nginx:alpine AS nginx-static

# Конфиг nginx (отдаёт /public, /storage; остальное проксируй Traefik’ом в php)
#COPY ./deploy/nginx/static.conf /etc/nginx/conf.d/default.conf

# Копируем только статический веб-контент
# Сначала всё из public/, затем билд Vite поверх
COPY ./public /var/www/html/public
COPY --from=web /web/dist /var/www/html/public/build

# public/storage монтируй томом в compose (runtime-аплоады)
