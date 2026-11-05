<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $formTitle }} | Sanshin</title>
    <link rel="stylesheet" href="{{ asset('css/common.css') }}?v={{ filemtime(public_path('css/common.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/user_form.css') }}?v={{ filemtime(public_path('css/user_form.css')) }}">
</head>
<body class="user-create-page">
    <header class="site-header">
        <span class="site-brand">Sanshin</span>
        <span class="site-description">Hệ thống quản lý đơn</span>
    </header>

    @include('sidebar', ['sidebarFormId' => ''])

    <main class="user-create">
        <h1 class="user-form-title">{{ $formTitle }}</h1>

        <form method="post" action="{{ route('users.create') }}" id="user-form" class="user-form" novalidate>
            <div class="user-form-fields">
                <div class="user-form-field">
                    <label for="username">Tên đăng nhập <span class="required-marker">*</span></label>
                    <input type="text" id="username" name="username" value="{{ $values['username'] }}" required>
                </div>

                <div class="user-form-field">
                    <label for="full-name">Tên người dùng <span class="required-marker">*</span></label>
                    <input type="text" id="full-name" name="full_name" value="{{ $values['full_name'] }}" required>
                </div>

                <div class="user-form-field">
                    <label for="password">Mật khẩu <span class="required-marker">*</span></label>
                    <input type="password" id="password" name="password" value="{{ $values['password'] }}" required>
                </div>

                <div class="user-form-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ $values['email'] }}">
                </div>

                <div class="user-form-field user-form-date">
                    <label for="birth-date">Ngày sinh</label>
                    <input type="date" id="birth-date" name="birth_date" value="{{ $values['birth_date'] }}">
                </div>

                <div class="user-form-field">
                    <label for="user-type">Loại người dùng <span class="required-marker">*</span></label>
                    <div class="user-select-control">
                        <select id="user-type" name="user_type" required>
                            @foreach ($newUserTypes as $code => $name)
                                <option value="{{ $code }}" @selected($values['user_type'] === (string) $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="user-form-field">
                    <label for="department">Phòng ban <span class="required-marker">*</span></label>
                    <div class="user-select-control">
                        <select id="department" name="department" required>
                            @foreach ($newUserDepartments as $code => $name)
                                <option value="{{ $code }}" @selected($values['department'] === (string) $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="user-form-actions">
                <button type="button" class="user-form-next" disabled>Tiếp theo</button>
                <button type="button" class="user-form-clear" disabled>Xóa trống</button>
            </div>
        </form>
    </main>
</body>
</html>
