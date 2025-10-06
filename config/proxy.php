<?php

return [
    // Куда проксировать
    'web_base' => env('PROXY_TARGET_BASE', ''),        // напр. http://frontend:3000
    'api_base' => env('PROXY_TARGET_BASE_API', ''),    // если нужно отдельное

    // WEB: проксируем всё, что НЕ начинается с /api
    'web_paths' => [
        '#^/(?!api(?:/|$)).*$#i',
    ],

    // API: целиком /api
    'api_paths' => [
        '#^/api(?:/.*)?$#i',
    ],

    // Что НИКОГДА не проксировать (отдаёт сам PHP/контейнер/Traefik)
    'exclude' => [
        '#^/up$#i',                 // healthcheck
        '#^/favicon\.ico$#i',
        '#^/robots\.txt$#i',
        '#^/(?:assets|build|storage|vendor)/#i',  // статика/публичный сторидж
        // добавь сюда любые эндпоинты, которые должны остаться в Laravel:
        // '#^/oauth/#i',
        // '#^/payment/webhook$#i',
    ],

    // Сеттинги запроса
    'timeout'          => (float) env('PROXY_TIMEOUT', 15),
    'forward_headers'  => true,                        // прокидывать заголовки клиента
    'follow_redirects' => true,                        // сервер сам следует 3xx целевого сайта

    'verify_tls' => (bool) env('PROXY_VERIFY_TLS', true),                // true по умолчанию
    'verify_tls_cafile' => env('PROXY_VERIFY_TLS_CAFILE', null),        // путь к кастомному CA (если есть)
    'insecure_hosts' => array_filter(array_map('trim', explode(',', env('PROXY_INSECURE_HOSTS', '')))),

];
