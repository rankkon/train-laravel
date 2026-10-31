<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ErrorController extends Controller
{
    /** Hiển thị trang lỗi theo mã HTTP. */
    public function show(Request $request): Response
    {
        $code = $request->query('code');
        $status = is_string($code) && in_array($code, ['400', '403', '404', '405', '409', '500'], true)
            ? (int) $code : 500;
        $response = response()->view('error', [], $status)->header('Cache-Control', 'no-store');

        if ($status === 405) {
            $response->header('Allow', 'GET, HEAD, POST');
        }

        return $response;
    }
}
