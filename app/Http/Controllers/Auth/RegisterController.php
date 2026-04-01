<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\Courier;
use App\Models\User;
use App\Support\Auth\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use App\Support\Auth\RoleEntrypoint;

class RegisterController extends Controller
{
    public function show(Request $request): View
    {
        $entrypoint = RoleEntrypoint::detect($request);
        $role = RoleEntrypoint::expectedRegistrationRole($request);

        return view('auth.register', [
            'defaultRole' => $role,
            'entrypoint' => $entrypoint,
        ]);
    }

    public function register(Request $request): RedirectResponse|JsonResponse
    {
        $request->merge([
            'phone' => PhoneNormalizer::normalize(
                $request->input('phone'),
                $request->input('country_code')
            ),
        ]);

        $role = RoleEntrypoint::expectedRegistrationRole($request);
        $request->merge(['role' => $role]);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'password' => 'required|min:8|confirmed',
            'role' => 'required|in:client,courier',
            'terms_agreed' => 'accepted',
        ];

        if ($role === User::ROLE_COURIER) {
            $rules['transport_type'] = 'required|string';
            $rules['city'] = 'required|string';
        }

        $validated = $request->validate($rules, [], [
            'phone' => 'phone',
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'is_verified' => false,
            ]);

            if ($validated['role'] === User::ROLE_COURIER) {
                $this->createCourierProfile($user, $validated);
            }

            DB::afterCommit(function () use ($user): void {
                try {
                    Mail::to($user->email)->send(new WelcomeMail($user));
                    Log::info('Welcome email sent to: '.$user->email);
                } catch (\Exception $e) {
                    Log::error('Mail send error: '.$e->getMessage());
                }
            });

            return $user;
        });

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

    protected function createCourierProfile(User $user, array $validated): Courier
    {
        return Courier::create([
            'user_id' => $user->id,
            // Canonical registration contract: both legacy `transport` and
            // newer `transport_type` must be set to the same selected value.
            'transport' => $validated['transport_type'],
            'transport_type' => $validated['transport_type'],
            'city' => $validated['city'],
        ]);
    }
}
