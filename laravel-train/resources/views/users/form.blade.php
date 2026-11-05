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

        <form method="post" action="{{ route('users.create.submit') }}" id="user-form" class="user-form" novalidate>
            <div class="user-form-fields">
                <div class="user-form-field">
                    <label for="username">Tên đăng nhập <span class="required-marker">*</span></label>
                    <input type="text" id="username" name="username" value="{{ $values['username'] }}" required
                        @if ($isConfirmation) disabled @endif
                        @if (isset($errors['username'])) class="user-input-error" @endif>
                    @if (isset($errors['username']))
                        <p class="user-field-error">{{ $errors['username'] }}</p>
                    @endif
                </div>

                <div class="user-form-field">
                    <label for="full-name">Tên người dùng <span class="required-marker">*</span></label>
                    <input type="text" id="full-name" name="full_name" value="{{ $values['full_name'] }}" required
                        @if ($isConfirmation) disabled @endif
                        @if (isset($errors['full_name'])) class="user-input-error" @endif>
                    @if (isset($errors['full_name']))
                        <p class="user-field-error">{{ $errors['full_name'] }}</p>
                    @endif
                </div>

                <div class="user-form-field">
                    <label for="password">Mật khẩu <span class="required-marker">*</span></label>
                    <input type="password" id="password" name="password" value="{{ $values['password'] }}" required
                        @if ($isConfirmation) disabled @endif
                        @if (isset($errors['password'])) class="user-input-error" @endif>
                    @if (isset($errors['password']))
                        <p class="user-field-error">{{ $errors['password'] }}</p>
                    @endif
                </div>

                <div class="user-form-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ $values['email'] }}"
                        @if ($isConfirmation) disabled @endif
                        @if (isset($errors['email'])) class="user-input-error" @endif>
                    @if (isset($errors['email']))
                        <p class="user-field-error">{{ $errors['email'] }}</p>
                    @endif
                </div>

                <div class="user-form-field user-form-date">
                    <label for="birth-date">Ngày sinh</label>
                    <input type="date" id="birth-date" name="birth_date" value="{{ $values['birth_date'] }}" max="{{ $maximumBirthDate }}"
                        @if ($isConfirmation) disabled @endif
                        @if (isset($errors['birth_date'])) class="user-input-error" @endif>
                    @if (isset($errors['birth_date']))
                        <p class="user-field-error">{{ $errors['birth_date'] }}</p>
                    @endif
                </div>

                <div class="user-form-field">
                    <label for="user-type">Loại người dùng <span class="required-marker">*</span></label>
                    <div class="user-select-control">
                        <select id="user-type" name="user_type" required
                            @if ($isConfirmation) disabled @endif
                            @if (isset($errors['user_type'])) class="user-input-error" @endif>
                            @foreach ($newUserTypes as $code => $name)
                                <option value="{{ $code }}" @selected($values['user_type'] === (string) $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if (isset($errors['user_type']))
                        <p class="user-field-error">{{ $errors['user_type'] }}</p>
                    @endif
                </div>

                <div class="user-form-field">
                    <label for="department">Phòng ban <span class="required-marker">*</span></label>
                    <div class="user-select-control">
                        <select id="department" name="department" required
                            @if ($isConfirmation) disabled @endif
                            @if (isset($errors['department'])) class="user-input-error" @endif>
                            @foreach ($newUserDepartments as $code => $name)
                                <option value="{{ $code }}" @selected($values['department'] === (string) $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if (isset($errors['department']))
                        <p class="user-field-error">{{ $errors['department'] }}</p>
                    @endif
                </div>
            </div>

            <div class="user-form-actions">
                @if ($isConfirmation)
                    <button name="action" value="confirm_save" class="user-form-next">Lưu lại</button>
                    <button name="action" value="back" class="user-form-clear">Quay lại</button>
                @else
                    <button type="submit" name="action" value="validate" class="user-form-next">Tiếp theo</button>
                    <button name="action" value="clear" class="user-form-clear">Xóa trống</button>
                @endif
            </div>
        </form>
    </main>
    @if ($showSavePopup)
        <div class="popup-overlay">
            <section class="popup-dialog">
                <header class="popup-header">
                    <h2 class="popup-title">{{ __('messages.confirmation_title') }}</h2>
                    <button form="user-form" name="action" value="cancel_save" class="popup-close" title="Đóng"></button>
                </header>
                <p class="popup-message">{{ __('messages.confirm_save') }}</p>
                <div class="popup-actions">
                    <button form="user-form" name="action" value="save" class="popup-button popup-confirm">Đồng ý</button>
                    <button form="user-form" name="action" value="cancel_save" class="popup-button popup-cancel" autofocus>Không</button>
                </div>
            </section>
        </div>
    @endif
    @if ($showExitPopup)
        <div class="popup-overlay">
            <section class="popup-dialog">
                <header class="popup-header">
                    <h2 class="popup-title">{{ __('messages.confirmation_title') }}</h2>
                    <button form="user-form" name="action" value="cancel_exit" class="popup-close" title="Đóng"></button>
                </header>
                <p class="popup-message">{{ $exitMessage }}</p>
                <div class="popup-actions">
                    @if ($exitDestination === 'logout')
                        <button form="user-form" formaction="{{ route('logout') }}" class="popup-button popup-confirm">Đồng ý</button>
                    @else
                        <button form="user-form" name="action" value="confirm_exit" class="popup-button popup-confirm">Đồng ý</button>
                    @endif
                    <button form="user-form" name="action" value="cancel_exit" class="popup-button popup-cancel" autofocus>Không</button>
                </div>
            </section>
        </div>
    @endif
</body>
</html>
