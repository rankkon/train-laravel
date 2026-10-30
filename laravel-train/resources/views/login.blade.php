<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập | Sanshin</title>
    <link rel="stylesheet" href="{{ asset('css/common.css') }}?v={{ filemtime(public_path('css/common.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
</head>
<body class="login-page">
    <div class="login-surface">
        <header class="site-header">
            <span class="site-brand">Sanshin</span>
            <span class="site-description">Hệ thống quản lý đơn</span>
        </header>

        <main class="login-container">
            <h1 class="login-title">Sanshin IT Solution</h1>

            <form method="post" action="{{ route('login.submit') }}" novalidate>
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
                    <span class="login-placeholder">Tên đăng nhập</span>
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
                </div>

                <div class="login-actions">
                    <button class="button-login">
                        <span class="button-text">Login</span>
                    </button>
                    <button class="button-clear" name="action" value="clear">
                        <span class="button-text">Clear</span>
                    </button>
                </div>

                @if ($usernameError || $passwordError)
                    <p class="login-error">{{ $loginErrorMessage }}</p>
                @endif
            </form>
        </main>
    </div>
</body>
</html>
