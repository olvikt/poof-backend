<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:30', Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in([User::ROLE_CLIENT, User::ROLE_COURIER])],
            'transport_type' => ['nullable', 'required_if:role,courier', Rule::in(['walk', 'bike', 'scooter', 'car'])],
            'city' => ['nullable', 'required_if:role,courier', 'string', 'max:255'],
            'terms_agreed' => ['required_if:role,courier', 'accepted'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_verified' => false,
        ]);

        if ($request->role === User::ROLE_COURIER) {
            Courier::create([
                'user_id' => $user->id,
                'transport' => $validated['transport_type'],
                'transport_type' => $validated['transport_type'],
                'city' => $validated['city'],
                'status' => 'offline',
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
