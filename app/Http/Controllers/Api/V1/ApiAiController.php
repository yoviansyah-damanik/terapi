<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AiService;
use Illuminate\Http\Request;

class ApiAiController extends Controller
{
    /**
     * Endpoint untuk mengirim prompt ke AI Active Provider.
     */
    public function prompt(Request $request, AiService $aiService)
    {
        $request->validate([
            'system_prompt' => 'nullable|string',
            'user_prompt'   => 'required|string',
            'format'        => 'nullable|string|in:text,json,json_object',
        ]);

        $systemPrompt = $request->input('system_prompt', 'Berikan respon yang singkat dan padat.');
        $userPrompt   = $request->input('user_prompt');
        $format       = $request->input('format', 'text');

        try {
            $response = $aiService->sendPrompt($systemPrompt, $userPrompt, $format);

            return response()->json([
                'success' => true,
                'data'    => [
                    'response' => $response,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses prompt AI: ' . $e->getMessage(),
            ], 500);
        }
    }
}
