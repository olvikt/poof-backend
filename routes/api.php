<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Middleware\AdminOnly;

// CLIENT
use App\Http\Controllers\Api\Client\ClientProfileController;
use App\Http\Controllers\Api\Client\ClientAddressController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\GeocodeController;

// COURIER
use App\Http\Controllers\Api\CourierOrderController;
use App\Http\Controllers\Api\CourierRuntimeController;
use App\Http\Controllers\Api\AdminMapController;
use App\Http\Controllers\Api\AdminRuntimeDiagnosticsController;
use App\Http\Controllers\Api\Payments\WayForPayCallbackController;
use App\Http\Controllers\Api\Client\OrderCompletionClientController;
use App\Http\Controllers\Api\Admin\OrderCompletionDisputeAdminController;


Route::post('/register', [RegisterController::class, 'register'])
    ->middleware('throttle:10,1');

Route::get('/geocode', [GeocodeController::class, 'search'])
    ->middleware('throttle:60,1');

Route::post('/payments/wayforpay/callback', WayForPayCallbackController::class)
    ->middleware('throttle:120,1');

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
    Route::get('/client/orders/{order}/completion-proof', [OrderCompletionClientController::class, 'show']);
    Route::post('/client/orders/{order}/completion-proof/confirm', [OrderCompletionClientController::class, 'confirm']);
    Route::post('/client/orders/{order}/completion-proof/disputes', [OrderCompletionClientController::class, 'openDispute']);

    // ADMIN/SUPPORT COMPLETION DISPUTES
    Route::get('/admin/completion-disputes', [OrderCompletionDisputeAdminController::class, 'index']);
    Route::get('/admin/completion-disputes/{dispute}', [OrderCompletionDisputeAdminController::class, 'show']);
    Route::post('/admin/completion-disputes/{dispute}/under-review', [OrderCompletionDisputeAdminController::class, 'markUnderReview']);
    Route::post('/admin/completion-disputes/{dispute}/resolve-confirmed', [OrderCompletionDisputeAdminController::class, 'resolveConfirmed']);
    Route::post('/admin/completion-disputes/{dispute}/resolve-rejected', [OrderCompletionDisputeAdminController::class, 'resolveRejected']);

    /*
    |--------------------------------------------------------------------------
    | COURIER ROUTES
    |--------------------------------------------------------------------------
    */

    // available orders for courier
    Route::get('/orders/available', [CourierOrderController::class, 'available'])
        ->middleware('observe.courier.runtime.endpoint:orders_available_api');

    // courier accepts order
    Route::post('/orders/{order}/accept', [CourierOrderController::class, 'accept']);

    // canonical courier runtime snapshot
    Route::get('/courier/runtime', [CourierRuntimeController::class, 'show'])
        ->middleware('observe.courier.runtime.endpoint:courier_runtime_api');
});


Route::middleware(['web', 'auth:web', AdminOnly::class])
    ->get('/admin/map-data', [AdminMapController::class, 'index']);

Route::middleware(['web', 'auth:web', AdminOnly::class])
    ->get('/dashboard/map', [AdminMapController::class, 'index']);

Route::middleware(['web', 'auth:web', AdminOnly::class])
    ->get('/admin/runtime-diagnostics', [AdminRuntimeDiagnosticsController::class, 'show']);
