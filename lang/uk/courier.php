<?php

declare(strict_types=1);

return [
    'telegram' => [
        'title' => 'Telegram-сповіщення',
        'status_label' => 'Статус',
        'status_linked' => 'Підʼєднано: :account',
        'status_unlinked' => 'Не підʼєднано',
        'account_fallback' => 'Telegram-акаунт',
        'bot_unavailable' => 'Telegram бот тимчасово недоступний.',
        'link' => 'Підʼєднати Telegram',
        'open' => 'Відкрити Telegram',
        'unlink' => 'Відʼєднати Telegram',
        'orders_pref' => 'Сповіщення про замовлення',
        'marketing_pref' => 'Новини та акції',
        'save_preferences' => 'Зберегти налаштування',
    ],
    'offer' => [
        'accepted' => 'Замовлення прийнято',
        'rejected' => 'Оффер пропущено',
        'closed' => [
            'expired' => 'Час оффера минув',
            'selected_elsewhere' => 'Оффер уже прийняв інший курʼєр',
            'unavailable' => 'Оффер більше недоступний',
        ],
    ],
    'notifications' => [
        'scheduled_order_visible' => "📦 Доступне нове заплановане замовлення\n\nЗамовлення зʼявилось у доступних.\nНатисніть «Готовий виконати», якщо хочете отримати пріоритет.",
        'scheduled_final_offer' => "🚚 Нове замовлення\n\n📍 Забір: :pickup\n🏁 Доставка: :delivery\n🕒 Вікно: :window\n💰 Оплата: :amount\n\n⏳ У вас є :ttl секунд, щоб прийняти замовлення.",
        'scheduled_offer_expiring_soon' => "⚠️ Час майже вичерпано\n\nДо завершення оффера залишилось менше 10 секунд.",
        'scheduled_reservation_lost' => 'ℹ️ Замовлення вже передано іншому курʼєру.',
        'address_fallback' => 'Адресу уточнюйте в застосунку',
        'window_fallback' => 'Час уточнюйте в застосунку',
    ],
];
