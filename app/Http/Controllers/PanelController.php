<?php

namespace App\Http\Controllers;

use App\Models\AiLead\Account\AccountOAuth2;
use App\Models\AiLead\Chat\ChatMessage;
use App\Models\AiLead\Gpt\{AccountGPTSetting, ChatGPTFile, ChatGPTSetting};
use App\Models\AiLead\Chat\Schedule;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use App\Models\AiLead\Pipeline\{Pipeline, PipelineStatus};
use App\Services\AmoSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PanelController extends Controller
{
    public function index(Request $request, int $account_id = 0): Response
    {
        /** @var AccountOAuth2|null $account */
        $account = Auth::guard('api')->user()?->oauth2;
        if (!$account || ($account_id && $account->account_id != $account_id)) {
            abort(404);
        }
        if (!$account_id) {
            $account_id = $account->account_id;
        }

        // 2) связи Account–GPTSetting (pivot) с файлами
        $settings = $account
            ->gptSettings()
            ->with('files')
            ->get();

        // 3) определяем активный этап из запроса или из первой настройки
        $activePipelineId = $request->input('pipeline_status_id')
            ?: ($settings->first()?->pivot->pipeline_status_id ?? null);

        // 4) подтягиваем соответствующую ChatGPTSetting или создаём пустую
        if ($activePipelineId) {
            $activeSetting = $settings
                ->first(fn($s) => $s->pivot->pipeline_status_id == $activePipelineId)
                ?->replicate(); // replicate, чтобы не ломать коллекцию
        }

        if (empty($activeSetting)) {
            $activeSetting = new ChatGPTSetting([
                'prompt'      => '',
                'model'       => ChatGPTSetting::getModelList()[0],
            ]);
            $activeSetting->setting_id = null;
        }

        // Тянем расписание
        $schedules = [];
        if ($activeSetting->setting_id) {
            $schedules = Schedule::where('setting_id', $activeSetting->setting_id)
                ->orderBy('weekday')
                ->orderBy('time_from')
                ->get();
        }

        return Inertia::render('Panel/Index', [
            'account_id'          => $account_id,
            'settings'            => $settings,
            'active_pipeline_id'  => $activePipelineId,
            'setting'             => $activeSetting,
            'available_models'    => ChatGPTSetting::getModelList(),
            'uploaded_files'      => $activeSetting->files ?? [],
            'schedules'           => $schedules,
        ]);
    }

    public function save(Request $request, int $account_id = 0): JsonResponse
    {
        /** @var AccountOAuth2|null $account */
        $account = Auth::guard('api')->user()?->oauth2;
        if (!$account || ($account_id && $account->account_id != $account_id)) {
            abort(404);
        }
        if (!$account_id) {
            $account_id = $account->account_id;
        }
        $input = $request->all();

        // Преобразуем 'null', '', false → null
        $input['setting_id'] = filter_var($input['setting_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['default' => null]
        ]);
        $input['is_active'] = isset($input['is_active']) && $input['is_active'] === 'true';

        $request->replace($input);

        $validated = $request->validate([
            'pipeline_status_id'     => ['required', 'integer', 'exists:pipeline_status,id'],
            'setting_id'             => ['nullable', 'integer'],
            'prompt'                 => ['nullable', 'string'],
            'completion_condition'   => ['required', 'string'],
            'delay'                  => ['required', 'integer', 'min:0'],
            'model'                  => ['required', 'string', 'in:' . implode(',', ChatGPTSetting::getModelList())],
            'is_active'              => ['required', 'boolean'],
            'files'                  => ['nullable', 'array'],
            'files.*'                => ['nullable', 'file', 'max:5120'],
            'schedules.*.weekday'    => ['required_with:schedules', 'array'],
            'schedules.*.weekday.*'  => ['integer', 'between:1,7'],
            'schedules.*.time_from'  => ['required_with:schedules', 'date_format:H:i'],
            'schedules.*.time_to'    => ['required_with:schedules', 'date_format:H:i', 'after:time_from'],
        ]);
        // обновляем или создаём основную настройку
        if (!empty($validated['setting_id'])) {
            $setting = ChatGPTSetting::findOrFail($validated['setting_id']);
            $setting->update([
                'prompt'               => $validated['prompt'],
                'model'                => $validated['model'],
                'completion_condition' => $validated['completion_condition'],
                'delay'                => $validated['delay'],
                'is_active'            => $validated['is_active'],
                'account_id'           => $account_id,
            ]);
        } else {
            $setting = ChatGPTSetting::create([
                'prompt'               => $validated['prompt'],
                'model'                => $validated['model'],
                'completion_condition' => $validated['completion_condition'],
                'delay'                => $validated['delay'],
                'is_active'            => $validated['is_active'],
                'account_id'           => $account_id,
            ]);
        }

        // обновляем pivot-таблицу account_gpt_settings
        $record = AccountGPTSetting::firstOrNew([
            'account_id'         => $account_id,
            'pipeline_status_id' => $validated['pipeline_status_id'],
        ]);
        $record->setting_id = $setting->setting_id;
        $record->save();

        // сохраняем файлы через ChatGPTFile
        $fileList = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $fileList[] = ChatGPTFile::saveFile($file, $setting->setting_id);
            }
        }

        // сохраняем расписание
        Schedule::where('setting_id', $setting->setting_id)->delete();
        if (isset($validated['schedules'])) {
            foreach ($validated['schedules'] as $sch) {
                foreach ($sch['weekday'] as $day) {
                    Schedule::create([
                        'setting_id' => $setting->setting_id,
                        'weekday'    => $day,
                        'time_from' => $sch['time_from'],
                        'time_to'   => $sch['time_to'],
                    ]);
                }
            }
        }

        $setting->setAssistant();

        return response()->json([
            'message' => 'Настройки успешно сохранены!',
            'files'   => $fileList,
        ]);
    }

    public function search(Request $request, int $account_id = 0)
    {
        /** @var AccountOAuth2|null $account */
        $account = Auth::guard('api')->user()?->oauth2;
        if (!$account || ($account_id && $account->account_id != $account_id)) {
            abort(404);
        }

        $query = ChatMessage::from('chat_message as messages')
            ->leftJoin('chat_message as replies', 'replies.id', '=', 'messages.reply_id')
            ->select([
                'messages.*',
                'replies.text as reply_text',
                'replies.created_at as reply_created_at'
            ])
            ->where('messages.domain', $account->domain)
            ->where('messages.origin', '!=', 'bot');

        // Поиск по тексту вопроса и ответа
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('messages.text', 'like', '%' . $request->search . '%')
                    ->orWhere('replies.text', 'like', '%' . $request->search . '%');
            });
        }

        // Фильтрация по дате
        if ($request->filled('date_from')) {
            $query->where('messages.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('messages.created_at', '<=', $request->date_to);
        }

        // Фильтр по наличию ответа
        $hasReply = $request->input('has_reply', null); // всегда строка или null

        if (!is_null($hasReply)) {
            if ($hasReply === "1") {
                $query->whereNotNull('messages.has_reply');
            } elseif ($hasReply === "0") {
                $query->whereNull('messages.has_reply');
            }
        }

        // Сортировка
        $dir = $request->dir === 'asc' ? 'asc' : 'desc';
        $query->orderBy('messages.created_at', $dir);
//        dd($query->toRawSql());
        $messages = $query->get();
        $perPage = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        // Группировка по reply_id
        $grouped = $messages->groupBy(fn($msg) => $msg->reply_id ?: $msg->id);

        $transformed = $grouped->map(function ($group) {
            $firstMessage = $group->first();

            $questions = $group->map(function($msg) {
                $parsed = $msg->formatJsonToArray($msg->text);
                $qArray = $msg->formatArrayToQuestions($parsed);
                return !empty($qArray) ? implode("\n", $qArray) : $msg->text;
            })->implode("\n");

            $answerText = null;
            $answerParams = [];
            $answerCreatedAt = null;

            if ($firstMessage->reply_text) {
                $parsedAnswer = $firstMessage->formatJsonToArray($firstMessage->reply_text);
                $answerText = $firstMessage->formatArrayToText($parsedAnswer);
                $answerParams = $parsedAnswer['data'] ?? [];
                $answerCreatedAt = $firstMessage->reply_created_at;
            }

            return [
                'id' => $firstMessage->id,
                'domain' => $firstMessage->domain,
                'contact_id' => $firstMessage->contact_id,
                'lead_id' => $firstMessage->lead_id,
                'has_reply' => $firstMessage->has_reply,
                'reply_id' => $firstMessage->reply_id_actual,
                'created_at' => $firstMessage->created_at,
                'questions' => $questions,
                'answer_text' => $answerText,
                'answer_params' => $answerParams,
                'answer_created_at' => $answerCreatedAt,
            ];
        })->values();

        // Срез для текущей страницы
        $totalGroups = $transformed->count();
        $currentItems = $transformed->forPage($currentPage, $perPage);

        // Пагинация
        $paginated = new LengthAwarePaginator(
            $currentItems,
            $totalGroups, // общее количество групп
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return Inertia::render('Panel/Search', [
            'messages' => $paginated,
            'filters' => $request->only(['search', 'dir', 'date_from', 'date_to', 'has_reply']),
        ]);
    }

    public function download(string $name)
    {
        $file = ChatGPTFile::where('stored_name', $name)->firstOrFail();
        return $file->streamFromOpenAI();
    }

    public function delete(string $name): JsonResponse
    {
        $file = ChatGPTFile::where('stored_name', $name)->firstOrFail();
        $file->deleteFile();
        return response()->json(['error' => 'Файл удалён']);
    }

    // API-методы
    public function statusAll(Request $request, int $account_id = 0): JsonResponse
    {
        $account = Auth::guard('api')->user()?->oauth2;
        if (!$account || ($account_id && $account->account_id != $account_id)) {
            abort(404);
        }

        $statuses = PipelineStatus::query()
            ->select([
                'pipeline_status.id as status_id',
                'pipeline_status.name',
                'pipeline_status.pipeline_id',
                'pipeline.name as pipeline_name'
            ])
            ->join('pipeline', 'pipeline.id', '=', 'pipeline_status.pipeline_id')
            ->where('pipeline.account_id', $account_id)
            ->orderBy('pipeline.sort')
            ->orderBy('pipeline_status.sort')
            ->get()
            ->groupBy('pipeline_name')
            ->map(fn($group) => $group->values())
            ->toArray();

        return response()->json($statuses);
    }

    public function getSetting(Request $request, int $account_id = 0): JsonResponse
    {
        $account = Auth::guard('api')->user()?->oauth2;
        if (!$account || ($account_id && $account->account_id != $account_id)) {
            abort(404);
        }

        $request->validate([
            'pipeline_status_id' => ['required', 'integer', 'exists:pipeline_status,id'],
        ]);

        $setting = AccountGPTSetting::query()
            ->where('account_id', $account->account_id)
            ->where('pipeline_status_id', $request->input('pipeline_status_id'))
            ->with('setting')
            ->with('setting.files')
            ->first();

        if (!$setting || !$setting->setting) {
            return response()->json([
                'setting'        => null,
                'uploaded_files' => [],
            ]);
        }

        // Тянем расписание
        $schedulesRaw = Schedule::where('setting_id', $setting->setting->setting_id)
            ->orderBy('weekday')
            ->orderBy('time_from')
            ->get();

        $grouped = [];
        foreach ($schedulesRaw as $sch) {
            $key = $sch->time_from . '-' . $sch->time_to;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'days'      => [],
                    'time_from' => $sch->time_from,
                    'time_to'   => $sch->time_to,
                ];
            }
            $grouped[$key]['days'][] = $sch->weekday;
        }

        $schedules = array_values($grouped);

        return response()->json([
            'setting' => [
                'account_id'  => $setting->account_id,
                'setting_id'  => $setting->setting->setting_id,
                'prompt'      => $setting->setting->prompt,
                'completion_condition' => $setting->setting->completion_condition,
                'delay'       => $setting->setting->delay,
                'model'       => $setting->setting->model,
                'is_active'   => $setting->setting->is_active,
                'schedules'   => $schedules,
            ],
            'uploaded_files' => $setting->setting->files,
        ]);
    }

    public function syncView(int $account_id = 0)
    {
        $account = Auth::guard('api')->user()?->oauth2;
        if (!$account || ($account_id && $account->account_id != $account_id)) {
            abort(404);
        }
        if (!$account_id) {
            $account_id = $account->account_id;
        }

        $pipelines = Pipeline::where('account_id', $account_id)->get();

        return inertia('Panel/SyncView', [
            'account_id' => $account_id,
            'pipelines' => $pipelines,
        ]);
    }

    public function sync(Request $request, AmoSyncService $service, int $account_id = 0)
    {
        /** @var AccountOAuth2|null $account */
        $account = Auth::guard('api')->user()?->oauth2;
        if (!$account || ($account_id && $account->account_id != $account_id)) {
            abort(404);
        }
        if (!$account_id) {
            $account_id = $account->account_id;
        }
        $pipelineId = $request->input('pipeline_id');
        if (!$pipelineId) {
            return back()->withErrors(['pipeline_id' => 'Не выбрана воронка']);
        }
        $account = AccountOAuth2::findOrFail($account_id);

        if ($pipelineId === 'all') {
            $service->syncAccount($account_id);
        } else {
            $service->fetchAndStoreLeads($account, (int) $pipelineId);
        }
        return back()->with('success', 'Синхронизация завершена');
    }
}
