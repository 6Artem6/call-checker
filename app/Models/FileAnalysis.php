<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class FileAnalysis extends Model
{
    protected $table = 'file_analysis'; // Имя таблицы
    protected $primaryKey = 'file_id'; // Первичный ключ
    public $incrementing = true; // Если primaryKey не автоинкремент
    public $timestamps = false;
    protected $fillable = ['analysis_data']; // Разрешенные для массового заполнения

    /**
     * Правила валидации.
     */
    public static function rules(): array
    {
        return [
            'file_id' => ['unique', 'required', 'integer'],
            'analysis_data' => ['required', 'string'],
        ];
    }

    /**
     * Связь один-к-одному с моделью File.
     */
    public function file()
    {
        return $this->hasOne(File::class, 'file_id', 'file_id');
    }

    /**
     * Получение записи с учетом текущего пользователя.
     *
     * @param int $id
     * @return FileAnalysis|null
     */
    public static function getViewRecord(int $id)
    {
        return self::query()
            ->where('file_analysis.file_id', $id)
            ->whereHas('file.request', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->with('file.request')
            ->first();
    }

    /**
     * Обработка анализа для вывода текста.
     *
     * @return string
     */
    public function getText(): string
    {
        $text = '';
        $data = json_decode($this->analysis_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Если данные не JSON
            $text = nl2br($this->analysis_data);
        } else {
            // Если данные JSON
            if (array_key_exists('speakers', $data)) {
                $text .= '<ul class="list-group list-group-flush">';
                foreach ($data['speakers'] as $number => $name) {
                    $text .= '<li class="list-group-item">' . $number . ' - ' . $name . '</li>';
                }
                $text .= '</ul>';
            }

            if (array_key_exists('analysis', $data)) {
                if (is_array($data['analysis'])) {
                    $text .= '<table class="table">';
                    foreach ($data['analysis'] as $record) {
                        $icon = '';
                        $details = '';

                        if ($record['result'] === 'Да') {
                            $icon = '<i class="bg-success rounded-pill text-white bi bi-check-lg"></i>';
                            $details = empty($record['details'])
                                ? '<i>Проверка пройдена</i>'
                                : $record['details'];
                        } elseif ($record['result'] === 'Нет') {
                            $icon = '<i class="bg-secondary rounded-pill text-white bi bi-question-lg"></i>';
                            $details = '<i>Данных нет</i>';
                        } elseif ($record['result'] === 'Ошибка') {
                            $icon = '<i class="bg-danger rounded-pill text-white bi bi-x-lg"></i>';
                            $details = '<b>Ошибка оператора</b>: ' . $record['details'];
                        }

                        $text .= '<tr>';
                        $text .= '<td>' . $icon . '</td>';
                        $text .= '<td>' . $record['check'] . '</td>';
                        $text .= '<td>' . $details . '</td>';
                        $text .= '</tr>';
                    }
                    $text .= '</table>';
                }
            }
        }

        return $text;
    }
}
