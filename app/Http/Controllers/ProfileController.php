<?php

namespace App\Http\Controllers;

use App\Models\ClientAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.auth()->id()],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'Профіль оновлено.');
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'city' => ['nullable', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'house' => ['required', 'string', 'max:64'],
            'apartment' => ['nullable', 'string', 'max:64'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        ClientAddress::create([
            'user_id' => $request->user()->id,
            'city' => $data['city'] ?? null,
            'street' => $data['street'],
            'house' => $data['house'],
            'apartment' => $data['apartment'] ?? null,
            // Колонки comment у client_addresses нет, сохраняем как title
            'title' => $data['comment'] ?? null,
            'label' => 'other',
        ]);

        return back()->with('success', 'Адресу додано.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $path = $request->file('avatar')->store('avatars', 'public');

        $request->user()->update([
            'avatar' => $path,
        ]);

        return back()->with('success', 'Аватар оновлено.');
    }
}
