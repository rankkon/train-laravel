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

    @include('sidebar', ['sidebarFormId' => 'user-form'])

    <main class="user-create">
        <h1 class="user-form-title">{{ $formTitle }}</h1>

        {{-- Form dùng chung cho thêm và sửa người dùng; các thao tác được xử lý bằng POST. --}}
        <form method="post" action="{{ $formAction }}" id="user-form" class="user-form" novalidate>
            {{-- Gửi dữ liệu gốc qua hidden input để so sánh và phục hồi form mà không lưu session. --}}
            @foreach ($originalValues as $field => $value)
                <input type="hidden" name="original_values[{{ $field }}]" value="{{ $value }}">
            @endforeach
            <input type="hidden" name="exit_destination" value="{{ $exitDestination }}"> {{-- Chỉ phục vụ bước xác nhận chuyển trang, controller kiểm tra lại route đích. --}}
            <div class="user-form-fields">
                <div class="user-form-field">
                    <label for="username">Tên đăng nhập <span class="required-marker">*</span></label> {{-- Username không được thay đổi khi sửa; service vẫn kiểm tra trên server. --}}
                    <input type="text" id="username" name="username" value="{{ $values['username'] }}" required
                        @if ($editedUserId > 0) disabled @endif
                        @if (isset($errors['username'])) class="user-input-error" @endif>
                    <p class="user-field-error" data-form-error="username" @if (! isset($errors['username'])) hidden @endif>{{ $errors['username'] ?? '' }}</p>
                </div>

                <div class="user-form-field">
                    <label for="full-name">Tên người dùng <span class="required-marker">*</span></label>
                    <input type="text" id="full-name" name="full_name" value="{{ $values['full_name'] }}" required
                        @if (isset($errors['full_name'])) class="user-input-error" @endif>
                    <p class="user-field-error" data-form-error="full_name" @if (! isset($errors['full_name'])) hidden @endif>{{ $errors['full_name'] ?? '' }}</p>
                </div>

                <div class="user-form-field">
                    <label for="password">
                        @if ($editedUserId > 0)
                            Mật khẩu mới
                        @else
                            Mật khẩu <span class="required-marker">*</span>
                        @endif
                    </label>
                    {{-- Khi sửa, đây là mật khẩu mới; để trống sẽ giữ mật khẩu hiện có. --}}
                    <div class="password-control">
                        <input type="password" id="password" name="password" value="{{ $values['password'] }}"
                            @if ($editedUserId === 0) required @endif
                            @if (isset($errors['password'])) class="user-input-error" @endif>
                        @include('password-toggle')
                    </div>
                    <p class="user-field-error" data-form-error="password" @if (! isset($errors['password'])) hidden @endif>{{ $errors['password'] ?? '' }}</p>
                </div>

                <div class="user-form-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ $values['email'] }}"
                        @if (isset($errors['email'])) class="user-input-error" @endif>
                    <p class="user-field-error" data-form-error="email" @if (! isset($errors['email'])) hidden @endif>{{ $errors['email'] ?? '' }}</p>
                </div>

                <div class="user-form-field user-form-date">
                    <label for="birth-date">Ngày sinh</label>
                    <input type="date" id="birth-date" name="birth_date" value="{{ $values['birth_date'] }}" max="{{ $maximumBirthDate }}"
                        @if (isset($errors['birth_date'])) class="user-input-error" @endif>
                    <p class="user-field-error" data-form-error="birth_date" @if (! isset($errors['birth_date'])) hidden @endif>{{ $errors['birth_date'] ?? '' }}</p>
                </div>

                <div class="user-form-field">
                    <label for="user-type">Loại người dùng <span class="required-marker">*</span></label>
                    <div class="user-select-control">
                        <select id="user-type" name="user_type" required
                            @if ($isAdminAccount) disabled @endif
                            @if (isset($errors['user_type'])) class="user-input-error" @endif>
                            @foreach ($newUserTypes as $code => $name)
                                <option value="{{ $code }}" @selected($values['user_type'] === (string) $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="user-field-error" data-form-error="user_type" @if (! isset($errors['user_type'])) hidden @endif>{{ $errors['user_type'] ?? '' }}</p>
                </div>

                <div class="user-form-field">
                    <label for="department">Phòng ban <span class="required-marker">*</span></label>
                    <div class="user-select-control">
                        <select id="department" name="department" required
                            @if (isset($errors['department'])) class="user-input-error" @endif>
                            @foreach ($newUserDepartments as $code => $name)
                                <option value="{{ $code }}" @selected($values['department'] === (string) $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="user-field-error" data-form-error="department" @if (! isset($errors['department'])) hidden @endif>{{ $errors['department'] ?? '' }}</p>
                </div>
            </div>

            {{-- Lưu gửi bước kiểm tra dữ liệu; Xóa trống kiểm tra thay đổi rồi mới mở popup. --}}
            <div class="user-form-actions">
                <button type="submit" name="action" value="validate" class="user-form-next">Lưu</button>
                <button type="submit" name="action" value="clear" class="user-form-clear">Xóa trống</button>
            </div>
        </form>
    </main>
    @include('form-popups', ['formId' => 'user-form']) {{-- Dùng chung popup lưu, xóa trống và rời trang. --}}
</body>
</html>
