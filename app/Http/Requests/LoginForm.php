<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class LoginForm extends FormRequest
{
    public $rememberMe = true;

    /**
     * Правила валидации
     */
    public function rules(): array
    {
        return [
            'user_name' => 'required|string',
            'password' => 'required|string',
            'rememberMe' => 'boolean',
        ];
    }

    /**
     * Переопределение атрибутов (аналог attributeLabels)
     */
    public function attributes(): array
    {
        return [
            'user_name' => __('Логин'),
            'password' => __('Пароль'),
            'rememberMe' => __('Запомнить меня'),
        ];
    }

    public function messages(): array
    {
        return [
            'user_name.required' => 'A title is required',
            'password.required' => 'A message is required',
        ];
    }

    /**
     * Логика входа пользователя
     *
     * @return bool
     */
    public function login(): bool
    {
        $credentials = $this->only('user_name', 'password');
        $remember = $this->boolean('rememberMe', false);

        // Проверка учетных данных
        if (Auth::attempt($credentials, $remember)) {
            $this->session()->regenerate();
            return true;
        }
        // Если аутентификация не удалась

        return false;
    }
}
