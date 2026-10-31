<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ErrorController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function (): RedirectResponse {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth')->name('dashboard');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::match(['GET', 'HEAD', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/logout', [AuthController::class, 'logout']);
Route::get('/error', [ErrorController::class, 'show'])->name('error');

Route::fallback(function (): void {
    abort(404);
});
