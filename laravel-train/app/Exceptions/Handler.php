<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    // Thiết lập exception cần ghi log, tránh log trùng và chọn phản hồi JSON khi phù hợp
    public function register(): void
    {
        $this->stopIgnoring($this->internalDontReport);
        $this->dontReport([
            AuthenticationException::class, ValidationException::class,
        ]);
        $this->dontReportWhen(fn (Throwable $exception): bool => $exception instanceof HttpResponseException
            && $exception->getResponse()->getStatusCode() < 400);
        $this->dontReportDuplicates();
        $this->reportable(function (QueryException $exception): ?bool { // Ghi lỗi database và thông tin lỗi, không ghi kèm SQL chứa hash mật khẩu
            if (! str_contains(strtolower($exception->getSql()), 'password_hash')) {
                return null;
            }

            Log::error($exception->getPrevious()->getMessage(), $this->buildExceptionContext($exception));

            return false;
        });
    }

    // Hiển thị giao diện lỗi cho người dùng, không hiển thị chi tiết lỗi
    protected function renderExceptionResponse(mixed $request, Throwable $exception): Response
    {
        // Giữ định dạng JSON cho API hoặc request yêu cầu JSON thay vì dựng trang HTML.
        if ($this->shouldReturnJson($request, $exception)) {
            return parent::renderExceptionResponse($request, $exception);
        }

        $status  = 500;
        $headers = [];
        if ($exception instanceof HttpExceptionInterface) {
            $status  = $exception->getStatusCode();
            $headers = $exception->getHeaders();
        }

        // Dựng trang lỗi chung với mã HTTP và header của exception.
        try {
            return response()->view('errors.common', [], $status, $headers)->header('Cache-Control', 'no-store');
        } catch (Throwable $renderError) {
            report($renderError);

            return response(__('messages.page_error'), 500)->header('Cache-Control', 'no-store'); // Phản hồi văn bản dự phòng khi không dựng được trang lỗi.
        }
    }

    // Tạo thông tin lỗi để ghi log
    protected function buildExceptionContext(Throwable $exception): array
    {
        $context           = $this->context();
        $httpException     = $this->prepareException($exception);
        $context['status'] = 500;
        if ($exception instanceof HttpResponseException) {
            $context['status'] = $exception->getResponse()->getStatusCode();
        } elseif ($httpException instanceof HttpExceptionInterface) {
            $context['status'] = $httpException->getStatusCode();
        }
        $context['exception_type'] = $exception::class;
        $context['file']           = $exception->getFile();
        $context['line']           = $exception->getLine();
        $cause                     = $exception;
        // Lần theo chuỗi exception để lấy nguyên nhân gốc giúp kiểm tra log.
        while ($cause->getPrevious() !== null) {
            $cause = $cause->getPrevious();
        }
        if ($cause !== $exception) {
            $context['cause_type']    = $cause::class;
            $context['cause_message'] = $cause->getMessage();
        }

        return $context;
    }

    // Lấy thông tin request mà không truy vấn database
    protected function context(): array
    {
        if (! $this->container->bound('request')) {
            return [];
        }

        $request = $this->container->make('request');
        $context = ['method' => $request->method(), 'path' => $request->path()];
        $guard   = $this->container->make('auth')->guard();
        // Lấy ID từ guard đã có hoặc session để tránh phát sinh truy vấn database khi đang xử lý lỗi.
        if ($guard instanceof SessionGuard) {
            if ($guard->hasUser()) {
                $context['user_id'] = $guard->user()->getAuthIdentifier();
            } elseif ($request->hasSession()) {
                $context['user_id'] = $request->session()->get($guard->getName());
            }
        }

        return $context;
    }
}
