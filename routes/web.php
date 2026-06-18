<?php

use App\Http\Controllers\ApplicationController;
use Illuminate\Support\Facades\Route;

/*
 | Public marketing site (Blade).
 */
Route::view('/', 'home')->name('home');

/*
 | Layer 1 — application form.
 */
Route::controller(ApplicationController::class)->group(function () {
    Route::get('/daftar', 'create')->name('daftar');
    Route::get('/daftar/terima-kasih', 'thankYou')->name('daftar.thankyou');
    Route::get('/daftar/cities/{province}', 'cities')->where('province', '[0-9]{2}')->name('daftar.cities');
    Route::post('/daftar', 'store')->middleware('throttle:10,1')->name('daftar.store');
});

/*
 | Admin panel (Vue SPA). A single Blade entrypoint boots the SPA; Vue Router
 | owns every nested path under /admin via history mode.
 */
Route::view('/admin/{any?}', 'admin')->where('any', '.*')->name('admin');
