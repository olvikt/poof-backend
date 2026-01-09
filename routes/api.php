<?php

use Illuminate\Support\Facades\Route;

// CLIENT
use App\Http\Controllers\Api\Client\ClientProfileController;
use App\Http\Controllers\Api\Client\ClientAddressController;
use App\Http\Controllers\Api\OrderController;

// COURIER
use App\Http\Controllers\Api\CourierOrderController;

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | CLIENT ROUTES
    |--------------------------------------------------------------------------
    */

    // CLIENT PROFILE
    Route::get('/client/profile', [ClientProfileController::class, 'show']);
    Route::put('/client/profile', [ClientProfileController::class, 'update']);

    // CLIENT ADDRESSES
    Route::get('/client/addresses', [ClientAddressController::class, 'index']);
    Route::post('/client/addresses', [ClientAddressController::class, 'store']);

    // CLIENT ORDERS
    Route::post('/orders', [OrderController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | COURIER ROUTES
    |--------------------------------------------------------------------------
    */

    // available orders for courier
    Route::get('/orders/available', [CourierOrderController::class, 'available']);

    // courier accepts order
    Route::post('/orders/{order}/accept', [CourierOrderController::class, 'accept']);
});


