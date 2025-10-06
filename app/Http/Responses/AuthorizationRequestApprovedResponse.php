<?php

namespace App\Http\Responses;

use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Illuminate\Http\RedirectResponse;

class AuthorizationRequestApprovedResponse implements AuthorizationViewResponse
{
    protected array $parameters = [];

    public function withParameters(array $parameters = []): static
    {
        $this->parameters = $parameters;
        return $this;
    }

    public function toResponse($request)
    {
        // Можно использовать $this->parameters, если нужно
        return new RedirectResponse('/oauth/authorize');
    }
}
