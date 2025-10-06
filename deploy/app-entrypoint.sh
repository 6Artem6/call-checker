#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html
umask 0002  # новые файлы -> 0664, директории -> 0775 (если ФС поддерживает)

APP_ENV_DEFAULT="${APP_ENV:-production}"
PORT="${LARAVEL_PORT:-8000}"
DEV_INI_DIR="/usr/local/etc/php-dev"
DEV_AUTO_RELOAD="${DEV_AUTO_RELOAD:-true}"

rm -f bootstrap/cache/config.php bootstrap/cache/packages.php 2>/dev/null || true

# === Одноразовая подготовка томов (мы root) ===
# Директории с правильными правами, владельцем и setgid сразу:
install -d -m 2775 -o www-data -g www-data storage \
  storage/logs storage/framework storage/framework/cache storage/framework/sessions \
  storage/framework/views storage/framework/testing storage/app \
  public/storage bootstrap/bootstrap bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data public/storage bootstrap/cache storage 2>/dev/null || true
: > storage/logs/laravel.log 2>/dev/null || true

# === Seed vendor-тома, если пустой ===
if [ ! -f "vendor/autoload.php" ]; then
  if [ -d "/opt/app_seed/vendor" ]; then
    echo "==> Seeding empty vendor volume from image"
    mkdir -p vendor && cp -R /opt/app_seed/vendor/. vendor/
  else
    echo "==> /opt/app_seed/vendor not found; running composer install (one-time)"
    # dev/prod ветка
    if [ "${APP_ENV:-production}" = "local" ] || [ "${APP_ENV:-production}" = "development" ]; then
      composer install --prefer-dist --no-interaction --no-progress
    else
      composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
    fi
  fi
fi

echo "==> APP_ENV=${APP_ENV_DEFAULT}  PORT=${PORT}"

# ----- DEV PHP INI (opcache off для hot-reload) -----
if [[ "$APP_ENV_DEFAULT" == "local" || "$APP_ENV_DEFAULT" == "development" ]]; then
  mkdir -p "$DEV_INI_DIR" || true
  cat > "${DEV_INI_DIR}/zz-dev.ini" <<'INI'
opcache.enable=0
opcache.enable_cli=0
opcache.validate_timestamps=1
opcache.revalidate_freq=0
INI
  echo "==> Applied dev PHP overrides in ${DEV_INI_DIR}/zz-dev.ini"
fi

# ----- APP_KEY: не трогаем .env, если он RO -----
if [[ -z "${APP_KEY:-}" ]]; then
  if [[ -w ".env" ]]; then
    LOG_CHANNEL=stderr php artisan key:generate --force || true
  else
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    echo ">> .env read-only; using ephemeral APP_KEY for this process"
  fi
fi

# ----- Предварительные команды (в stderr) -----
LOG_CHANNEL=stderr php artisan optimize:clear || true

# storage:link больше не нужен (public disk -> public/storage).
if [[ "${RUN_STORAGE_LINK:-0}" == "1" ]]; then
  if ! LOG_CHANNEL=stderr php artisan storage:link --no-ansi 2>/dev/null; then
    echo "!! storage:link failed; skipped (using public/storage disk)"
  fi
fi

# Passport (если установлен) — через stderr, без chmod/chown
if LOG_CHANNEL=stderr php artisan list --raw | grep -q '^passport:'; then
  if [[ ! -f "storage/oauth/private.key" && ! -f "storage/oauth/oauth-private.key" ]]; then
    LOG_CHANNEL=stderr php artisan passport:keys --force || true
  fi
  [[ -f storage/oauth/private.key && ! -f storage/oauth/oauth-private.key ]] && \
    cp -f storage/oauth/private.key storage/oauth/oauth-private.key || true
  [[ -f storage/oauth/public.key && ! -f storage/oauth/oauth-public.key ]] && \
    cp -f storage/oauth/public.key storage/oauth/oauth-public.key || true
    
  # владелец — www-data (не критично, но логично)
  chown -R www-data:www-data storage/oauth 2>/dev/null || true

  # приватные ключи: 660 (или 600, если воркер и веб НЕ под одной учёткой)
  find storage/oauth -type f \( -name "*private*.key" -o -name "oauth-private.key" -o -name "private.key" \) \
       -exec chmod 660 {} \; 2>/dev/null || true

  # публичные — 644/664, на ваше усмотрение (обычно 644 достаточно)
  find storage/oauth -type f \( -name "*public*.key" -o -name "oauth-public.key" -o -name "public.key" \) \
       -exec chmod 644 {} \; 2>/dev/null || true
fi

# Кэши — только в проде (через stderr)
if [[ "$APP_ENV_DEFAULT" != "local" && "$APP_ENV_DEFAULT" != "development" ]]; then
  LOG_CHANNEL=stderr php artisan config:cache --no-ansi || true
  LOG_CHANNEL=stderr php artisan route:cache  --no-ansi || true
  LOG_CHANNEL=stderr php artisan view:cache   --no-ansi || true
fi

# ----- START -----

start_external_watcher() {
  # внешний вотчер: inotifywait -> octane:reload
  if command -v inotifywait >/dev/null 2>&1; then
    echo ">> External watcher: inotifywait on app/, routes/, config/, resources/"
    (
      # немножко debounce, чтобы не спамить reload
      while inotifywait -r -e modify,create,delete,move app routes config resources; do
        echo ">> Change detected -> php artisan octane:reload"
        php artisan octane:reload || true
        # маленькая задержка, чтобы склеивать «залпы» изменений
        sleep 0.25
      done
    ) &
  else
    echo "!! inotifywait not found; external watcher disabled"
  fi
}

if php artisan list --raw | grep -q '^octane:'; then
  echo "==> Starting Octane on 0.0.0.0:${PORT}"

  if [[ "$APP_ENV_DEFAULT" == "local" || "$APP_ENV_DEFAULT" == "development" ]]; then
    if [[ "$DEV_AUTO_RELOAD" == "true" ]]; then
      start_external_watcher
    fi
  fi

  # ВАЖНО: запускаем БЕЗ --watch
  exec php artisan octane:start \
    --server=swoole \
    --host=0.0.0.0 \
    --port="${PORT}" \
    --workers="${OCTANE_WORKERS:-4}" \
    --task-workers="${OCTANE_TASK_WORKERS:-4}" \
    --max-requests="${OCTANE_MAX_REQUESTS:-1000}"
else
  echo "!! Octane not found → php artisan serve ${PORT}"
  exec php artisan serve --host=0.0.0.0 --port="${PORT}"
fi
