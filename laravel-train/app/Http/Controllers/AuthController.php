<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** Hiển thị form đăng nhập. */
    public function showLogin(): View
    {
        return view('login', [
            'username' => '',
            'usernameError' => false,
            'passwordError' => false,
            'loginErrorMessage' => __('messages.login_failed'),
        ]);
    }

    /** Kiểm tra thông tin và đăng nhập. */
    public function login(Request $request): View|RedirectResponse
    {
        if ($request->input('action') === 'clear') {
            return redirect()->route('login', status: 303);
        }

        $username = $request->input('username');
        if (! is_string($username)) {
            $username = '';
        }
        $username = trim($username);

        $password = $request->input('password');
        if (! is_string($password)) {
            $password = '';
        }

        $usernameError = $username === '';
        $passwordError = $password === '';
        $loginErrorMessage = __('messages.login_failed');

        if (! $usernameError && ! $passwordError) {
            $user = User::query()->where('username', $username)->first();

            if (! $user) {
                $usernameError = true;
            } elseif ($user->status !== User::STATUS_ACTIVE) {
                $usernameError = true;
                $loginErrorMessage = __('messages.account_locked');
            } elseif (! hash_equals($user->password_hash, md5($password))) {
                $passwordError = true;
            } else {
                Auth::login($user);

                return redirect()->route('dashboard', status: 303);
            }
        }

        return view('login', compact('username', 'usernameError', 'passwordError', 'loginErrorMessage'));
    }
}
