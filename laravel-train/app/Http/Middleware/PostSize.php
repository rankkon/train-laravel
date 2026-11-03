<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PostSize
{
    // Kiểm tra kích thước dữ liệu POST trước khi tiếp tục request
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('post') && $request->is('leaves/create')) { // Bỏ qua kiểm tra kích thước dữ liệu POST khi tạo đơn
            return $next($request);
        }

        return (new ValidatePostSize)->handle($request, $next);
    }
}
