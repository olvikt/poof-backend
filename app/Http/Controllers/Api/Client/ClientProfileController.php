<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ClientProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        abort_if($user->role !== 'client', 403);

        return response()->json([
            'profile' => $user->clientProfile,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        abort_if($user->role !== 'client', 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'push_notifications' => 'boolean',
            'email_notifications' => 'boolean',
        ]);

        $user->clientProfile->update($data);

        return response()->json([
            'message' => 'Profile updated',
            'profile' => $user->clientProfile,
        ]);
    }
}
