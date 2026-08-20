<?php

namespace App\Http\Controllers;

use App\Services\AI\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiChatController extends Controller
{
    public function __construct(private readonly AiChatService $aiChat) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $result = $this->aiChat->answer($request->user(), $validated['message']);
            return response()->json($result);
        } catch (\Throwable $exception) {
            Log::error('AI_CHAT_REQUEST_FAILURE', [
                'user_id' => $request->user()?->getAuthIdentifier(),
                'role' => $request->user()?->role,
                'message_hash' => hash('sha256', $validated['message']),
                'exception' => get_class($exception),
                'code' => (string) $exception->getCode(),
            ]);

            return response()->json([
                'ok' => true,
                'answer' => 'Layanan AI sedang mengalami gangguan sementara. Silakan coba lagi beberapa saat lagi.',
                'sources' => [],
                'degraded' => true,
            ], 200);
        }
    }
}
