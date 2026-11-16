<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\PaymentVerificationController;
use App\Http\Controllers\Api\RecipeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PUBLIC (customers)
|--------------------------------------------------------------------------
*/

Route::post(
    '/orders/{order}/gcash',
    [PaymentVerificationController::class, 'submitReference']
);

Route::post('/login', [AuthController::class, 'login']);
Route::get('/tables/{token}/orders', [OrderController::class, 'byTable']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/tables/{token}', [TableController::class, 'showByToken']);
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/track/{orderNumber}', [OrderController::class, 'track']);
Route::post('/reservations', [ReservationController::class, 'store']);
Route::get('/reservations/track/{referenceCode}', [ReservationController::class, 'track']);

/*
|--------------------------------------------------------------------------
| STAFF / CASHIER (requires login token)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('cashier')->group(function () {
    Route::get(
        '/payments/pending',
        [PaymentVerificationController::class, 'pending']
    );

    Route::patch(
        '/payments/{payment}/verify',
        [PaymentVerificationController::class, 'verify']
    );

    Route::patch(
        '/payments/{payment}/reject',
        [PaymentVerificationController::class, 'reject']
    );
});

Route::middleware('auth:sanctum')->prefix('staff')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/summary', [DashboardController::class, 'summary']);

    // Menu management
    Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy']);

    // Recipes
    Route::get('/recipes', [RecipeController::class, 'index']);
    Route::put('/products/{product}/ingredients', [RecipeController::class, 'sync']);
    Route::post('/products/{product}/ingredients', [RecipeController::class, 'sync']);
    Route::post('/upload-image', [ProductController::class, 'uploadImage']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/{order}/record-cash', [OrderController::class, 'recordCash']);

    // Reservations
    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::patch('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus']);

    // Payments
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments', [PaymentController::class, 'store']);

    // Tables (QR codes)
    Route::get('/tables', [TableController::class, 'index']);
    Route::post('/tables', [TableController::class, 'store']);
    Route::delete('/tables/{table}', [TableController::class, 'destroy']);

    // Inventory
    Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock']);
    Route::get('/inventory/movements', [InventoryController::class, 'movements']);
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory', [InventoryController::class, 'store']);
    Route::put('/inventory/{item}', [InventoryController::class, 'update']);
    Route::delete('/inventory/{item}', [InventoryController::class, 'destroy']);
    Route::post('/inventory/{item}/adjust', [InventoryController::class, 'adjust']);
});