<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaveController;
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

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves.index');
    Route::get('/leaves/create', [LeaveController::class, 'create'])->name('leaves.create');
    Route::post('/leaves/create', [LeaveController::class, 'create'])->name('leaves.create.submit');
    Route::match(['PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/leaves/create', function (): void {
        abort(405, '', ['Allow' => 'GET, HEAD, POST']);
    });
    Route::post('/leaves/actions', [LeaveController::class, 'handleListAction'])->name('leaves.actions');
    Route::post('/leaves', [LeaveController::class, 'handleListAction']);
    Route::match(['PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/leaves', function (): void {
        abort(405, '', ['Allow' => 'GET, HEAD, POST']);
    });
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::match(['GET', 'HEAD', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/logout', [AuthController::class, 'logout']);

Route::fallback(function (): void {
    abort(404);
});
