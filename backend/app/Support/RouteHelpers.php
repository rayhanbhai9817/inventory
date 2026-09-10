<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

class RouteHelpers
{
    /**
     * Registers a permission-gated apiResource for a module.
     *
     * Route::apiResource(...)->middleware($assocArray) does NOT scope
     * middleware per action — it ignores the array keys and applies every
     * value to every route (see PendingResourceRegistration::middleware()).
     * middlewareFor() is the actual per-action API.
     */
    public static function permissionGatedResource(string $uri, string $controller, string $module): void
    {
        Route::apiResource($uri, $controller)
            ->middlewareFor(['index', 'show'], "permission:{$module}.view,sanctum")
            ->middlewareFor('store', "permission:{$module}.create,sanctum")
            ->middlewareFor('update', "permission:{$module}.edit,sanctum")
            ->middlewareFor('destroy', "permission:{$module}.delete,sanctum");
    }
}
