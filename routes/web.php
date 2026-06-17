<?php

use Illuminate\Support\Facades\Route;

/*
 | Public marketing site (Blade).
 */
Route::view('/', 'home')->name('home');

// Placeholder until the Layer 1 application form is built.
Route::view('/daftar', 'daftar')->name('daftar');

/*
 | Admin panel (Vue SPA). A single Blade entrypoint boots the SPA; Vue Router
 | owns every nested path under /admin via history mode.
 */
Route::view('/admin/{any?}', 'admin')->where('any', '.*')->name('admin');
