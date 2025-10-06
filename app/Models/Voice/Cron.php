<?php

namespace App\Models\Voice;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Cron extends Model
{
    // Модель не будет работать с базой данных, поэтому убираем свойство $table
    protected $params;

    public function __construct()
    {
        parent::__construct();
        // Загружаем параметры cron из конфигурации Laravel
        $this->params = Config::get('cron');
    }

    /**
     * Проверка секретного ключа
     *
     * @param string|null $secret
     * @return bool
     */
    public function checkSecret(?string $secret = null): bool
    {
        return ($secret == $this->getSecret());
    }

    /**
     * Получение секретного ключа
     *
     * @return string
     */
    protected function getSecret(): string
    {
        return sha1(sha1($this->params['SECRET_KEY']));
    }
}
