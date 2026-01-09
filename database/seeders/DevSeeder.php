<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

use App\Models\User;
use App\Models\ClientProfile;
use App\Models\ClientAddress;
use App\Models\Courier;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ ADMIN
        $admin = User::updateOrCreate(
            ['email' => 'admin@poof.app'],
            [
                'name'      => 'Admin',
                'password'  => Hash::make('password'),
                'role'      => 'admin',
                'is_active' => true,
                'phone'     => null,
                'locale'    => 'uk',
                'timezone'  => 'Europe/Kyiv',
            ]
        );

        // ✅ TEST CLIENT
        $client = User::updateOrCreate(
            ['email' => 'client@test.com'],
            [
                'name'      => 'Test Client',
                'password'  => Hash::make('password'),
                'role'      => 'client',
                'is_active' => true,
                'phone'     => '+380501234567',
                'locale'    => 'uk',
                'timezone'  => 'Europe/Kyiv',
            ]
        );

        // Client profile (bonuses/notifications)
        ClientProfile::updateOrCreate(
            ['user_id' => $client->id],
            [
                'name'                => $client->name,
                'bonuses'             => 0,
                'push_notifications'  => true,
                'email_notifications' => true,
            ]
        );

        // Default address (Uber-style)
        // Сбрасываем default у всех адресов клиента
        ClientAddress::where('user_id', $client->id)->update(['is_default' => false]);

        ClientAddress::updateOrCreate(
            [
                'user_id'     => $client->id,
                'title'       => 'Дім',
            ],
            [
                'address_text' => 'Kyiv, Test address, building 1',
                'city'         => 'Kyiv',
                'street'       => 'Test street',
                'house'        => '1',
                'entrance'     => '1',
                'floor'        => '5',
                'apartment'    => '51',
                'intercom'     => '51',
                'lat'          => 50.4501000,
                'lng'          => 30.5234000,
                'is_default'   => true,
            ]
        );

        // ✅ TEST COURIER USER
        $courierUser = User::updateOrCreate(
            ['email' => 'courier@poof.app'],
            [
                'name'      => 'Test Courier',
                'password'  => Hash::make('password'),
                'role'      => 'courier',
                'is_active' => true,
                'phone'     => '+380671234567',
                'locale'    => 'uk',
                'timezone'  => 'Europe/Kyiv',
            ]
        );

        // Courier profile (таблица couriers)
        Courier::updateOrCreate(
            ['user_id' => $courierUser->id],
            [
                // добавь сюда поля, если в couriers есть обязательные столбцы
            ]
        );
    }
}
