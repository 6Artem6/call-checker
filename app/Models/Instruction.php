<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Instruction extends Model
{
    use HasFactory;

    protected $table = 'instruction';
    protected $primaryKey = 'instruction_id';
    public $timestamps = false;

    /**
     * Атрибуты, которые можно массово заполнить
     */
    protected $fillable = [
        'instruction_text',
        'user_id',
        'theme_id',
        'is_set',
    ];

    /**
     * Дополнительное виртуальное свойство
     */
    public bool $is_set = false;

    /**
     * Массив правил валидации
     */
    public static function rules(): array
    {
        return [
            'instruction_text' => [
                'required',
                'string',
                'max:256',
                'regex:/^[а-яa-z0-9\s\,\.\:\;\!\@\#\%\(\)\[\]\-\+\=\*\?\'\"\/\\\\]+$/iu',
                'unique:instruction,instruction_text,NULL,instruction_id,user_id,' . auth()->id() . ',theme_id,:theme_id',
            ],
            'user_id' => ['required', 'integer'],
            'theme_id' => ['required', 'integer'],
        ];
    }

    /**
     * Определение связи "тема"
     */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'theme_id', 'theme_id');
    }

    /**
     * Определение связи "пользователь"
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Метод для очистки текста инструкции
     */
    public static function cleanText($text)
    {
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text);
        return preg_replace('/[^ёа-яa-z0-9\s,.:;!@#%()\[\]\-+=*?\'"\/\\\\]+/iu', '', $text);
    }

    /**
     * Событие перед валидацией
     */
    protected static function booted(): void
    {
        static::saving(static function ($instruction) {
            $instruction->user_id = auth()->id();
            $instruction->instruction_text = self::cleanText($instruction->instruction_text);
        });
    }

    /**
     * Получение списка инструкций для отображения
     */
    public static function getLoadViewList($themeId): array
    {
        return self::query()
            ->select(['instruction_id as id', 'instruction_text as text'])
            ->where('user_id', auth()->id())
            ->where('theme_id', $themeId)
            ->get()
            ->toArray();
    }

    /**
     * Получение списка инструкций для сохранения
     */
    public static function getLoadSaveList($themeId): Collection
    {
        $list = self::query()
            ->where('user_id', Auth::id())
            ->where('theme_id', $themeId)
            ->get();

        foreach ($list as $instruction) {
            $instruction->is_set = false;
        }

        return $list;
    }
}
