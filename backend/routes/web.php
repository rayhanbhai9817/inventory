<?php

use Illuminate\Support\Facades\Route;

// This backend is API-only (see routes/api.php) — the root route exists
// only so http(s)://this-domain/ returns something sane (e.g. for
// uptime checks) instead of Laravel's default Vite-built welcome page,
// which would 500 in production since nothing here ever builds
// resources/css|js via Vite.
Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'service' => config('app.name'),
        'message' => 'API is running. See /api/v1 for endpoints.',
    ]);
});
