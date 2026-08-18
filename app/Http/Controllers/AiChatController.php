<?php

namespace App\Http\Controllers;

use App\Services\AI\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function __construct(private readonly AiChatService $aiChat) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $result = $this->aiChat->answer($request->user(), $validated['message']);
        return response()->json($result);
    }
}
