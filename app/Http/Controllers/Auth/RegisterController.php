<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeToPoof;
use App\Models\Courier;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(Request $request): View
    {
        $role = $request->string('role')->toString();

        if (! in_array($role, [User::ROLE_CLIENT, User::ROLE_COURIER], true)) {
            $role = User::ROLE_CLIENT;
        }

        return view('auth.register', [
            'defaultRole' => $role,
        ]);
    }

    public function register(Request $request): RedirectResponse|JsonResponse
    {
        $normalizedPhone = preg_replace('/\D/', '', (string) $request->input('phone'));
        $request->merge(['phone' => (string) $request->input('country_code', '').$normalizedPhone]);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'password' => 'required|min:8|confirmed',
            'role' => 'required|in:client,courier',
            'terms_agreed' => 'accepted',
        ];

        if ($request->role === 'courier') {
            $rules['transport_type'] = 'required|string';
            $rules['city'] = 'required|string';
        }

        $validated = $request->validate($rules, [], [
            'phone' => 'phone',
        ]);
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_verified' => false,
        ]);

        Mail::to($user->email)->send(new WelcomeToPoof($user));

        if ($request->role === 'courier') {
            Courier::create([
                'user_id' => $user->id,
                'transport_type' => $request->transport_type,
                'city' => $request->city,
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Registered successfully',
                'redirect_to' => $user->role === User::ROLE_COURIER ? '/courier' : '/dashboard',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                ],
            ], 201);
        }

        return redirect($user->role === User::ROLE_COURIER ? '/courier' : '/dashboard');
    }
}
