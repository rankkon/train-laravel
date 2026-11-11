<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\UserController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Trang gốc chuyển đến dashboard hoặc login theo trạng thái đăng nhập.
Route::get('/', function (): RedirectResponse {
    if (Auth::check()) {
        return redirect()->route('dashboard'); // Nếu người dùng đã đăng nhập, chuyển hướng đến trang dashboard
    }

    return redirect()->route('login');
})->name('home');

// Các route dành cho người dùng chưa đăng nhập
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

// Các route nghiệp vụ yêu cầu đăng nhập
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Nhóm quản lý người dùng gồm danh sách, khóa/mở khóa và form thêm/sửa.
Route::get('/users', [UserController::class, 'index'])->name('users.index'); // Hiện danh sách người dùng cho tài khoản đăng nhập
Route::post('/users', [UserController::class, 'store'])->name('users.store');
Route::get('/users/create', [UserController::class, 'create'])->name('users.create'); // mở form thêm người dùng
Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
Route::patch('/users/bulk-lock', [UserController::class, 'bulkLock'])->name('users.bulk-lock');
Route::patch('/users/{user}/lock', [UserController::class, 'lock'])->name('users.lock');
Route::patch('/users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');

// Nhóm quản lý đơn gồm danh sách, thêm/sửa và xử lý duyệt/hủy.
Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves.index');
Route::get('/leaves/create', [LeaveController::class, 'create'])->name('leaves.create'); // mở form tạo đơn
Route::post('/leaves', [LeaveController::class, 'store'])->name('leaves.store');
Route::get('/leaves/{leave}/edit', [LeaveController::class, 'edit'])->name('leaves.edit');
Route::put('/leaves/{leave}', [LeaveController::class, 'update'])->name('leaves.update');
Route::patch('/leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('leaves.approve');
Route::patch('/leaves/{leave}/cancel', [LeaveController::class, 'cancel'])->name('leaves.cancel');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
