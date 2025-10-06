<?php

namespace App\Models\AiLead\Chat;

use App\Models\AiLead\Gpt\AccountGPTSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\OpenAI\{OpenAIAnalysisService, OpenAIArbitrationService};
use App\Services\SeleniumService;
use App\Services\PuppeteerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AiLead\Account\AccountOAuth2;
use App\Models\AiLead\Chat\Abstracts\BaseChatMessage;
use Throwable;


/**
 * @property integer $contact_id
 * @property integer $chat_id
 * @property integer $lead_id
 * @property string $domain
 * @property string $text
 * @property string $origin
 * @property string $has_reply
 * @property string $reply_id
 *
 * @mixin Builder
 */
class ChatMessage extends BaseChatMessage
{
    protected $table = 'chat_message';
    protected $primaryKey = 'id';
    protected $fillable = [
        'contact_id',
        'chat_id',
        'lead_id',
        'text',
        'domain',
        'origin',
        'has_reply',
        'reply_id',
    ];
    protected $hidden = [
        'id'
    ];
    protected $casts = [
        'id' => 'integer',
        'contact_id' => 'string',
        'chat_id' => 'string',
        'lead_id' => 'integer',
        'domain' => 'string',
        'text' => 'string',
        'origin' => 'string',
        'has_reply' => 'boolean',
        'reply_id' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->created_at) {
                $model->created_at = now();
            }
        });
    }

    private function analysis(string $content): array
    {
        $service = new OpenAIAnalysisService;
        $service->setSetting($this->setting);
        $service->setThreadByUserChat($this);

        return $service->chatFunction($content);
    }

    /**
     * Запускает арбитраж диалога через OpenAIArbitrationService
     *
     * @param array $messages  // массив [{role, content}]
     * @param string $condition  // условие завершения диалога
     * @return bool
     */
    private function arbitration(array $messages, string $condition): bool
    {
        $service = new OpenAIArbitrationService();
        $service->setSetting($this->setting);

        [$verdict, $missing, $notes] = $service->arbitrateCondition($condition, $messages);

        Log::channel('amocrm')->info('Arbitration result', [
            'verdict' => $verdict,
            'missing' => $missing,
            'notes'   => $notes,
        ]);

        return $verdict;
    }

    /**
     * Пример использования внутри ChatMessage.
     * Например, после сохранения сообщения или при проверке has_reply/reply_id.
     */
    public function checkDialogCompletion(): bool
    {
        $thread = UserChatThread::where('domain', $this->domain)
            ->where('lead_id', $this->lead_id)
            ->first();

        if (!$thread) {
            return false;
        }

        // Собираем все сообщения диалога
        $dialog = ChatMessage::where('domain', $this->domain)
            ->where('lead_id', $this->lead_id)
            ->orderBy('id')
            ->get()
            ->map(function ($msg) {
                return [
                    'role' => $msg->reply_id ? 'bot' : 'user',
                    'content' => $msg->text,
                ];
            })
            ->toArray();

        $condition = $this->setting->completion_condition ?? 'Проверь, можно ли завершать диалог';

        return $this->arbitration($dialog, $condition);
    }

    public function saveAnswerAnalysis(): void
    {
        Log::channel('amocrm')->info("Start saveAnswerAnalysis", [
            'message_id' => $this->id,
            'chat_id'    => $this->chat_id,
            'lead_id'    => $this->lead_id,
            'created_at' => $this->created_at,
        ]);

        // -----------------------------
        // 0) Попытка определить setting_id для расписания
        // -----------------------------
        $account_id = -1;
        try {
            // Попробуем получить setting_id из pivot account_gpt_settings для account (лучшее приближение)
            $accountSetting = AccountGPTSetting::query()
                ->where('setting_id', $this->setting->setting_id)
                ->first();

            $account_id = $accountSetting?->account_id;
        } catch (Throwable $e) {
            Log::channel('amocrm')->warning("Не удалось получить account_id: " . $e->getMessage(), [
                'setting_id' => $this->setting->setting_id
            ]);
        }

        // Время текущего сообщения
        $msgTime = Carbon::parse($this->created_at);

        // -----------------------------
        // 1) Проверка: пришло ли сообщение в активный период?
        // -----------------------------
        $isActiveNow = true; // по умолчанию — активен (на случай, если нет расписания)
        try {
            $isActiveNow = Schedule::isActiveNow($this->setting->setting_id, $msgTime);
        } catch (Throwable $e) {
            Log::channel('amocrm')->warning("Ошибка проверки расписания: " . $e->getMessage(), [
                'setting_id' => $this->setting->setting_id
            ]);
            // оставляем $isActiveNow = true, чтобы не пропускать обработку по ошибке
            $isActiveNow = true;
        }

        // Если сообщение пришло в неактивный период — ничего не синхроним, просто оставим нормальную обработку (сообщение уже сохранено как $this)
        if (!$isActiveNow) {
            Log::channel('amocrm')->info("Сообщение пришло в неактивный период — не синхронизируем историю сейчас", [
                'lead_id' => $this->lead_id,
                'created_at' => $this->created_at,
            ]);
            // продолжаем — далее логика подберёт candidates и т.д. (обычно бот не отвечает в неактивный период)
        } else {

            // -----------------------------
            // 2) Сообщение пришло в активный период — проверяем, первое ли это сообщение после паузы
            // -----------------------------
            // Определяем stopAt = время последнего бот-сообщения (если есть)
            $lastBotMsg = self::query()
                ->where('lead_id', $this->lead_id)
                ->where('origin', 'bot')
                ->orderByDesc('created_at')
                ->first();

            $stopAtTs = $lastBotMsg ? (int) Carbon::parse($lastBotMsg->created_at)->timestamp : null;
            $stopAtCarbon = $lastBotMsg ? Carbon::parse($lastBotMsg->created_at) : null;

            // Проверяем, есть ли уже пользовательские сообщения между stopAt и текущим сообщением
            $hasUserAfterStop = self::query()
                ->where('lead_id', $this->lead_id)
                ->whereIn('origin', ['bot', 'system', 'auto'])
                ->when($stopAtCarbon, function ($q) use ($stopAtCarbon) {
                    // if stopAt exists, messages strictly after stopAt
                    $q->where('created_at', '>', $stopAtCarbon);
                })
                ->where('created_at', '<', $this->created_at)
                ->exists();

            // Если нет таких сообщений — это ПЕРВОЕ пользовательское сообщение в активный период → синхронизируем историю
            if (!$hasUserAfterStop) {
                Log::channel('amocrm')->info("Первое сообщение после паузы — начнём синхронизацию истории", [
                    'lead_id' => $this->lead_id,
                    'stop_at_ts' => $stopAtTs,
                ]);

                try {
                    /** @var PuppeteerService $puppeteer */
                    $puppeteer = app(PuppeteerService::class);

                    $raw = $puppeteer->getLeadHistory(
                        $account_id,
                        $this->domain,
                        $this->lead_id,
                        $stopAtTs,
                        100
                    );

                    Log::channel('amocrm')->info("\$raw" . json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    // raw может быть массивом сообщений либо ['messages' => [...]]
                    $items = [];
                    if (is_array($raw) && array_key_exists('messages', $raw) && is_array($raw['messages'])) {
                        $items = $raw['messages'];
                    } elseif (is_array($raw)) {
                        $items = $raw;
                    }

                    Log::channel('amocrm')->info("Получено элементов истории от Puppeteer", [
                        'count' => count($items),
                        'lead_id' => $this->lead_id,
                    ]);

                    if (!empty($items)) {
                        // Сохраняем полученные сообщения — дедупликация по text + created_at ± 5 сек
                        DB::transaction(function () use ($items, $stopAtTs) {
                            $saved = 0;
                            foreach ($items as $itm) {
                                $data = $itm['data'] ?? [];

                                // --- text (строго из data.message.text или из data.text / itm.text)
                                $text = $data['message']['text']
                                    ?? $itm['text']
                                    ?? null;
                                $text = is_string($text) ? trim($text) : null;
                                if (empty($text)) {
                                    continue;
                                }

                                // --- created_at (unix seconds)
                                $createdAtTs = $data['message']['created_at']
                                    ?? $data['msec_created_at']
                                    ?? $itm['msec_created_at']
                                    ?? $itm['date_create']
                                    ?? $itm['created_at']
                                    ?? null;

                                if ($createdAtTs === null) {
                                    Log::channel('amocrm')->warning('Пропускаем сообщение без created_at', ['item' => $itm]);
                                    continue;
                                }
                                // ms -> s
                                if ($createdAtTs > 100000000000) {
                                    $createdAtTs = (int) floor($createdAtTs / 1000);
                                }
                                $createdAt = Carbon::createFromTimestamp((int)$createdAtTs);

                                // --- chat_id (UUID диалога) — приоритет: data.chat_id, itm.chat_id, fallback this->chat_id
                                $chatId = $data['chat_id'] ?? $itm['chat_id'] ?? $this->chat_id;

                                // --- contact_id: сначала element_id (числовой), затем попытки извлечь из author / origin_profile
                                $contactId = null;
                                if (!empty($itm['element_id'])) {
                                    $contactId = $itm['element_id'];
                                } elseif (!empty($data['element_id'])) {
                                    $contactId = $data['element_id'];
                                }

                                // если нет element_id — посмотрим в author.id (может быть uuid, но иногда numeric)
                                if ($contactId === null && !empty($data['author']['id'])) {
                                    $maybe = $data['author']['id'];
                                    if (is_numeric($maybe)) {
                                        $contactId = (int)$maybe;
                                    } else {
                                        // иногда author.origin_profile содержит JSON с numeric id
                                        $originProfile = $data['author']['origin_profile'] ?? null;
                                        if ($originProfile && is_string($originProfile)) {
                                            $decoded = json_decode($originProfile, true);
                                            if (json_last_error() === JSON_ERROR_NONE && !empty($decoded['id']) && is_numeric($decoded['id'])) {
                                                $contactId = (int)$decoded['id'];
                                            }
                                        }
                                    }
                                }

                                // Если всё ещё нет contactId — подставим 0 (или null, если у тебя nullable)
                                if ($contactId === null) {
                                    // если колонка в БД NOT NULL — ставим 0, иначе ставь null
                                    $contactId = 0;
                                    Log::channel('amocrm')->warning('Не найден contact_id в событии, ставим 0 (fallback)', [
                                        'item_id' => $itm['id'] ?? null,
                                        'chat_id' => $chatId,
                                        'text_preview' => mb_substr($text, 0, 80),
                                    ]);
                                }

                                // --- origin
                                if (isset($data['recipient']['id'])) {
                                    $origin = 'bot';
                                } elseif (isset($data['author']['origin'])) {
                                    $origin = $data['author']['origin'];
                                } else {
                                    $origin = 'user';
                                }

                                // --- дедупликация: text + created_at ±5 сек для одного и того же chat_id/lead_id
                                $exists = self::query()
                                    ->where('lead_id', $this->lead_id)
                                    ->where('chat_id', $chatId)
                                    ->where('text', $text)
                                    ->whereBetween('created_at', [
                                        $createdAt->copy()->subSeconds(5)->toDateTimeString(),
                                        $createdAt->copy()->addSeconds(5)->toDateTimeString(),
                                    ])
                                    ->exists();

                                if ($exists) {
                                    continue;
                                }

                                // Создание записи
                                self::create([
                                    'contact_id' => $contactId,
                                    'chat_id'    => $chatId,
                                    'lead_id'    => $this->lead_id,
                                    'text'       => $text,
                                    'domain'     => $this->domain,
                                    'origin'     => $origin,
                                    'has_reply'  => false,
                                    'reply_id'   => null,
                                    'created_at' => $createdAt->toDateTimeString(),
                                ]);

                                $saved++;
                            } // foreach items

                            Log::channel('amocrm')->info("Сохранено сообщений из истории", [
                                'saved' => $saved,
                                'lead_id' => $this->lead_id,
                            ]);
                        }); // transaction
                    } // if !empty(items)
                } catch (Throwable $e) {
                    Log::channel('amocrm')->error("Ошибка при получении истории через Puppeteer: " . $e->getMessage(), [
                        'lead_id' => $this->lead_id,
                    ]);
                    // не фатальная — продолжаем обычную обработку
                }
            } else {
                Log::channel('amocrm')->info("Не первое сообщение после паузы — синхронизация истории не требуется", [
                    'lead_id' => $this->lead_id,
                ]);
            } // end if !$hasUserAfterStop
        } // end else (isActiveNow)

        // -----------------------------
        // 3) Далее — существующая логика анализа / формирования ответа
        // (копируется и адаптируется из оригинального метода)
        // -----------------------------

        // Водяной знак — до каких user-msg уже были покрыты ботом
        $watermark = self::query()
            ->where('lead_id', $this->lead_id)
            ->where('origin', 'bot')
            ->max('reply_id') ?? 0;

        // Берём все кандидатные пользовательские сообщения (еще без reply)
        $candidates = self::query()
            ->where('chat_id', $this->chat_id)
            ->where('lead_id', $this->lead_id)
            ->where('id', '>', $watermark)
            ->where(function ($q) {
                $q->whereNull('has_reply')->orWhere('has_reply', false);
            })
            ->where(function ($q) {
                $q->whereNull('origin')
                    ->orWhereNotIn('origin', ['bot', 'system', 'auto']);
            })
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            Log::channel('amocrm')->info("No candidate messages for analysis", [
                'chat_id' => $this->chat_id,
                'lead_id' => $this->lead_id,
            ]);
            return;
        }

        $ids = $candidates->pluck('id')->all();
        $analysis_data = $candidates->pluck('text')->implode("\n\n");

        Log::channel('amocrm')->info("before analysis data", [
            'messages_count' => count($ids),
            'sample' => mb_substr($analysis_data, 0, 1000),
        ]);

        // 3) Запрос к анализатору (делаем 1-2 попытки)
        [$analysis_output, $status] = $this->analysis($analysis_data);
        Log::channel('amocrm')->info('analysis result first try', [
            'status' => $status,
            'preview' => is_string($analysis_output) ? mb_substr($analysis_output, 0, 1000) : null,
        ]);

        if (empty($analysis_output) || !$status) {
            [$analysis_output, $status] = $this->analysis($analysis_data);
            Log::channel('amocrm')->info('analysis result second try', [
                'status' => $status,
                'preview' => is_string($analysis_output) ? mb_substr($analysis_output, 0, 1000) : null,
            ]);
        }

        // 4) Если анализа нет — fallback (создаём заглушку и помечаем сообщения)
        if (empty($analysis_output) || !$status) {
            Log::channel('amocrm')->warning('Analysis failed, creating fallback', [
                'chat_id' => $this->chat_id,
                'lead_id' => $this->lead_id,
                'messages_count' => count($ids),
            ]);

            $answer = DB::transaction(function () use ($ids, $candidates) {
                $toUpdate = self::whereIn('id', $ids)
                    ->where(function ($q) {
                        $q->whereNull('has_reply')->orWhere('has_reply', false);
                    })
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->get();

                if ($toUpdate->isEmpty()) {
                    return null;
                }

                $lastUserId = $toUpdate->last()->id;
                $ctx = $toUpdate->last(); // контекст для создания ответа

                $fallbackText = "Бот временно недоступен. Попробуйте позже.";

                $answer = self::create([
                    'contact_id' => $ctx->contact_id,
                    'chat_id'    => $ctx->chat_id,
                    'text'       => $fallbackText,
                    'domain'     => $ctx->domain,
                    'lead_id'    => $ctx->lead_id,
                    'has_reply'  => null,
                    'reply_id'   => $lastUserId,
                    'origin'     => 'system-fallback',
                ]);

                // помечаем только всё ещё необработанные
                self::whereIn('id', $toUpdate->pluck('id')->all())
                    ->where(function ($q) {
                        $q->whereNull('has_reply')->orWhere('has_reply', false);
                    })
                    ->update(['has_reply' => true, 'reply_id' => $lastUserId]);

                return $answer;
            });

            if ($answer) {
                $answer->sendAnswer();
                if ($this->checkDialogCompletion()) {
                    UserChatThread::where('domain', $this->domain)
                        ->where('lead_id', $this->lead_id)
                        ->update(['status' => 0]);
                    Log::channel('amocrm')->info("Диалог закрыт арбитром (fallback)", [
                        'domain' => $this->domain,
                        'lead_id' => $this->lead_id,
                    ]);
                }
            }

            return;
        }

        // 5) Успешный анализ — подготовим ответ и атомарно применим изменения в БД
        $analysis_output = preg_replace('/【.*?】/u', '', $analysis_output);

        $answer = DB::transaction(function () use ($ids, $analysis_output) {
            $toUpdate = self::whereIn('id', $ids)
                ->where(function ($q) {
                    $q->whereNull('has_reply')->orWhere('has_reply', false);
                })
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($toUpdate->isEmpty()) {
                return null;
            }

            $ctx = $toUpdate->last();

            $answer = self::create([
                'contact_id' => $ctx->contact_id,
                'chat_id'    => $ctx->chat_id,
                'text'       => $analysis_output,
                'domain'     => $ctx->domain,
                'lead_id'    => $ctx->lead_id,
                'has_reply'  => null,
                'reply_id'   => null,
                'origin'     => 'bot',
            ]);

            self::whereIn('id', $toUpdate->pluck('id')->all())
                ->where(function ($q) {
                    $q->whereNull('has_reply')->orWhere('has_reply', false);
                })
                ->update(['has_reply' => true, 'reply_id' => $answer->id]);

            return $answer;
        });

        if ($answer) {
            Log::channel('amocrm')->info("Answer created, sending", [
                'answer_id' => $answer->id,
                'messages_count' => count($ids),
            ]);

            $answer->sendAnswer();

            // арбитраж — используем текущий $this (у него должен быть setSetting ранее)
            if ($this->checkDialogCompletion()) {
                UserChatThread::where('domain', $this->domain)
                    ->where('lead_id', $this->lead_id)
                    ->update(['status' => 0]);

                Log::channel('amocrm')->info("Диалог закрыт арбитром", [
                    'domain' => $this->domain,
                    'lead_id' => $this->lead_id,
                ]);
            }
        } else {
            Log::channel('amocrm')->info("No answer created — candidates already handled by another worker", [
                'chat_id' => $this->chat_id,
                'lead_id' => $this->lead_id,
            ]);
        }
    }

    public function saveAnswerAnalysisOld(): void
    {
        Log::channel('amocrm')->info("Start saveAnswerAnalysis", [
            'message_id' => $this->id,
            'chat_id'    => $this->chat_id,
            'lead_id'    => $this->lead_id,
        ]);

        // 1) Водяной знак — до каких user-msg уже были покрыты ботом
        $watermark = ChatMessage::query()
            ->where('lead_id', $this->lead_id)
            ->where('origin', 'bot')
            ->max('reply_id') ?? 0;

        // 2) Берём все кандидатные пользовательские сообщения (еще без reply)
        $candidates = ChatMessage::query()
            ->where('chat_id', $this->chat_id)
            ->where('lead_id', $this->lead_id)
            ->where('id', '>', $watermark)
            ->where(function ($q) {
                $q->whereNull('has_reply')->orWhere('has_reply', false);
            })
            ->where(function ($q) {
                // Фильтр "только пользовательские". Подправь список origin под свою систему.
                $q->whereNull('origin')
                    ->orWhereNotIn('origin', ['bot', 'system', 'auto']);
            })
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            Log::channel('amocrm')->info("No candidate messages for analysis", [
                'chat_id' => $this->chat_id,
                'lead_id' => $this->lead_id,
            ]);
            return;
        }

        $ids = $candidates->pluck('id')->all();
        $analysis_data = $candidates->pluck('text')->implode("\n\n");

        Log::channel('amocrm')->info("before analysis data", [
            'messages_count' => count($ids),
            'sample' => mb_substr($analysis_data, 0, 1000),
        ]);

        // 3) Запрос к анализатору (делаем 1-2 попытки, как у тебя было)
        [$analysis_output, $status] = $this->analysis($analysis_data);
        Log::channel('amocrm')->info('analysis result first try', [
            'status' => $status,
            'preview' => is_string($analysis_output) ? mb_substr($analysis_output, 0, 1000) : null,
        ]);

        if (empty($analysis_output) || !$status) {
            [$analysis_output, $status] = $this->analysis($analysis_data);
            Log::channel('amocrm')->info('analysis result second try', [
                'status' => $status,
                'preview' => is_string($analysis_output) ? mb_substr($analysis_output, 0, 1000) : null,
            ]);
        }

        // 4) Если анализа нет — делаем fallback (создаём заглушку и помечаем сообщения)
        if (empty($analysis_output) || !$status) {
            Log::channel('amocrm')->warning('Analysis failed, creating fallback', [
                'chat_id' => $this->chat_id,
                'lead_id' => $this->lead_id,
                'messages_count' => count($ids),
            ]);

            $answer = DB::transaction(function () use ($ids, $candidates) {
                // заново захватим те, что ещё не обработаны
                $toUpdate = ChatMessage::whereIn('id', $ids)
                    ->where(function ($q) { $q->whereNull('has_reply')->orWhere('has_reply', false); })
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->get();

                if ($toUpdate->isEmpty()) {
                    return null;
                }

                $lastUserId = $toUpdate->last()->id;
                $ctx = $toUpdate->last(); // контекст для создания ответа

                $fallbackText = "Бот временно недоступен. Попробуйте позже.";

                $answer = ChatMessage::create([
                    'contact_id' => $ctx->contact_id,
                    'chat_id'    => $ctx->chat_id,
                    'text'       => $fallbackText,
                    'domain'     => $ctx->domain,
                    'lead_id'    => $ctx->lead_id,
                    'has_reply'  => null,
                    'reply_id'   => $lastUserId,
                    'origin'     => 'system-fallback',
                ]);

                // помечаем только всё ещё необработанные
                ChatMessage::whereIn('id', $toUpdate->pluck('id')->all())
                    ->where(function ($q) { $q->whereNull('has_reply')->orWhere('has_reply', false); })
                    ->update(['has_reply' => true, 'reply_id' => $lastUserId]);

                return $answer;
            });

            if ($answer) {
                $answer->sendAnswer();
                if ($this->checkDialogCompletion()) {
                    UserChatThread::where('domain', $this->domain)
                        ->where('lead_id', $this->lead_id)
                        ->update(['status' => 0]);
                    Log::channel('amocrm')->info("Диалог закрыт арбитром (fallback)", [
                        'domain' => $this->domain,
                        'lead_id' => $this->lead_id,
                    ]);
                }
            }

            return;
        }

        // 5) Успешный анализ — подготовим ответ и атомарно применим изменения в БД
        $analysis_output = preg_replace('/【.*?】/u', '', $analysis_output);

        $answer = DB::transaction(function () use ($ids, $analysis_output) {
            $toUpdate = ChatMessage::whereIn('id', $ids)
                ->where(function ($q) { $q->whereNull('has_reply')->orWhere('has_reply', false); })
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($toUpdate->isEmpty()) {
                return null;
            }

            $ctx = $toUpdate->last();

            $answer = ChatMessage::create([
                'contact_id' => $ctx->contact_id,
                'chat_id'    => $ctx->chat_id,
                'text'       => $analysis_output,
                'domain'     => $ctx->domain,
                'lead_id'    => $ctx->lead_id,
                'has_reply'  => null,
                'reply_id'   => null, // или $ctx->id, если хочешь привязку к последнему вопросу
                'origin'     => 'bot',
            ]);

            ChatMessage::whereIn('id', $toUpdate->pluck('id')->all())
                ->where(function ($q) { $q->whereNull('has_reply')->orWhere('has_reply', false); })
                ->update(['has_reply' => true, 'reply_id' => $answer->id]);

            return $answer;
        });

        if ($answer) {
            Log::channel('amocrm')->info("Answer created, sending", [
                'answer_id' => $answer->id,
                'messages_count' => count($ids),
            ]);

            $answer->sendAnswer();

            // арбитраж — используем текущий $this (у него должен быть setSetting ранее)
            if ($this->checkDialogCompletion()) {
                UserChatThread::where('domain', $this->domain)
                    ->where('lead_id', $this->lead_id)
                    ->update(['status' => 0]);

                Log::channel('amocrm')->info("Диалог закрыт арбитром", [
                    'domain' => $this->domain,
                    'lead_id' => $this->lead_id,
                ]);
            }
        } else {
            Log::channel('amocrm')->info("No answer created — candidates already handled by another worker", [
                'chat_id' => $this->chat_id,
                'lead_id' => $this->lead_id,
            ]);
        }
    }

    public function sendAnswer()
    {
        $account = AccountOAuth2::where('domain', $this->domain)->first();
        if (!$account) {
            return response()->json(['error' => 'Account not found'], 404);
        }

        if (!$account->session_id) {
            $sessionId = bin2hex(random_bytes(16));
            $account->session_id = $sessionId;
            $account->save();
        } else {
            $sessionId = $account->session_id;
        }

        Log::channel('amocrm')->info('Отправляем запрос с заголовками:', [
            'Cookie' => "X-Session-ID={$sessionId}; Path=/; HttpOnly",
        ]);

        $data = $this->formatJsonToArray($this->text);
        $text = $this->formatArrayToText($data);
        $noteText = $this->formatArrayDataToText($data);
        $taskText = $this->formatTasksToText($data);

        // старое закрытие сделки оставляем на всякий случай
        if (isset($data['status']) && ($data['status'] == 2)) {
            $record = UserChatThread::where('domain', $this->domain)
                ->where('lead_id', $this->lead_id)
                ->update(['status' => 0]);

            if ($record === 0) {
                Log::channel('amocrm')->warning("Не удалось обновить статус в UserChatThread", [
                    'domain' => $this->domain,
                    'lead_id' => $this->lead_id
                ]);
            }
        }

        $puppeteerService = new PuppeteerService;
        $puppeteerService->sendLeadMessage(
            $account->account_id,
            $this->domain,
            $this->lead_id,
            $text,
            $noteText,
            $taskText
        );

        return response()->json(true)
            ->cookie('X-Session-ID', $sessionId, 24 * 60, '/', null, true, true, false, 'Lax');
    }

    private function sendAnswerSeleniumToken()
    {
        $url = 'https://kirilltihiy.amocrm.ru';
        $username = 'kirill.tihiy@mail.ru';
        $password = '725513';
        $userId = 1;
        $account_id = 32181490;

        $seleniumAuthService = new SeleniumService;
        $seleniumAuthService->init($account_id);
        $seleniumAuthService->setBaseUrl($url);
        $seleniumAuthService->setCredentials($username, $password);

        $status = $seleniumAuthService->loginWithToken($userId);

        $seleniumAuthService->sendLeadMessage($this->lead_id, $this->text);
    }

    private function sendAnswerSeleniumCookies()
    {
        $url = 'https://kirilltihiy.amocrm.ru';
        $username = 'kirill.tihiy@mail.ru';
        $password = '725513';
        $userId = 1;

        $seleniumAuthService = new SeleniumService;
        $seleniumAuthService->baseUrl = $url;
        $seleniumAuthService->username = $username;
        $seleniumAuthService->password = $password;

        $status = $seleniumAuthService->loginWithCookies($userId);

        $seleniumAuthService->sendLeadMessage($this->lead_id, $this->text);
    }

    private function sendAnswerWazzup()
    {
        $is_telegram = $this->origin === "telegram";
        Http::withHeaders([
            'Authorization' => 'Bearer ' . env('WUZZAP_AUTHORIZATION_BEARER'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->baseUrl(env('WUZZAP_API_URL'))
            ->post('/message', [
                "channelId" => $is_telegram ?
                    env('WUZZAP_TELEGRAM_CHANNEL_ID') : env('WUZZAP_WHATSAPP_CHANNEL_ID'),
                "chatType" => $this->origin,
                "chatId" => ($is_telegram ? '' : '+') . $this->contact_id,
                "text" => $this->text
            ]);
        Log::channel('amocrm')->info("after http");
    }
}
