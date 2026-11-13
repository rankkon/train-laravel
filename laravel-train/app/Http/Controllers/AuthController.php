<?php

namespace App\Http\Controllers;

use App\Constants\AppConstants;
use App\Http\Middleware\ActiveUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware(ActiveUser::class)->except('logout');
        $this->middleware('guest')->only(['showLogin', 'login', 'setPassword']);
    }

    // Xóa phiên đăng nhập và chuyển về trang login
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate(); // Hủy phiên
        $request->session()->regenerateToken(); // tạo lại session token

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

    // Kiểm tra thông tin và đăng nhập
    public function login(Request $request): View|RedirectResponse
    {
        $request->session()->forget('password_setup_user_id'); // Bỏ chờ đặt lại mật khẩu của lần login trước

        if ($request->input('action') === 'clear') {
            return redirect()->route('login', status: 303); // Mở lại form đăng nhập mặc định
        }

        $validator = Validator::make($request->only('username', 'password'), [
            'username' => 'required',
            'password' => 'required',
        ]);
        $usernameError     = $validator->errors()->has('username');
        $passwordError     = $validator->errors()->has('password');
        $username         = $usernameError ? '' : $request->input('username');
        $password         = $request->input('password');
        $loginErrorMessage = __('messages.login_failed');

        if (! $usernameError && ! $passwordError) {
            $user = User::query()->where('username', $username)->first();

            if (! $user) { // Kiểm tra tài khoản tồn tại
                $usernameError = true;
            } elseif ($user->status !== AppConstants::STATUS_ACTIVE) { // Kiểm tra tài khoản hoạt động
                $usernameError     = true;
                $loginErrorMessage = __('messages.account_locked');
            } elseif (! Hash::check($password, $user->password_hash)) { // Kiểm tra mật khẩu
                $passwordError = true;
            } elseif ($user->password_status === 0) {
                $request->session()->put('password_setup_user_id', $user->id); // Ghi tài khoản mới chờ đặt lại mật khẩu khi login
            } else {
                Auth::login($user); // Đăng nhập thành công

                return redirect()->route('dashboard', status: 303);
            }
        }

        return view('auth.login', compact('username', 'usernameError', 'passwordError', 'loginErrorMessage'));
    }

    // Lưu mật khẩu người dùng đặt lần đầu
    public function setPassword(Request $request): JsonResponse
    {
        $user = User::whereKey($request->session()->get('password_setup_user_id'))
            ->where('status', AppConstants::STATUS_ACTIVE)
            ->where('password_status', 0)->firstOrFail();
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->symbols()],
        ]);

        $user->update([
            'password_hash'   => Hash::make($data['password']),
            'password_status' => 1,
            'update_at'       => now(),
        ]);
        $request->session()->forget('password_setup_user_id');

        return response()->json(['redirect' => route('login')]);
    }
}
