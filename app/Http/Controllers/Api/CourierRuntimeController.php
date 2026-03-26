<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;

class CourierRuntimeController extends Controller
{
    public function show()
    {
        $user = auth()->user();

        abort_if(! $user instanceof User || ! $user->isCourier(), 403);

        return response()->json([
            'runtime' => $user->courierRuntimeSnapshot(),
        ]);
    }
}
