<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Support\RouteHelpers;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        RouteHelpers::permissionGatedResource('categories', CategoryController::class, 'categories');
        RouteHelpers::permissionGatedResource('brands', BrandController::class, 'brands');
        RouteHelpers::permissionGatedResource('units', UnitController::class, 'units');
        RouteHelpers::permissionGatedResource('warehouses', WarehouseController::class, 'warehouses');
        RouteHelpers::permissionGatedResource('suppliers', SupplierController::class, 'suppliers');
        RouteHelpers::permissionGatedResource('customers', CustomerController::class, 'customers');
        RouteHelpers::permissionGatedResource('products', ProductController::class, 'products');
    });
});
