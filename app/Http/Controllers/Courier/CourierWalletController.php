<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Actions\Courier\Payout\CreateCourierWithdrawalRequestAction;
use App\Actions\Courier\Payout\SaveCourierPayoutRequisitesAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Courier\Wallet\CourierWalletReadModelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CourierWalletController extends Controller
{
    public function show(CourierWalletReadModelService $readModelService)
    {
        $courier = $this->resolveCourier();
        abort_if(! $courier instanceof User, 403);

        return view('courier.wallet', [
            'wallet' => $readModelService->forCourier($courier),
        ]);
    }

    public function requestWithdrawal(Request $request, CreateCourierWithdrawalRequestAction $action): RedirectResponse
    {
        $courier = $this->resolveCourier();
        abort_if(! $courier instanceof User, 403);

        $payload = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $action->execute(
            $courier,
            (int) $payload['amount'],
            isset($payload['notes']) ? (string) $payload['notes'] : null,
        );

        return back()->with('success', 'Запит на вивід створено.');
    }

    public function saveRequisites(Request $request, SaveCourierPayoutRequisitesAction $action): RedirectResponse
    {
        $courier = $this->resolveCourier();
        abort_if(! $courier instanceof User, 403);

        $payload = $request->validate([
            'card_holder_name' => ['required', 'string', 'max:255'],
            'card_number' => ['required', 'string', 'regex:/^[0-9\s]{12,24}$/'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $action->execute(
            $courier,
            (string) $payload['card_holder_name'],
            (string) $payload['card_number'],
            isset($payload['bank_name']) ? (string) $payload['bank_name'] : null,
            isset($payload['notes']) ? (string) $payload['notes'] : null,
        );

        return back()->with('success', 'Реквізити для виплат збережено.');
    }

    private function resolveCourier(): ?User
    {
        $user = auth()->user();

        return $user instanceof User && $user->isCourier()
            ? $user
            : null;
    }
}
