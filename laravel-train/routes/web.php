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
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

// Các route nghiệp vụ yêu cầu đăng nhập
Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Nhóm quản lý người dùng gồm danh sách, khóa/mở khóa và form thêm/sửa.
    Route::get('/users', [UserController::class, 'index'])->name('users.index'); // Hiện danh sách người dùng cho tài khoản đăng nhập
    Route::post('/users', [UserController::class, 'handleListAction'])->name('users.actions');
    // GET mở form, POST xử lý kiểm tra dữ liệu và các bước xác nhận trên cùng form.
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create'); // mở form thêm người dùng
    Route::post('/users/create', [UserController::class, 'create'])->name('users.create.submit');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::post('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit.submit');
    // Từ chối phương thức không hỗ trợ và trả header Allow với GET, HEAD, POST.
    Route::match(['PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/users/{user}/edit', function (): void {
        abort(405, '', ['Allow' => 'GET, HEAD, POST']);
    });
    Route::match(['PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/users/create', function (): void {
        abort(405, '', ['Allow' => 'GET, HEAD, POST']);
    });
    Route::match(['PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/users', function (): void {
        abort(405, '', ['Allow' => 'GET, HEAD, POST']);
    });

    // Nhóm quản lý đơn gồm danh sách, thêm/sửa và xử lý duyệt/hủy.
    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves.index');
    Route::get('/leaves/create', [LeaveController::class, 'create'])->name('leaves.create'); // mở form tạo đơn
    Route::post('/leaves/create', [LeaveController::class, 'create'])->name('leaves.create.submit'); // xử lý các action form tạo đơn
    Route::get('/leaves/{leave}/edit', [LeaveController::class, 'edit'])->name('leaves.edit');
    Route::post('/leaves/{leave}/edit', [LeaveController::class, 'edit'])->name('leaves.edit.submit');
    // Chỉ hỗ trợ GET, HEAD và POST cho form sửa đơn.
    Route::match(['PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/leaves/{leave}/edit', function (): void {
        abort(405, '', ['Allow' => 'GET, HEAD, POST']);
    });
    Route::match(['PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/leaves/create', function (): void {
        abort(405, '', ['Allow' => 'GET, HEAD, POST']);
    });
    Route::post('/leaves/actions', [LeaveController::class, 'handleListAction'])->name('leaves.actions'); // Nhận các thao tác popup duyệt hoặc hủy đơn.
    Route::post('/leaves', [LeaveController::class, 'handleListAction']);
    Route::match(['PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/leaves', function (): void { // Từ chối các phương thức không hỗ trợ và trả header phương thức  được phép
        abort(405, '', ['Allow' => 'GET, HEAD, POST']);
    });
});

// POST thực hiện đăng xuất; các phương thức còn lại chỉ chuyển về trang login
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::match(['GET', 'HEAD', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/logout', [AuthController::class, 'logout']);

// Route fallback để xử lý các route không tồn tại
Route::fallback(function (): void {
    abort(404);
});
