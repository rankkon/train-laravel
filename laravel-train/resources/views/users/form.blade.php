@extends('layouts.app')

@section('title', $user->exists ? 'Sửa người dùng' : 'Thêm mới người dùng')
@section('body-class', 'user-create-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/user_form.css') }}?v={{ filemtime(public_path('css/user_form.css')) }}">
@endpush

@section('content')
    <main class="user-create">
        <h1 class="user-form-title">{{ $user->exists ? 'Sửa người dùng' : 'Thêm mới người dùng' }}</h1>
        <form method="post" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" id="user-form" class="user-form" data-record-form data-error-class="user-input-error">
            @csrf
            @if ($user->exists)
                @method('PUT')
            @endif
            <div class="user-form-fields">
                <p class="user-field-error" data-form-error="form" hidden></p>
                <div class="user-form-field">
                    <label for="username"><span class="required-marker">*</span> Tên đăng nhập</label>
                    <input type="text" id="username" name="username" value="{{ $user->username }}"
                        maxlength="50" required @disabled($user->exists) aria-describedby="username-error"
                        data-required-message="{{ __('validation.custom.username.required') }}">
                    <p id="username-error" class="user-field-error" data-form-error="username" hidden></p>
                </div>
                <div class="user-form-field">
                    <label for="full-name"><span class="required-marker">*</span> Tên người dùng</label>
                    <input type="text" id="full-name" name="full_name" value="{{ $user->full_name }}"
                        maxlength="100" required aria-describedby="full-name-error"
                        data-required-message="{{ __('validation.custom.full_name.required') }}">
                    <p id="full-name-error" class="user-field-error" data-form-error="full_name" hidden></p>
                </div>
                @if ($user->exists)
                    <div class="user-form-field">
                        <label for="password">Mật khẩu mới</label>
                        {{-- Khi sửa, đây là mật khẩu mới; để trống sẽ giữ mật khẩu hiện có. --}}
                        <div class="password-control">
                            <input type="password" id="password" name="password" minlength="8" aria-describedby="password-error"
                                data-invalid-message="{{ __('validation.custom.password.min', ['min' => 8]) }}">
                            @include('components.password-toggle')
                        </div>
                        <p id="password-error" class="user-field-error" data-form-error="password" hidden></p>
                    </div>
                @endif
                <div class="user-form-field">
                    <label for="email"><span class="required-marker">*</span> Email</label>
                    <input type="email" id="email" name="email" value="{{ $user->email }}" maxlength="254" required
                        aria-describedby="email-error" data-required-message="{{ __('validation.custom.email.required') }}"
                        data-invalid-message="{{ __('validation.custom.email.email') }}">
                    <p id="email-error" class="user-field-error" data-form-error="email" hidden></p>
                </div>
                <div class="user-form-field user-form-date">
                    <label for="birth-date">Ngày sinh</label>
                    <input type="date" id="birth-date" name="birth_date" value="{{ $user->birth_date?->format('Y-m-d') }}"
                        max="{{ today()->toDateString() }}" aria-describedby="birth-date-error"
                        data-invalid-message="{{ __('validation.custom.birth_date.before_or_equal') }}">
                    <p id="birth-date-error" class="user-field-error" data-form-error="birth_date" hidden></p>
                </div>
                <div class="user-form-field">
                    <label for="user-type"><span class="required-marker">*</span> Loại người dùng</label>
                    <div class="user-select-control">
                        <select id="user-type" name="user_type" required @disabled($user->user_type === \App\Constants\AppConstants::TYPE_ADMIN)
                            aria-describedby="user-type-error" data-required-message="{{ __('validation.custom.user_type.in') }}">
                            @foreach ($userTypes as $code => $name)
                                <option value="{{ $code }}" @selected(($user->user_type ?? \App\Constants\AppConstants::TYPE_USER) === $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p id="user-type-error" class="user-field-error" data-form-error="user_type" hidden></p>
                </div>
                <div class="user-form-field">
                    <label for="department"><span class="required-marker">*</span> Phòng ban</label>
                    <div class="user-select-control">
                        <select id="department" name="department" required aria-describedby="department-error"
                            data-required-message="{{ __('validation.custom.department.in') }}">
                            @foreach ($departments as $code => $name)
                                <option value="{{ $code }}" @selected(($user->department ?? array_key_first($departments)) === $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p id="department-error" class="user-field-error" data-form-error="department" hidden></p>
                </div>
            </div>
            <div class="user-form-actions">
                <button type="submit" class="user-form-next">Lưu</button>
                <button type="button" id="clear-user-form" data-clear-form class="user-form-clear">Xóa trống</button>
            </div>
        </form>
    </main>
@endsection
