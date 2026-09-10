<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BatchController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StockAdjustmentController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\StockLedgerController;
use App\Http\Controllers\Api\V1\UserController;
use App\Support\RouteHelpers;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // --- Product Management -------------------------------------------------
        RouteHelpers::permissionGatedResource('categories', CategoryController::class, 'categories');

        Route::middleware('permission:products.view,sanctum')->group(function () {
            Route::get('/products', [ProductController::class, 'index']);
            Route::get('/products/{product}', [ProductController::class, 'show']);
        });
        Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create,sanctum');
        Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit,sanctum');
        Route::middleware('permission:products.delete,sanctum')->group(function () {
            Route::post('/products/{product}/archive', [ProductController::class, 'archive']);
            Route::post('/products/{product}/restore', [ProductController::class, 'restore']);
            Route::delete('/products/{product}', [ProductController::class, 'destroy']);
            Route::post('/products/trashed/{productId}/restore', [ProductController::class, 'restoreFromTrash']);
        });

        // --- Inventory / Batches / Stock movement -------------------------------
        Route::get('/inventory', [InventoryController::class, 'index'])->middleware('permission:inventory.view,sanctum');
        Route::get('/inventory/{product}', [InventoryController::class, 'show'])->middleware('permission:inventory.view,sanctum');

        Route::get('/batches', [BatchController::class, 'index'])->middleware('permission:batches.view,sanctum');
        Route::get('/batches/{batch}', [BatchController::class, 'show'])->middleware('permission:batches.view,sanctum');

        Route::post('/stock/in', [StockController::class, 'in'])->middleware('permission:stock.in,sanctum');
        Route::post('/stock/out', [StockController::class, 'out'])->middleware('permission:stock.out,sanctum');

        Route::middleware('permission:stock.adjust,sanctum')->group(function () {
            Route::get('/stock/adjustments', [StockAdjustmentController::class, 'index']);
            Route::post('/stock/adjustments', [StockAdjustmentController::class, 'store']);
        });

        Route::get('/stock-ledger', [StockLedgerController::class, 'index'])->middleware('permission:ledger.view,sanctum');

        // --- Dashboard / Activity / Audit ---------------------------------------
        Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view,sanctum');
        Route::get('/activity', [ActivityController::class, 'index'])->middleware('permission:activity.view,sanctum');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view,sanctum');

        // --- Reports (thin, permission-gated views over the above) -------------
        Route::get('/reports/inventory-summary', [InventoryController::class, 'index'])->middleware('permission:reports.view,sanctum');
        Route::get('/reports/stock-movement', [StockLedgerController::class, 'index'])->middleware('permission:reports.view,sanctum');
        Route::get('/reports/batches', [BatchController::class, 'index'])->middleware('permission:reports.view,sanctum');
        Route::get('/reports/daily-activity', [ActivityController::class, 'index'])->middleware('permission:reports.view,sanctum');

        // --- Administration -------------------------------------------------------
        // Notifications have no dedicated permission gate beyond being
        // authenticated — they're personal/business-wide, not a privileged module.
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

        Route::middleware('permission:users.view,sanctum')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::get('/users/{user}', [UserController::class, 'show']);
        });
        Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create,sanctum');
        Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.edit,sanctum');
        Route::patch('/users/{user}/status', [UserController::class, 'setStatus'])->middleware('permission:users.edit,sanctum');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:users.edit,sanctum');

        Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view,sanctum');

        Route::get('/settings', [SettingsController::class, 'show'])->middleware('permission:settings.manage,sanctum');
        Route::put('/settings', [SettingsController::class, 'update'])->middleware('permission:settings.manage,sanctum');
    });
});
