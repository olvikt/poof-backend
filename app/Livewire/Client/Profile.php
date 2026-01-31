<?php

namespace App\Livewire\Client;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Profile extends Component
{
	/**
     * События от форм (без перезагрузки страницы)
     */
    protected $listeners = [
        'avatar-saved'   => '$refresh',
        'profile-saved'  => '$refresh',
        'address-saved'  => '$refresh',
    ];

	public function render()
	{
		return view('livewire.client.profile', [
			'user' => auth()->user()->fresh(), // 🔥 ВАЖНО
		])
		->layout('layouts.client');
	}
}
