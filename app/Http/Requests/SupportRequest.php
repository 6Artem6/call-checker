<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'   => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'   => 'Поле email обязательно.',
            'email.email'      => 'Некорректный email.',
            'subject.required' => 'Укажите тему обращения.',
            'message.required' => 'Напишите сообщение.',
        ];
    }
}
