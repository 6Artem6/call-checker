<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as AuthenticatableModel;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;


class AccountUser extends AuthenticatableModel
{
    use Notifiable, HasFactory, HasApiTokens;

    protected $table = 'account_user'; // Указание таблицы, если она отличается от стандартной
    protected $primaryKey = 'user_id'; // Указание первичного ключа
    public $timestamps = false; // Удалите, если в таблице есть поля created_at/updated_at

    /**
     * Массов заполняемые атрибуты
     */
    protected $fillable = [
        'user_name',
        'password',
        'auth_key',
        'access_token',
        'status',
    ];

    /**
     * Атрибуты, которые должны быть скрыты для массивов (например, при преобразовании в JSON)
     */
    protected $hidden = [
        'password',
        'auth_key',
        'access_token',
    ];

    /**
     * Атрибуты, которые должны быть преобразованы в нативные типы
     */
    protected $casts = [
        'user_id' => 'integer',
        'user_name' => 'string',
        'password' => 'string',
        'auth_key' => 'string',
        'access_token' => 'string',
        'status' => 'integer',
    ];

}
