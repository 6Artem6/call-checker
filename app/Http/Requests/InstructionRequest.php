<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class InstructionRequest extends FormRequest
{
    /**
     * Определите, может ли пользователь выполнять этот запрос.
     */
    public function authorize(): bool
    {
        // Здесь можно настроить авторизацию (например, только для авторизованных пользователей).
        return Auth::check();
    }

    /**
     * Верните правила валидации для запроса.
     */
    public function rules(): array
    {
        return [
            'instruction_text' => [
                'required',
                'string',
                'max:256',
                'regex:/^[а-яa-z0-9\s\,\.\:\;\!\@\#\%\(\)\[\]\-\+\=\*\?\'\"\/\\\\]+$/iu',
                'unique:instruction,instruction_text,NULL,instruction_id,user_id,' . Auth::id() . ',theme_id,' . $this->theme_id
            ],
            'theme_id' => ['required', 'integer', 'exists:theme,theme_id'],
        ];
    }

    /**
     * Сообщения об ошибках валидации.
     */
    public function messages(): array
    {
        return [
            'instruction_text.required' => 'Текст инструкции обязателен.',
            'instruction_text.unique' => 'Такая инструкция уже существует.',
            'instruction_text.max' => 'Текст инструкции не может превышать 256 символов.',
            'instruction_text.regex' => 'Текст инструкции содержит недопустимые символы.',
            'theme_id.required' => 'Тема обязательна.',
        ];
    }
}
