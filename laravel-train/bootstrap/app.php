<?php

use App\Http\Middleware\ActiveUser;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [ActiveUser::class], remove: [PreventRequestForgery::class]);
        $middleware->redirectGuestsTo(fn (): string => route('login'));
        $middleware->redirectUsersTo(fn (): string => route('dashboard'));
        $middleware->trimStrings(except: [
            fn (Request $request): bool => $request->is('login', 'error', 'leaves', 'leaves/actions'),
        ]);
        $middleware->convertEmptyStringsToNull(except: [
            fn (Request $request): bool => $request->is('login', 'error', 'leaves', 'leaves/actions'),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request): ?RedirectResponse {
            if ($request->routeIs('error') || $request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            if (! $request->hasSession()
                || ! in_array('web', $request->route()?->gatherMiddleware() ?? [], true)) {
                return null;
            }

            if ($exception instanceof AuthenticationException
                || $exception instanceof ValidationException
                || $exception instanceof HttpResponseException) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            if (! in_array($status, [400, 403, 404, 405, 409, 500], true)) {
                return null;
            }

            foreach (['user_create', 'leave_create'] as $form) {
                if ($request->session()->has($form.'_draft')) {
                    $request->session()->put($form.'_resume', true);
                    $request->session()->put($form.'_draft.confirmed', false);
                    $request->session()->forget($form.'_save_pending');
                }
            }

            return redirect()->route('error', ['code' => $status], 303);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
