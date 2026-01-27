<?php

namespace App\Livewire\Client;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Profile extends Component
{
    /**
     * Слушаем события обновления профиля,
     * чтобы карточки обновлялись без перезагрузки
     */
    protected $listeners = [
        'avatar-updated' => '$refresh',
        'avatar-saved'   => '$refresh',
        'profile-updated'=> '$refresh',
        'address-updated'=> '$refresh',
    ];

    public function render()
    {
        return view('livewire.client.profile', [
            'user' => Auth::user(),
        ])
        // ✅ КЛЮЧЕВО: подключаем клиентский layout
        // (header, bottom-nav, more-sheet, mobile-структура)
        ->layout('layouts.client');
    }
}
