<?php

namespace App\Http\Middleware;

use App\Constants\AppConstants;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ActiveUser
{
    // Kiểm tra tài khoản còn hoạt động trước khi tiếp tục request
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('logout')) {
            return $next($request); // Bỏ qua kiểm tra trạng thái tài khoản khi logout
        }

        $user = $request->user();

        if ($user !== null && $user->status !== AppConstants::STATUS_ACTIVE) { // Tài khoản bị khóa sẽ bị hủy đăng nhập, session
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login', status: 303);
        }

        return $next($request);
    }
}
