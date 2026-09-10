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
use App\Http\Controllers\Api\V1\ProductPriceController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StockAdjustmentController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\StockLedgerController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\SupplierProductController;
use App\Http\Controllers\Api\V1\SupplierReportController;
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

        // --- Suppliers & Supplier-Product relationships -------------------------
        Route::middleware('permission:suppliers.view,sanctum')->group(function () {
            Route::get('/suppliers', [SupplierController::class, 'index']);
            Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
            Route::get('/supplier-products', [SupplierProductController::class, 'index']);
        });
        Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('permission:suppliers.create,sanctum');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:suppliers.update,sanctum');
        Route::middleware('permission:suppliers.archive,sanctum')->group(function () {
            Route::post('/suppliers/{supplier}/archive', [SupplierController::class, 'archive']);
            Route::post('/suppliers/{supplier}/restore', [SupplierController::class, 'restore']);
        });

        Route::middleware('permission:suppliers.update,sanctum')->group(function () {
            Route::post('/supplier-products', [SupplierProductController::class, 'store']);
            Route::put('/supplier-products/{supplierProduct}', [SupplierProductController::class, 'update']);
            Route::delete('/supplier-products/{supplierProduct}', [SupplierProductController::class, 'destroy']);
        });

        // --- Product Pricing (reference data — never touches inventory) --------
        Route::get('/product-price-catalog', [ProductPriceController::class, 'catalog'])
            ->middleware('permission:product_prices.view,sanctum');
        Route::get('/products/{product}/prices', [ProductPriceController::class, 'index'])
            ->middleware('permission:product_prices.history,sanctum');
        Route::post('/products/{product}/prices', [ProductPriceController::class, 'store'])
            ->middleware('permission:product_prices.create,sanctum');
        Route::put('/products/{product}/prices/{price}', [ProductPriceController::class, 'update'])
            ->middleware('permission:product_prices.update,sanctum');

        // --- Dashboard / Activity / Audit ---------------------------------------
        Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view,sanctum');
        Route::get('/activity', [ActivityController::class, 'index'])->middleware('permission:activity.view,sanctum');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view,sanctum');

        // --- Reports (thin, permission-gated views over the above) -------------
        Route::get('/reports/inventory-summary', [InventoryController::class, 'index'])->middleware('permission:reports.view,sanctum');
        Route::get('/reports/stock-movement', [StockLedgerController::class, 'index'])->middleware('permission:reports.view,sanctum');
        Route::get('/reports/batches', [BatchController::class, 'index'])->middleware('permission:reports.view,sanctum');
        Route::get('/reports/daily-activity', [ActivityController::class, 'index'])->middleware('permission:reports.view,sanctum');

        Route::middleware('permission:supplier_reports.view,sanctum')->group(function () {
            Route::get('/reports/suppliers', [SupplierReportController::class, 'suppliers']);
            Route::get('/reports/product-suppliers', [SupplierReportController::class, 'productSuppliers']);
            Route::get('/reports/stock-in-by-supplier', [BatchController::class, 'index']);
        });
        Route::get('/reports/product-prices', [ProductPriceController::class, 'catalog'])
            ->middleware('permission:product_prices.view,sanctum');

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
