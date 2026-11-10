<?php

namespace App\Http\Controllers;

use App\Constants\AppConstants;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    // Xóa phiên đăng nhập và chuyển về trang login
    public function logout(Request $request): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            return redirect()->route('login');
        }

        $request->session()->invalidate(); // Hủy phiên
        Auth::forgetGuards(); // Bỏ qua các guard để không đọc lại từ phiên cũ
        Auth::logout();
        $request->session()->regenerateToken(); // tạo lại session token

        return redirect()->route('login', status: 303);
    }

    // Hiển thị form đăng nhập
    public function showLogin(): View
    {
        return view('login', [ // Dữ liệu mặc định cho form đăng nhập
            'username'          => '',
            'usernameError'     => false,
            'passwordError'     => false,
            'loginErrorMessage' => __('messages.login_failed'),
        ]);
    }

    // Kiểm tra thông tin và đăng nhập
    public function login(Request $request): View|RedirectResponse
    {
        if ($request->input('action') === 'clear') {
            return redirect()->route('login', status: 303); // Mở lại form đăng nhập mặc định
        }

        $username = $request->input('username');
        if (! is_string($username)) { // Chỉ nhận chuỗi
            $username = '';
        }
        $trimmedUsername = trim($username);
        if ($trimmedUsername === '') {
            $username = '';
        }
        $password = $request->input('password');
        if (! is_string($password)) { // Chỉ nhận chuỗi
            $password = '';
        }

        $usernameError     = $username === ''; // Kiểm tra username có rỗng không
        $passwordError     = $password === ''; // Kiểm tra password có rỗng không
        $loginErrorMessage = __('messages.login_failed');

        if (! $usernameError && ! $passwordError) {
            $user = User::query()->where('username', $username)->first();
            if ($user === null && $username !== $trimmedUsername) {
                $user = User::query()->where('username', $trimmedUsername)->first();
            }

            if (! $user) { // Kiểm tra tài khoản tồn tại
                $usernameError = true;
            } elseif ($user->status !== AppConstants::STATUS_ACTIVE) { // Kiểm tra tài khoản hoạt động
                $usernameError     = true;
                $loginErrorMessage = __('messages.account_locked');
            } elseif (! hash_equals($user->password_hash, md5($password))) { // Kiểm tra mật khẩu
                $passwordError = true;
            } else {
                Auth::login($user); // Đăng nhập thành công

                return redirect()->route('dashboard', status: 303);
            }
        }

        return view('login', compact('username', 'usernameError', 'passwordError', 'loginErrorMessage'));
    }
}
