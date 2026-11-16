@extends('layouts.app')

@section('title', 'Đặt mật khẩu mới')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
@endpush

@section('content')
    <dialog id="password-setup-dialog" class="popup-dialog password-setup-dialog" closedby="none" aria-labelledby="password-setup-title" aria-describedby="password-setup-description">
        <div class="popup-header">
            <h2 id="password-setup-title" class="popup-title">{{ __('messages.password_setup_title') }}</h2>
        </div>

        <p id="password-setup-description" class="password-setup-description">{{ __('messages.password_setup_description') }}</p>

        <form id="password-setup-form" method="post" action="{{ route('password.update') }}" novalidate>
            @csrf
            <div class="password-setup-fields">
                <div>
                    <label for="new-password">Mật khẩu mới <span class="password-setup-required">*</span></label>
                    <input type="password" id="new-password" name="password" autocomplete="new-password" required>
                </div>
                <div>
                    <label for="password-confirmation">Xác nhận mật khẩu <span class="password-setup-required">*</span></label>
                    <input type="password" id="password-confirmation" name="password_confirmation" autocomplete="new-password" required>
                </div>
                <p id="password-setup-error" class="password-setup-error" role="alert" hidden></p>
            </div>
            <div class="popup-actions">
                <button type="submit" class="popup-button popup-confirm">Lưu mật khẩu</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
    <script>
        const passwordDialog = document.getElementById('password-setup-dialog');
        const passwordForm   = document.getElementById('password-setup-form');
        const passwordError  = document.getElementById('password-setup-error');

        passwordDialog.showModal();
        document.getElementById('new-password').focus();
        passwordDialog.addEventListener('cancel', event => event.preventDefault());

        passwordForm.addEventListener('submit', async event => {
            event.preventDefault();
            passwordError.hidden = true;

            try {
                await withLoading(async () => {
                    const response = await sendRequest(passwordForm.action, {
                        method: 'POST',
                        body: new FormData(passwordForm),
                    });
                    const result         = await response.json();
                    window.location.href = result.redirect;
                });
            } catch (error) {
                passwordError.textContent = Object.values(error.errors ?? { form: [confirmation.dataset.error] }).flat().join('\n');
                passwordError.hidden      = false;
            }
        });
    </script>
@endpush
