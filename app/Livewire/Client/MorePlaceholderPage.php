<?php

declare(strict_types=1);

namespace App\Livewire\Client;

use Illuminate\Support\Arr;
use Livewire\Component;

class MorePlaceholderPage extends Component
{
    public bool $embedded = false;

    public string $page = 'promocodes';

    public string $title = '';

    public string $description = '';

    public function mount(string $page = "promocodes", bool $embedded = false): void
    {
        $this->embedded = $embedded;
        $contentMap = [
            'promocodes' => [
                'title' => 'Промокоди скоро з\'являться',
                'description' => 'Ми будуємо програму лояльності для кожного клієнта: персональні бонуси, приємні знижки та спеціальні пропозиції.',
            ],
            'settings' => [
                'title' => 'Налаштування в розробці',
                'description' => 'Незабаром тут можна буде керувати деталями акаунту, сповіщеннями та персональними параметрами сервісу.',
            ],
        ];

        if (! Arr::has($contentMap, $page)) {
            abort(404);
        }

        $this->page = $page;
        $this->title = $contentMap[$page]['title'];
        $this->description = $contentMap[$page]['description'];
    }

    public function render()
    {
        $view = view('livewire.client.more-placeholder-page');

        return $this->embedded ? $view : $view->layout('layouts.client');
    }
}
