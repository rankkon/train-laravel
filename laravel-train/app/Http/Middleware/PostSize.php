<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PostSize
{
    /** Chuyển lỗi dữ liệu tạo đơn về form. */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('post') && $request->is('leaves/create')) {
            return $next($request);
        }

        return (new ValidatePostSize)->handle($request, $next);
    }
}
