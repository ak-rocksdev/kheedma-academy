<?php

use App\Http\Controllers\Api\Admin\ApplicantController;
use App\Http\Controllers\Api\Admin\CohortController;
use App\Http\Controllers\Api\Admin\CommunityMemberController;
use App\Http\Controllers\Api\Admin\EnrollmentController;
use App\Http\Controllers\Api\Admin\PersonController;
use App\Http\Controllers\Api\Admin\ProgramController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

/*
 | Admin panel API (Sanctum SPA — cookie + CSRF, same origin as /admin).
 */
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Staff-only operational modules (granular permissions).
    Route::prefix('admin')->group(function () {
        Route::get('/applications', [ApplicantController::class, 'index'])->middleware('permission:applications.view');
        Route::patch('/applications/{application}', [ApplicantController::class, 'update'])->middleware('permission:applications.review');
        Route::get('/people', [PersonController::class, 'index'])->middleware('permission:people.view');
        // Literal /people/merge-preview must register before the {person}
        // wildcard, or the wildcard swallows it as a (404) person id.
        Route::middleware('permission:people.merge')->group(function () {
            Route::get('/people/merge-preview', [PersonController::class, 'mergePreview']);
            Route::post('/people/merge', [PersonController::class, 'merge']);
        });
        Route::get('/people/{person}', [PersonController::class, 'show'])->middleware('permission:people.view');
        Route::patch('/people/{person}/account', [PersonController::class, 'updateAccount'])->middleware('permission:users.manage');

        Route::middleware('permission:users.manage')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::patch('/users/{user}', [UserController::class, 'update']);
            Route::delete('/users/{user}', [UserController::class, 'destroy']);
        });

        Route::get('/cohorts', [CohortController::class, 'index'])->middleware('permission:cohorts.view');
        Route::middleware('permission:cohorts.manage')->group(function () {
            Route::post('/cohorts', [CohortController::class, 'store']);
            Route::patch('/cohorts/{cohort}', [CohortController::class, 'update']);
            Route::delete('/cohorts/{cohort}', [CohortController::class, 'destroy']);
        });

        Route::middleware('permission:programs.manage')->group(function () {
            Route::get('/programs', [ProgramController::class, 'index']);
            Route::post('/programs', [ProgramController::class, 'store']);
            Route::patch('/programs/{program:id}', [ProgramController::class, 'update']);
            Route::delete('/programs/{program:id}', [ProgramController::class, 'destroy']);
        });

        Route::get('/community-members', [CommunityMemberController::class, 'index'])->middleware('permission:community.view');

        Route::middleware('permission:enrollments.manage')->group(function () {
            Route::post('/enrollments', [EnrollmentController::class, 'store']);
            Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy']);
            Route::post('/enrollments/{enrollment}/drop', [EnrollmentController::class, 'drop']);
        });
    });
});
