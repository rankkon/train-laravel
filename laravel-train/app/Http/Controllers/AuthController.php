<?php

namespace App\Http\Controllers;

use App\Constants\AppConstants;
use App\Http\Middleware\ActiveUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware(ActiveUser::class)->except('logout');
        $this->middleware('guest')->only(['showLogin', 'login']);
        $this->middleware('auth')->only(['showPassword', 'setPassword']);
    }

    // Xóa phiên đăng nhập và chuyển về trang login
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout(); // Xóa trạng thái login, không nhận request tiếp từ tài khoản này
        $request->session()->invalidate(); // Xóa dữ liệu session cũ
        $request->session()->regenerateToken(); // tạo lại token mới

        return redirect()->route('login', status: 303);
    }

    // Hiển thị form đăng nhập
    public function showLogin(): View
    {
        return view('auth.login', [ // Dữ liệu mặc định cho form đăng nhập
            'username'          => '',
            'usernameError'     => false,
            'passwordError'     => false,
            'loginErrorMessage' => __('messages.login_failed'),
        ]);
    }

    // Chuyển hướng vào dashboard hoặc hiện popup đặt mật khẩu mới nếu login lần đầu
    public function showPassword(Request $request): View|RedirectResponse
    {
        return $request->user()->password_status === 0
            ? view('auth.set-password')
            : redirect()->route('dashboard');
    }

    // Kiểm tra thông tin và đăng nhập
    public function login(Request $request): View|RedirectResponse
    {
        if ($request->input('action') === 'clear') {
            return redirect()->route('login', status: 303); // Mở lại form đăng nhập mặc định
        }

        $validator = Validator::make($request->only('username', 'password'), [ // Chỉ nhận 2 trường từ request để truyền vào validator
            'username' => 'required',
            'password' => 'required',
        ]);
        $usernameError = $validator->errors()->has('username'); // Kiểm tra danh sách lỗi có lỗi của trường username
        $passwordError = $validator->errors()->has('password');
        $username = $usernameError ? '' : $request->input('username');
        $password = $request->input('password');
        $loginErrorMessage = __('messages.login_failed');

        if (! $usernameError && ! $passwordError) {
            $user = User::query()->where('username', $username)->first();

            if (! $user) { // Kiểm tra tài khoản tồn tại
                $usernameError = true;
            } elseif ($user->status !== AppConstants::STATUS_ACTIVE) { // Kiểm tra tài khoản hoạt động
                $usernameError = true;
                $loginErrorMessage = __('messages.account_locked');
            } elseif (! Hash::check($password, $user->password_hash)) { // Kiểm tra mật khẩu
                $passwordError = true;
            } else {
                Auth::login($user); // Đăng nhập thành công

                return redirect()->route($user->password_status === 0 ? 'password.edit' : 'dashboard', status: 303);
            }
        }

        return view('auth.login', [
            'username'          => $username,
            'usernameError'     => $usernameError,
            'passwordError'     => $passwordError,
            'loginErrorMessage' => $loginErrorMessage,
        ]);
    }

    // Cập nhật mật khẩu người dùng đặt lần đầu
    public function setPassword(Request $request): JsonResponse
    {
        DB::transaction(function () use ($request): void {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->status === AppConstants::STATUS_ACTIVE && $user->password_status === 0, 403); // Chỉ tiếp tục nếu user còn hoạt động và chưa đổi mật khẩu lần đầu
            $data = $request->validate([
                'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->symbols()], // Validate mật khẩu: phải trùng mật khẩu xác nhận và có 8 kí tự gồm chữ, số, kí tự
            ]);

            $user->update([ // Update mật khẩu vào database
                'password_hash'   => Hash::make($data['password']),
                'password_status' => 1,
                'update_at'       => now(),
            ]);
        });
        Auth::logout(); // Logout và chuyển về trang login
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['redirect' => route('login')]);
    }
}
