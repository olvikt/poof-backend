<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Notifications\TelegramBindingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CourierTelegramController extends Controller
{
    public function generateLink(TelegramBindingService $bindingService): RedirectResponse
    {
        $courier = auth()->user();
        abort_if(! $courier instanceof User || ! $courier->isCourier(), 403);
        $link = $bindingService->generateForCourier($courier);

        return back()->with('telegram_deep_link', $link['deep_link']);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $courier = auth()->user();
        abort_if(! $courier instanceof User || ! $courier->isCourier(), 403);

        $data = $request->validate([
            'telegram_notifications_orders_enabled' => ['required', 'boolean'],
            'telegram_notifications_marketing_enabled' => ['required', 'boolean'],
        ]);

        $courier->forceFill($data)->save();

        return back()->with('success', 'Telegram settings updated.');
    }

    public function unlink(TelegramBindingService $bindingService): RedirectResponse
    {
        $courier = auth()->user();
        abort_if(! $courier instanceof User || ! $courier->isCourier(), 403);
        $bindingService->unlink($courier);

        return back()->with('success', 'Telegram unlinked.');
    }
}
