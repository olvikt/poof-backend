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
use Illuminate\Validation\ValidationException;
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
        $normalizedPhone = preg_replace('/\D/', '', (string) $request->input('phone'));
        $request->merge(['phone' => $normalizedPhone]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'country_code' => ['required', Rule::in(['+380'])],
            'phone' => ['required', 'digits:9'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:client,courier'],
            'transport_type' => ['required_if:role,courier', Rule::in(['walk', 'bike', 'scooter', 'car'])],
            'city' => ['required_if:role,courier', 'string', 'max:255'],
            'terms_agreed' => ['required_if:role,courier', 'accepted'],
        ], [], [
            'phone' => 'phone',
        ]);

        $fullPhone = $validated['country_code'].$validated['phone'];

        if (User::where('phone', $fullPhone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'The phone has already been taken.',
            ]);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $fullPhone,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_verified' => false,
        ]);

        Mail::to($user->email)->send(new WelcomeToPoof($user));

        if ($validated['role'] === User::ROLE_COURIER) {
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
