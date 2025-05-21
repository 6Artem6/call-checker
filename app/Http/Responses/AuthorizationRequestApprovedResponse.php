<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;

class AuthorizationRequestApprovedResponse
{
    public function toResponse($request)
    {
        // Возвращаем автоматический редирект на страницу /token для получения токенов
        return new RedirectResponse(route('passport.token'));
    }
}
