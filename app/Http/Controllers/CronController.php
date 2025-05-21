<?php

namespace App\Http\Controllers;

use App\Models\Request;
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
}
