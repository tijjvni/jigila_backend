<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('config', ConfigController::class);
Route::post('webhooks/paystack', [WebhookController::class, 'paystack']);

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);

    Route::apiResource('orders', OrderController::class);

    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);

    Route::get('tickets', [TicketController::class, 'index']);
    Route::post('tickets', [TicketController::class, 'store']);
    Route::get('tickets/{ticket}', [TicketController::class, 'show']);
    Route::post('tickets/{ticket}/messages', [TicketController::class, 'reply']);

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index']);

        // Users
        Route::apiResource('users', UserController::class);
        Route::patch('users/{user}/archive', [UserController::class, 'archive']);

        // Orders
        Route::get('orders', [AdminOrderController::class, 'index']);
        Route::get('orders/{order}', [AdminOrderController::class, 'show']);
        Route::patch('orders/{order}/status',   [AdminOrderController::class, 'updateStatus']);
        Route::patch('orders/{order}/bid',      [AdminOrderController::class, 'updateBid']);
        Route::patch('orders/{order}/location', [AdminOrderController::class, 'updateLocation']);

        // Invoices
        Route::get('invoices', [AdminInvoiceController::class, 'index']);
        Route::get('invoices/{invoice}', [AdminInvoiceController::class, 'show']);
        Route::post('orders/{order}/invoices', [AdminInvoiceController::class, 'store']);

        // Tickets
        Route::get('tickets', [AdminTicketController::class, 'index']);
        Route::get('tickets/{ticket}', [AdminTicketController::class, 'show']);
        Route::post('tickets/{ticket}/messages', [AdminTicketController::class, 'reply']);
        Route::patch('tickets/{ticket}/status', [AdminTicketController::class, 'updateStatus']);

        // Settings
        Route::get('settings', [AdminSettingsController::class, 'index']);
        Route::put('settings', [AdminSettingsController::class, 'update']);

        // Roles (assign must precede the resource to avoid {role} capturing "assign")
        Route::post('roles/assign',                 [RoleController::class, 'assign']);
        Route::apiResource('roles', RoleController::class);
        Route::post('roles/{role}/users/{user}',    [RoleController::class, 'addUser']);
        Route::delete('roles/{role}/users/{user}',  [RoleController::class, 'removeUser']);
    });
});
