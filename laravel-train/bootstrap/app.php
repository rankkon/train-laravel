<?php

use App\Http\Middleware\ActiveUser;
use App\Http\Middleware\PostSize;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(ValidatePostSize::class, PostSize::class);
        $middleware->web(append: [ActiveUser::class], remove: [PreventRequestForgery::class]);
        $middleware->redirectGuestsTo(fn (): string => route('login'));
        $middleware->redirectUsersTo(fn (): string => route('dashboard'));
        $middleware->trimStrings(except: [
            fn (Request $request): bool => $request->is('login', 'leaves', 'leaves/actions', 'leaves/create'),
        ]);
        $middleware->convertEmptyStringsToNull(except: [
            fn (Request $request): bool => $request->is('login', 'leaves', 'leaves/actions', 'leaves/create'),
        ]);
    })
    ->withExceptions()
    ->create();
