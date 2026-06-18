<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\ApplicantController;
use App\Http\Controllers\Api\Admin\PersonController;
use Illuminate\Support\Facades\Route;

/*
 | Admin panel API (Sanctum SPA — cookie + CSRF, same origin as /admin).
 */
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Staff-only operational modules.
    Route::middleware('role:admin|mentor')->prefix('admin')->group(function () {
        Route::get('/applications', [ApplicantController::class, 'index']);
        Route::patch('/applications/{application}', [ApplicantController::class, 'update']);
        Route::get('/people/{person}', [PersonController::class, 'show']);
    });
});
