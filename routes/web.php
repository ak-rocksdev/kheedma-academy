<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\ProgramPageController;
use Illuminate\Support\Facades\Route;

/*
 | Public marketing site (Blade).
 */
Route::view('/', 'home')->name('home');

/*
 | Public funnel. /daftar is the two-door chooser (Task 6); each program has
 | its own landing + application form under a stable slug URL.
 */
Route::controller(ApplicationController::class)->group(function () {
    Route::get('/daftar/terima-kasih', 'thankYou')->name('daftar.thankyou');
    Route::get('/daftar/cities/{province}', 'cities')->where('province', '[0-9]{2}')->name('daftar.cities');
    Route::get('/program/{program:slug}/daftar', 'create')->name('program.apply');
    Route::post('/program/{program:slug}/daftar', 'store')->middleware('throttle:10,1')->name('program.apply.store');
});

Route::get('/daftar', [ProgramPageController::class, 'chooser'])->name('daftar');
Route::get('/program/{program:slug}', [ProgramPageController::class, 'show'])->name('program.show');

/*
 | Community door — join creates a participant account and signs in.
 */
Route::get('/komunitas', [CommunityController::class, 'show'])->name('komunitas');
Route::post('/komunitas', [CommunityController::class, 'join'])->middleware('throttle:10,1')->name('komunitas.join');

// Temporary target until Task 3 builds the real member area.
Route::get('/akun', fn () => redirect()->route('home'))->middleware('auth')->name('member.area');

/*
 | Admin panel (Vue SPA). A single Blade entrypoint boots the SPA; Vue Router
 | owns every nested path under /admin via history mode.
 */
Route::view('/admin/{any?}', 'admin')->where('any', '.*')->name('admin');
