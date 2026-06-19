<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\VerifyEmailController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\RoleController;
use Illuminate\Support\Facades\Route;

// Paystack webhook — unversioned, no auth required
Route::post('webhooks/paystack', [WebhookController::class, 'paystack']);

Route::prefix('v1')->group(function () {
    Route::get('config', ConfigController::class);

    // ── Auth (no token required) ──────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('login',    [AuthController::class, 'login']);
            Route::post('register', [AuthController::class, 'register']);
        });
        Route::middleware('throttle:5,1')->group(function () {
            Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
            Route::post('verify-otp',      [AuthController::class, 'verifyOtp']);
            Route::post('reset-password',  [AuthController::class, 'resetPassword']);
        });

        // Email verification
        Route::get('email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
        Route::post('email/resend', [VerifyEmailController::class, 'resend'])
            ->middleware(['auth:sanctum', 'throttle:6,1']);
    });

    // ── Authenticated — no email verification required ────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
    });

    // ── Authenticated + email verified ────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);

        // Orders
        Route::get('orders',         [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::put('orders/{order}', [OrderController::class, 'update']);
        Route::delete('orders/{order}', [OrderController::class, 'destroy']);
        Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:30,1');

        // Invoices
        Route::get('invoices',             [InvoiceController::class, 'index']);
        Route::get('invoices/{invoice}',   [InvoiceController::class, 'show']);

        // Notifications
        Route::get('notifications',                       [NotificationController::class, 'index']);
        Route::patch('notifications/read-all',            [NotificationController::class, 'markAllRead']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);

        // Tickets
        Route::get('tickets',          [TicketController::class, 'index']);
        Route::get('tickets/{ticket}', [TicketController::class, 'show']);
        Route::post('tickets', [TicketController::class, 'store'])->middleware('throttle:10,1');
        Route::post('tickets/{ticket}/messages', [TicketController::class, 'reply'])->middleware('throttle:20,1');

        // ── Admin ─────────────────────────────────────────────────────────────
        Route::middleware('role:admin')->prefix('admin')->group(function () {

            // Dashboard
            Route::middleware('permission:dashboard.view')->group(function () {
                Route::get('dashboard', [DashboardController::class, 'index']);
            });

            // Orders — read
            Route::middleware('permission:orders.view')->group(function () {
                Route::get('orders',                   [AdminOrderController::class, 'index']);
                Route::get('orders/{order}',           [AdminOrderController::class, 'show']);
                Route::get('orders/{order}/audit-log', [AdminOrderController::class, 'auditLog']);
            });

            // Orders — write
            Route::middleware('permission:orders.manage')->group(function () {
                Route::patch('orders/{order}/status',   [AdminOrderController::class, 'updateStatus']);
                Route::patch('orders/{order}/bid',      [AdminOrderController::class, 'updateBid']);
                Route::patch('orders/{order}/location', [AdminOrderController::class, 'updateLocation']);
            });

            // Invoices — read
            Route::middleware('permission:invoices.view')->group(function () {
                Route::get('invoices',           [AdminInvoiceController::class, 'index']);
                Route::get('invoices/{invoice}', [AdminInvoiceController::class, 'show']);
            });

            // Invoices — write
            Route::middleware('permission:invoices.manage')->group(function () {
                Route::post('orders/{order}/invoices', [AdminInvoiceController::class, 'store']);
            });

            // Users — read
            Route::middleware('permission:users.view')->group(function () {
                Route::get('users',         [UserController::class, 'index']);
                Route::get('users/{user}',  [UserController::class, 'show']);
            });

            // Users — write
            Route::middleware('permission:users.manage')->group(function () {
                Route::post('users',                    [UserController::class, 'store']);
                Route::put('users/{user}',              [UserController::class, 'update']);
                Route::delete('users/{user}',           [UserController::class, 'destroy']);
                Route::patch('users/{user}/archive',    [UserController::class, 'archive']);
            });

            // Roles — all operations (no separate view slug)
            Route::middleware('permission:roles.manage')->group(function () {
                Route::post('roles/assign',                [RoleController::class, 'assign']);
                Route::apiResource('roles', RoleController::class);
                Route::post('roles/{role}/users/{user}',   [RoleController::class, 'addUser']);
                Route::delete('roles/{role}/users/{user}', [RoleController::class, 'removeUser']);
            });

            // Support — read
            Route::middleware('permission:support.view')->group(function () {
                Route::get('tickets',          [AdminTicketController::class, 'index']);
                Route::get('tickets/{ticket}', [AdminTicketController::class, 'show']);
            });

            // Support — write
            Route::middleware('permission:support.manage')->group(function () {
                Route::post('tickets/{ticket}/messages', [AdminTicketController::class, 'reply']);
                Route::patch('tickets/{ticket}/status',  [AdminTicketController::class, 'updateStatus']);
            });

            // Settings
            Route::middleware('permission:settings.manage')->group(function () {
                Route::get('settings', [AdminSettingsController::class, 'index']);
                Route::put('settings', [AdminSettingsController::class, 'update']);
            });
        });
    });
});
