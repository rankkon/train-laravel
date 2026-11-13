@extends('layouts.base')

@section('title', 'Đăng nhập')
@section('body-class', 'login-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
@endpush

@section('body')
    <div class="login-surface">
        @include('layouts.header')

        <main class="login-container">
            <h1 class="login-title">Sanshin IT Solution</h1>

            {{-- Form gửi POST và hiển thị lỗi do server trả về; không điền lại mật khẩu sau lần đăng nhập lỗi. --}}
            <form method="post" action="{{ route('login.submit') }}" novalidate>
                @csrf
                <div class="login-field login-username">
                    <label for="username">Tên đăng nhập</label>
                    <span class="required-marker">*</span>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="{{ $username }}"
                        @if ($usernameError) class="login-input-error" autofocus @endif
                        placeholder=" "
                        required
                    >
                    <span class="login-placeholder">Tên đăng nhập</span> {{-- Placeholder được CSS ẩn khi ô nhập có dữ liệu. --}}
                </div>

                <div class="login-field login-password">
                    <label for="password">Mật khẩu</label>
                    <span class="required-marker">*</span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        @if ($passwordError) class="login-input-error" @endif
                        @if ($passwordError && ! $usernameError) autofocus @endif
                        placeholder=" "
                        required
                    >
                    <span class="login-placeholder">Mật khẩu</span>
                    @include('components.password-toggle') {{-- Dùng chung nút ẩn/hiện mật khẩu, không gửi form. --}}
                </div>

                <div class="login-actions">
                    <button class="button-login">
                        <span class="button-text">Login</span>
                    </button>
                    <button class="button-clear" name="action" value="clear">
                        <span class="button-text">Clear</span>
                    </button>
                </div>

                {{-- Hiển thị thông báo khi thiếu dữ liệu, sai thông tin đăng nhập hoặc tài khoản bị khóa. --}}
                @if ($usernameError || $passwordError)
                    <p class="login-error">{{ $loginErrorMessage }}</p>
                @endif
            </form>
        </main>
    </div>
    @if (session()->has('password_setup_user_id'))
        @include('auth.set-password')
    @endif
@endsection
