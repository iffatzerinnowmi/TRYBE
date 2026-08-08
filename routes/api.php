<?php

use App\Http\Controllers\Api\V1\AuthApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TRYBE API — v1
|--------------------------------------------------------------------------
| Every route here is automatically prefixed with /api by Laravel,
| and we add /v1 on top. So: /api/v1/auth/login
*/

Route::prefix('v1')->group(function () {

    // ---- Public ----
    Route::post('auth/register', [AuthApiController::class, 'register']);
    Route::post('auth/login',    [AuthApiController::class, 'login']);

    // ---- Requires Bearer token ----
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthApiController::class, 'logout']);
        Route::get('auth/me',      [AuthApiController::class, 'me']);

        // Step 4 (reliability endpoints) will be added here.
    });
});