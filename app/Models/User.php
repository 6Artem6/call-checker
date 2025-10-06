<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as AuthenticatableModel;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\HasApiTokens;

class User extends AuthenticatableModel
{
    use Notifiable, HasFactory, HasApiTokens;

    protected $table = 'user'; // Указание таблицы, если она отличается от стандартной
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

    /**
     * Установка связи с моделью UserTheme
     */
    public function userThemes()
    {
        return $this->hasMany(UserTheme::class, 'user_id', 'user_id');
    }

    /**
     * Установка связи с моделью Theme через UserTheme
     */
    public function themes()
    {
        return $this->belongsToMany(Theme::class, 'user_theme', 'user_id', 'theme_id');
    }

    /**
     * Проверка пароля
     */
    public function validatePassword(string $password): bool
    {
        return Hash::check($password, $this->password);
    }

    /**
     * Генерация случайного пароля
     */
    public static function createRandomPassword(): string
    {
        $alphabet = array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9'));
        $password = [];
        $alphaLength = count($alphabet) - 1;

        for ($i = 0; $i < 10; $i++) {
            $n = rand(0, $alphaLength);
            $password[] = $alphabet[$n];
        }

        return implode('', $password);
    }

    /**
     * Хук при сохранении данных
     */
    public function saveData()
    {
        $this->status = 1;
        $this->password = bcrypt(self::createRandomPassword());
        $this->save();
    }
}
