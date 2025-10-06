#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html
umask 0002

rm -f bootstrap/cache/config.php bootstrap/cache/packages.php 2>/dev/null || true

# === те же тома уже смонтированы; проверим базовые папки ===
install -d -m 2775 -o www-data -g www-data storage/logs storage/framework storage/framework/cache \
  storage/framework/sessions storage/framework/views storage/framework/testing bootstrap/cache 2>/dev/null || true
: > storage/logs/laravel.log 2>/dev/null || true

# === Dusk guard (опционально) ===
if [ ! -d "vendor/laravel/dusk" ]; then
  export APP_ENV=production
  export APP_DEBUG=false
  echo ">> Dusk not present → forcing APP_ENV=production for queue"
fi

# === vendor seed на случай пустого тома ===
if [ ! -f "vendor/autoload.php" ]; then
  if [ -d "/opt/app_seed/vendor" ]; then
    echo "==> Seeding empty vendor volume from image (queue)"
    mkdir -p vendor && cp -R /opt/app_seed/vendor/. vendor/
  else
    echo "==> /opt/app_seed/vendor not found; composer install (queue)"
    composer install --prefer-dist --no-interaction --no-progress || true
  fi
fi

# === pre-commands (через stderr) ===
LOG_CHANNEL=stderr php artisan optimize:clear || true

# Passport permissions (на случай запуска воркера раньше веба)
if [ -d "storage/oauth" ]; then
  # владелец — www-data (не критично, но логично)
  chown -R www-data:www-data storage/oauth 2>/dev/null || true

  # приватные ключи: 660 (или 600, если воркер и веб НЕ под одной учёткой)
  find storage/oauth -type f \( -name "*private*.key" -o -name "oauth-private.key" -o -name "private.key" \) \
       -exec chmod 660 {} \; 2>/dev/null || true

  # публичные — 644/664, на ваше усмотрение (обычно 644 достаточно)
  find storage/oauth -type f \( -name "*public*.key" -o -name "oauth-public.key" -o -name "public.key" \) \
       -exec chmod 644 {} \; 2>/dev/null || true
fi

TIMEOUT="${QUEUE_TIMEOUT:-300}"
TRIES="${QUEUE_TRIES:-3}"
DEV_AUTO_RELOAD="${DEV_AUTO_RELOAD:-false}"

# DEV авто-ребут воркера при изменении кода (inotify-tools должны быть в образе)
if [ "$DEV_AUTO_RELOAD" = "true" ]; then
  echo ">> DEV auto-reload enabled: watching app/, routes/, config/, resources/"
  ( while inotifywait -r -e modify,create,delete,move app routes config resources; do
      echo ">> Code change detected -> queue:restart"
      php artisan queue:restart || true
    done ) &
fi

echo ">> Starting queue:work (daemon, timeout=${TIMEOUT}, tries=${TRIES})"
exec php artisan queue:work --daemon --timeout="${TIMEOUT}" --tries="${TRIES}"
