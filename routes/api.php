<?php

use App\Http\Controllers\Api\Admin\ApplicantController;
use App\Http\Controllers\Api\Admin\PersonController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
 | Admin panel API (Sanctum SPA — cookie + CSRF, same origin as /admin).
 */
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Staff-only operational modules (granular permissions).
    Route::prefix('admin')->group(function () {
        Route::get('/applications', [ApplicantController::class, 'index'])->middleware('permission:applications.view');
        Route::patch('/applications/{application}', [ApplicantController::class, 'update'])->middleware('permission:applications.review');
        Route::get('/people/{person}', [PersonController::class, 'show'])->middleware('permission:people.view');
    });
});
