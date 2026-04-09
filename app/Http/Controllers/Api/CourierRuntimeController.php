<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Courier\CourierPresenceService;

class CourierRuntimeController extends Controller
{
    public function show()
    {
        $user = auth()->user();

        abort_if(! $user instanceof User || ! $user->isCourier(), 403);
        $runtime = app(CourierPresenceService::class)->snapshot($user);

        return response()->json([
            'runtime' => $runtime,
        ]);
    }
}
