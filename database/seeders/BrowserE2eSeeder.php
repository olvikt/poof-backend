<?php

namespace Database\Seeders;

use App\Models\ClientAddress;
use App\Models\ClientProfile;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BrowserE2eSeeder extends Seeder
{
    public function run(): void
    {
        $client = User::updateOrCreate(
            ['email' => 'client@test.com'],
            [
                'name' => 'Test Client',
                'password' => Hash::make('password'),
                'role' => User::ROLE_CLIENT,
                'is_active' => true,
                'phone' => '+380501234567',
                'locale' => 'uk',
                'timezone' => 'Europe/Kyiv',
            ]
        );

        ClientProfile::updateOrCreate(
            ['user_id' => $client->id],
            [
                'name' => $client->name,
                'bonuses' => 0,
                'push_notifications' => true,
                'email_notifications' => true,
            ]
        );

        $address = ClientAddress::updateOrCreate(
            [
                'user_id' => $client->id,
                'title' => 'Дім',
            ],
            [
                'label' => 'home',
                'address_text' => 'Kyiv, Test address, building 1',
                'city' => 'Kyiv',
                'street' => 'Test street',
                'house' => '1',
                'entrance' => '1',
                'floor' => '5',
                'apartment' => '51',
                'intercom' => '51',
                'lat' => 50.4501,
                'lng' => 30.5234,
                'is_default' => true,
                'geocode_source' => 'seed',
                'geocode_accuracy' => 'rooftop',
                'geocoded_at' => now(),
            ]
        );

        ClientAddress::query()
            ->where('user_id', $client->id)
            ->whereKeyNot($address->id)
            ->update(['is_default' => false]);

        $courierUser = User::updateOrCreate(
            ['email' => 'courier@poof.app'],
            [
                'name' => 'Test Courier',
                'password' => Hash::make('password'),
                'role' => User::ROLE_COURIER,
                'is_active' => true,
                'phone' => '+380671234567',
                'locale' => 'uk',
                'timezone' => 'Europe/Kyiv',
                'is_online' => true,
                'is_busy' => false,
                'last_lat' => 50.451,
                'last_lng' => 30.523,
                'last_seen_at' => now(),
            ]
        );

        Courier::updateOrCreate(
            ['user_id' => $courierUser->id],
            [
                'status' => Courier::STATUS_ONLINE,
                'city' => 'Kyiv',
                'transport_type' => 'bike',
                'is_verified' => true,
                'last_location_at' => now(),
            ]
        );

        OrderOffer::query()->delete();
        Order::query()->delete();

        $order = Order::unguarded(function () use ($client, $address): Order {
            return Order::query()->create([
                'client_id' => $client->id,
                'status' => Order::STATUS_SEARCHING,
                'payment_status' => Order::PAY_PAID,
                'order_type' => Order::TYPE_ONE_TIME,
                'type' => Order::TYPE_ONE_TIME,
                'service' => 'trash_removal',
                'bags_count' => 1,
                'price' => 80,
                'currency' => 'UAH',
                'address_id' => $address->id,
                'address_text' => 'Kyiv, Test address, building 1',
                'lat' => 50.4501,
                'lng' => 30.5234,
                'scheduled_date' => now()->toDateString(),
                'scheduled_time_from' => '10:00',
                'scheduled_time_to' => '12:00',
                'time_from' => '10:00',
                'time_to' => '12:00',
                'handover_type' => Order::HANDOVER_DOOR,
            ]);
        });

        OrderOffer::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courierUser->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'sequence' => 1,
            'status' => OrderOffer::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
            'last_offered_at' => now(),
        ]);
    }
}
