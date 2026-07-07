<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\MemberAreaController;
use App\Http\Controllers\MemberAuthController;
use App\Http\Controllers\MemberPasswordController;
use App\Http\Controllers\ProgramPageController;
use App\Http\Middleware\RedirectNonStaffFromAdmin;
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
    Route::post('/program/{program:slug}/daftar', 'store')->middleware(['throttle:10,1', 'precognitive'])->name('program.apply.store');
});

Route::get('/daftar', [ProgramPageController::class, 'chooser'])->name('daftar');
Route::get('/program/{program:slug}', [ProgramPageController::class, 'show'])->name('program.show');

/*
 | Community door — join creates a participant account and signs in.
 */
Route::get('/komunitas', [CommunityController::class, 'show'])->name('komunitas');
Route::post('/komunitas', [CommunityController::class, 'join'])->middleware(['throttle:10,1', 'precognitive'])->name('komunitas.join');

/*
 | Member area (participants). Staff are redirected to /admin.
 */
Route::get('/masuk', [MemberAuthController::class, 'showLogin'])->name('member.login');
Route::post('/masuk', [MemberAuthController::class, 'login'])->middleware('throttle:10,1')->name('member.login.store');
Route::post('/keluar', [MemberAuthController::class, 'logout'])->name('member.logout');
Route::get('/akun', [MemberAreaController::class, 'index'])->middleware('auth')->name('member.area');

/*
 | Member password reset. The GET reset route MUST be named password.reset —
 | Laravel's reset notification builds its URL from that name.
 */
Route::get('/lupa-password', [MemberPasswordController::class, 'requestForm'])->name('member.password.request');
Route::post('/lupa-password', [MemberPasswordController::class, 'sendLink'])->middleware('throttle:6,1')->name('member.password.email');
Route::get('/reset-password/{token}', [MemberPasswordController::class, 'resetForm'])->name('password.reset');
Route::post('/reset-password', [MemberPasswordController::class, 'update'])->middleware('throttle:6,1')->name('member.password.update');

/*
 | Admin panel (Vue SPA). A single Blade entrypoint boots the SPA; Vue Router
 | owns every nested path under /admin via history mode.
 */
Route::view('/admin/{any?}', 'admin')->where('any', '.*')->middleware(RedirectNonStaffFromAdmin::class)->name('admin');
