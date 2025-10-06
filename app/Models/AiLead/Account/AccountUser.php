<?php

namespace App\Models\AiLead\Account;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;


class AccountUser extends Authenticatable
{
    use Notifiable, HasFactory, HasApiTokens;

    protected $table = 'account_user';
    protected $primaryKey = 'user_id';
    public $timestamps = false;

    /**
     * Массов заполняемые атрибуты
     */
    protected $fillable = [
        'email',
        'password',
        'auth_key',
        'access_token',
        'refresh_token',
        'expires_in',
        'status',
    ];

    /**
     * Атрибуты, которые должны быть скрыты для массивов
     */
    protected $hidden = [
        'password',
        'auth_key',
        'access_token',
        'refresh_token',
        'expires_in',
    ];

    /**
     * Атрибуты, которые должны быть преобразованы в нативные типы
     */
    protected $casts = [
        'user_id' => 'integer',
        'email' => 'string',
        'password' => 'string',
        'auth_key' => 'string',
        'access_token' => 'string',
        'status' => 'integer',
    ];

    public function oauth2()
    {
        return $this->hasOne(AccountOAuth2::class, 'user_id', 'user_id');
    }

    public static function findForPassport($username)
    {
        return self::where('email', $username)->first();
    }

}
