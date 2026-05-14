<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Notifications\TelegramBindingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramBindingService $bindingService): JsonResponse
    {
        $message = $request->input('message', []);
        $chatId = isset($message['chat']['id']) ? (string) $message['chat']['id'] : null;
        $fromId = isset($message['from']['id']) ? (string) $message['from']['id'] : null;
        $hasText = isset($message['text']) && is_string($message['text']) && $message['text'] !== '';
        $text = $hasText ? (string) $message['text'] : '';

        if (! $hasText) {
            Log::info('telegram_webhook_ignored', ['reason' => 'missing_text', 'chat_id' => $chatId, 'from_id' => $fromId, 'has_text' => false]);

            return response()->json(['ok' => true]);
        }

        $normalizedText = preg_replace('/\s+/', ' ', trim($text)) ?? '';

        if ($normalizedText === '/start') {
            Log::info('plain_start_missing_payload', ['chat_id' => $chatId, 'from_id' => $fromId]);

            return response()->json(['ok' => true]);
        }

        if (! preg_match('/^\/start\s+([A-Za-z0-9_-]+)$/', $normalizedText, $matches)) {
            Log::info('telegram_webhook_ignored', ['reason' => 'unsupported_command', 'chat_id' => $chatId, 'from_id' => $fromId, 'has_text' => true]);

            return response()->json(['ok' => true]);
        }

        $token = trim($matches[1]);
        $bindingService->bindByToken(
            $token,
            (string) ($chatId ?? ''),
            $fromId,
            $message['from']['username'] ?? null,
        );

        return response()->json(['ok' => true]);
    }
}
