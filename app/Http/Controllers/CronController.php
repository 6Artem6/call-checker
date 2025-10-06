<?php

namespace App\Http\Controllers;

use App\Models\Voice\Request;
use App\Models\AiLead\Account\AccountOAuth2;
use Illuminate\Http\JsonResponse;


class CronController extends Controller
{
    /**
     * Действие для транскрибирования файлов
     */
    public function fileTranscribe(): JsonResponse
    {
        $model = new Request();
        $model->saveDataTranscribe();
        return response()->json(['message' => 'File transcription completed.']);
    }

    /**
     * Действие для анализа файлов
     */
    public function fileAnalysis(): JsonResponse
    {
        $request = new Request();
        $request->saveDataAnalysisNew();
        return response()->json(['message' => 'File analysis completed.']);
    }

    /**
     * Действие для обновления токенов
     */
    public function refreshTokens(): JsonResponse
    {
        $accounts = AccountOAuth2::all();
        foreach ($accounts as $account) {
            $account->refreshAccessData();
        }
        return response()->json(['message' => 'Refresh tokens completed.']);
    }
}
