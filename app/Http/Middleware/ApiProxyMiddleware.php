<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;


class ApiProxyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Проксируем только /api/*
        if ($request->is('api/*')) {
            $target = rtrim(env('SELENIUM_API_URL'), '/');

            // Заголовки без hop-by-hop (их нельзя проксировать)
            $headers = collect($request->headers->all())
                ->map(fn($v) => is_array($v) ? implode(', ', $v) : $v)
                ->except([
                    'host',
                    'content-length',
                    'accept-encoding',
                    'connection',
                ])
                ->toArray();

            // Отправляем запрос
            $response = Http::withHeaders($headers)
                ->send(
                    $request->method(),
                    $target . '/' . ltrim($request->path(), '/'),
                    [
                        'query' => $request->query(),          // GET-параметры
                        'body'  => $request->getContent(),     // Сырые данные (JSON, form-data и т. д.)
                    ]
                );

            // Возвращаем ответ клиенту
            return response($response->body(), $response->status())
                ->withHeaders($this->filterResponseHeaders($response->headers()));
        }

        return $next($request);
    }

    private function filterResponseHeaders(array $headers): array
    {
        // Убираем hop-by-hop заголовки, которые нельзя пробрасывать
        $blocked = [
            'transfer-encoding',
            'content-encoding',
            'connection',
            'keep-alive',
            'proxy-authenticate',
            'proxy-authorization',
            'te',
            'trailers',
            'upgrade',
        ];

        return collect($headers)
            ->map(fn($v) => is_array($v) ? implode(', ', $v) : $v)
            ->except($blocked)
            ->toArray();
    }
}
