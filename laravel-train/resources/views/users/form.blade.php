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
                    <label for="username">Tên đăng nhập <span class="required-marker">*</span></label>
                    <input type="text" id="username" name="username" value="{{ $user->username }}"
                        maxlength="50" required @disabled($user->exists)>
                    <p class="user-field-error" data-form-error="username" hidden></p>
                </div>
                <div class="user-form-field">
                    <label for="full-name">Tên người dùng <span class="required-marker">*</span></label>
                    <input type="text" id="full-name" name="full_name" value="{{ $user->full_name }}"
                        maxlength="100" required>
                    <p class="user-field-error" data-form-error="full_name" hidden></p>
                </div>
                <div class="user-form-field">
                    <label for="password">
                        @if ($user->exists)
                            Mật khẩu mới
                        @else
                            Mật khẩu <span class="required-marker">*</span>
                        @endif
                    </label>
                    {{-- Khi sửa, đây là mật khẩu mới; để trống sẽ giữ mật khẩu hiện có. --}}
                    <div class="password-control">
                        <input type="password" id="password" name="password" minlength="8" @required(!$user->exists)>
                        @include('components.password-toggle')
                    </div>
                    <p class="user-field-error" data-form-error="password" hidden></p>
                </div>
                <div class="user-form-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ $user->email }}" maxlength="254">
                    <p class="user-field-error" data-form-error="email" hidden></p>
                </div>
                <div class="user-form-field user-form-date">
                    <label for="birth-date">Ngày sinh</label>
                    <input type="date" id="birth-date" name="birth_date" value="{{ $user->birth_date?->format('Y-m-d') }}" max="{{ now()->subDay()->toDateString() }}">
                    <p class="user-field-error" data-form-error="birth_date" hidden></p>
                </div>
                <div class="user-form-field">
                    <label for="user-type">Loại người dùng <span class="required-marker">*</span></label>
                    <div class="user-select-control">
                        <select id="user-type" name="user_type" required @disabled($user->user_type === \App\Constants\AppConstants::TYPE_ADMIN)>
                            @foreach ($userTypes as $code => $name)
                                <option value="{{ $code }}" @selected(($user->user_type ?? \App\Constants\AppConstants::TYPE_USER) === $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="user-field-error" data-form-error="user_type" hidden></p>
                </div>
                <div class="user-form-field">
                    <label for="department">Phòng ban <span class="required-marker">*</span></label>
                    <div class="user-select-control">
                        <select id="department" name="department" required>
                            @foreach ($departments as $code => $name)
                                <option value="{{ $code }}" @selected(($user->department ?? array_key_first($departments)) === $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="user-field-error" data-form-error="department" hidden></p>
                </div>
            </div>
            <div class="user-form-actions">
                <button type="submit" class="user-form-next">Lưu</button>
                <button type="button" id="clear-user-form" data-clear-form class="user-form-clear">Xóa trống</button>
            </div>
        </form>
    </main>
@endsection
