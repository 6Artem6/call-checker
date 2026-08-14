# Call Checker

Call Checker — веб-приложение для анализа звонков пользователей и ведения специализированных диалогов с покупателями в CRM с автоматическим продвижением по воронкам продаж.

Проект сочетает сбор и обработку голосовых и событийных данных звонков, визуализацию и аналитику, а также инструменты для автоматических и ручных диалогов агентов/ботов внутри CRM.

## Ключевые возможности
- **Интеграция с amoCRM:** Автоматическое ведение и обновление сделок, передача контекста диалогов и истории коммуникаций.
- **AI-анализ интентов:** Контекстный анализ сообщений и выявление намерений клиентов для сценариев общения.
- **Автоматизация воронок:** Перевод сделок по этапам воронки продаж на основе триггеров и результатов аналитики.
- **Асинхронные события:** Приём и надежная обработка входящих вебхуков внешних сервисов через очереди (Redis/Queues).
- **История & Мониторинг:** Полный аудит шагов воронки, веб-интерфейс для настройки правил и модерации диалогов.

## Технологии
- **Бэкенд:** PHP 8.2+, Laravel 12 (REST API, Queues, Webhooks)
- **Интеграции:** amoCRM API, Webhooks
- **Фронтенд:** Vue.js, HTML, Blade
- **База данных & Кеш:** MySQL, Redis (очереди и кеширование)
- **Инфраструктура:** Docker, Docker Compose, Nginx

## Требования
- PHP 8.2+
- Composer 2.x
- Node.js 18+ и npm
- PostgreSQL / MySQL & Redis
- Docker & Docker Compose

## Быстрый старт (локально)
1. Клонировать репозиторий:
   git clone https://github.com/6Artem6/call-checker.git
   cd call-checker

2. Установить зависимости:
   composer install
   npm install

3. Создать файл окружения и настроить его:
   cp .env.example .env
   Отредактируйте `.env` — подключение к БД, ключи и т.д.

4. Выполнить миграции и сиды (если проект на Laravel):
   php artisan migrate --seed

5. Собрать фронтенд-ассеты:
   npm run dev

6. Запустить локальный сервер:
   php artisan serve
   Открыть: http://localhost:8000

## Быстрый старт (Docker)
1. Создать/отредактировать .env / .env.docker
2. Запустить контейнеры:
   docker-compose up -d --build
3. Установить зависимости и выполнить миграции внутри контейнера:
   docker-compose exec app composer install
   docker-compose exec app php artisan migrate --seed
   docker-compose exec node npm install && npm run production

## Переменные окружения (пример)
- APP_NAME=CallChecker
- APP_ENV=local
- APP_KEY=base64:...
- APP_URL=http://localhost
- DB_CONNECTION=mysql
- DB_HOST=127.0.0.1
- DB_PORT=3306
- DB_DATABASE=call_checker
- DB_USERNAME=user
- DB_PASSWORD=secret
- TELEPHONY_PROVIDER_API_KEY=...
- CRM_API_URL=...
- CRM_API_KEY=...

Добавьте переменные для интеграции с телефонными провайдерами и CRM.

## API — примеры
Приём события звонка (пример):
POST /api/calls
Content-Type: application/json
Body:
{
  "call_id": "uuid-or-string",
  "caller": "+71234567890",
  "callee": "+79876543210",
  "status": "completed",
  "started_at": "2026-08-14T12:00:00Z",
  "duration": 120,
  "recording_url": "https://...",
  "metadata": {...}
}

Пример curl:
curl -X POST "http://localhost/api/calls" \
  -H "Content-Type: application/json" \
  -d '{"caller":"+71234567890","callee":"+79876543210","started_at":"2026-08-14T12:00:00Z","duration":120}'

Webhook для интеграции с CRM:
- События: lead_created, lead_moved, call_received, call_result
- Приложение должно уметь обрабатывать входящие webhook и обновлять состояние воронки.

## Интерфейс и работа с воронками
- Настройка воронок продаж и шагов (Admin UI).
- Правила переходов: вручную, по результатам звонка, по меткам качества разговора.
- История переходов и возможность отката/комментариев для оператора.

## Тестирование
- PHP: composer test или vendor/bin/phpunit
- JS: npm test
Рекомендуется добавление CI (GitHub Actions) для автоматического запуска тестов при PR.

## Логирование и мониторинг
- Логи: storage/logs/ (или указанный в конфигурации)
- Рекомендации: интеграция Sentry или аналогов для мониторинга ошибок, метрики Prometheus/Grafana для метрик.

## Развёртывание
- VPS: nginx + php-fpm, настройка SSL, очередей (supervisor)
- Docker: использовать Dockerfile и docker-compose или Kubernetes для масштабирования
- CI/CD: настроить сборку, тесты и развертывание из main ветки

## Вклад
1. Форкнуть репозиторий
2. Создать ветку feature/имя
3. Сделать изменения и покрыть тестами
4. Открыть Pull Request с описанием изменений и инструкцией по тестированию

## Roadmap / Возможные улучшения
- Добавление графовых сценариев валидации диалогов (LLM Guardrails).
- Расширение модуля аналитики с выгрузкой отчетов в XLSX/PDF.
- Поддержка дополнительный каналов (Telegram/WhatsApp Bot API) помимо телефонии.

## Лицензия
Исходный код доступен для ознакомления, локального запуска, проведения Code Review и тестирования. Коммерческое использование, продажа или использование в коммерческих продуктах без согласования с автором запрещены. См. файл [LICENSE](LICENSE) для деталей.
