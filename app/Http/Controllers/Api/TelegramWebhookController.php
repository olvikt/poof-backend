<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Notifications\TelegramBindingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramBindingService $bindingService): JsonResponse
    {
        $message = $request->input('message', []);
        $text = (string) ($message['text'] ?? '');
        if (! str_starts_with($text, '/start ')) {
            return response()->json(['ok' => true]);
        }

        $token = trim(substr($text, 7));
        $bindingService->bindByToken(
            $token,
            (string) ($message['chat']['id'] ?? ''),
            isset($message['from']['id']) ? (string) $message['from']['id'] : null,
            $message['from']['username'] ?? null,
        );

        return response()->json(['ok' => true]);
    }
}
