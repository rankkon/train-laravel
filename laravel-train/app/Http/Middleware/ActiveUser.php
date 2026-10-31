<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ActiveUser
{
    /** Hủy phiên của tài khoản không hoạt động. */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('error') || $request->is('logout')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user !== null && $user->status !== User::STATUS_ACTIVE) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login', status: 303);
        }

        return $next($request);
    }
}
