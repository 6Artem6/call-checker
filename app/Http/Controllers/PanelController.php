<?php

namespace App\Http\Controllers;

use App\Models\AccountOAuth2;
use App\Models\ChatGPTFile;
use App\Models\ChatGPTSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PanelController extends Controller
{
    public function index(Request $request, int $account_id = 0): Response
    {
        /** @var $account AccountOAuth2 */
        $account = Auth::guard('api')->user()?->oauth2;
        if (!$account or ($account_id and ($account->account_id != $account_id))) {
            abort(404);
        }
        $uploaded_files = [];
        $setting = ChatGPTSetting::query()
            ->with('files')
            ->where('account_id', $account->account_id)
            ->first();
        if (is_null($setting)) {
            $setting = new ChatGPTSetting();
        } else {
            $uploaded_files = $setting->files;
        }
        $setting->account_id = $account->account_id;
        return Inertia::render('Panel/Index', [
            'setting' => $setting,
            'available_models' => ChatGPTSetting::getModelList(),
            'uploaded_files' => $uploaded_files
        ]);
    }

    public function save(Request $request, int $account_id = 0): JsonResponse
    {
        /** @var $account AccountOAuth2 */
        $account = Auth::guard('api')->user()?->oauth2;
        if (!$account or ($account_id and ($account->account_id != $account_id))) {
            abort(404);
        }
        // Валидация запроса
        $validated = $request->validate([
            'prompt' => ['nullable', 'string', 'max:2000'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:1'],
            'model' => ['required', 'string', 'in:' . implode(',', ChatGPTSetting::getModelList())],
            'files' => ['nullable', 'array'],
            'files.*' => ['nullable', 'file', 'max:5120'], // Файлы (до 5MB каждый)
        ]);

        // Сохранение настроек
        $setting = ChatGPTSetting::with('files')->updateOrCreate(
            ['account_id' => $account->account_id],
            [
                'prompt' => $validated['prompt'],
                'temperature' => $validated['temperature'],
                'model' => $validated['model']
            ]
        );

        // Обработка файлов
        $fileList = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $model = ChatGPTFile::saveFile($file, $setting->setting_id);
                $fileList[] = $model;
            }
        }

        $setting->setAssistant();

        return response()->json([
            'message' => 'Настройки успешно сохранены!',
            'files' => $fileList,
        ]);
    }

    public function download(string $name): JsonResponse | BinaryFileResponse
    {
        $file = ChatGPTFile::query()->where('stored_name', $name)->firstOrFail();
        if (!$file->getIsFileExists()) {
            return response()->json(['error' => 'Файл не найден'], 404);
        }
        return response()->download($file->getLocalFilePath(), $file->original_name);
    }

    public function delete(string $name): JsonResponse
    {
        $file = ChatGPTFile::query()->where('stored_name', $name)->firstOrFail();
        if (!$file->getIsFileExists()) {
            return response()->json(['error' => 'Файл не найден'], 404);
        }
        $file->deleteFile();
        return response()->json(['error' => 'Файл удалён']);
    }
}
