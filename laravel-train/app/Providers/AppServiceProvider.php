<?php

namespace App\Providers;

use App\Exceptions\Handler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Đăng ký thành phần ứng dụng. */
    public function register(): void
    {
        $this->app->singleton(ExceptionHandler::class, Handler::class);
    }

    /** Khởi tạo thành phần ứng dụng. */
    public function boot(): void {}
}
