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
                    <input type="password" id="new-password" name="password" autocomplete="new-password" required
                        aria-describedby="new-password-error" data-required-message="{{ __('validation.custom.password.required') }}">
                    <p id="new-password-error" class="password-setup-error" data-form-error="password" role="alert" hidden></p>
                </div>
                <div>
                    <label for="password-confirmation">Xác nhận mật khẩu <span class="password-setup-required">*</span></label>
                    <input type="password" id="password-confirmation" name="password_confirmation" autocomplete="new-password" required
                        aria-describedby="password-confirmation-error" data-mismatch-message="{{ __('validation.custom.password.confirmed') }}">
                    <p id="password-confirmation-error" class="password-setup-error" data-form-error="password_confirmation" role="alert" hidden></p>
                </div>
                <p class="password-setup-error" data-form-error="form" role="alert" hidden></p>
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
        const newPassword = passwordForm.elements.namedItem('password');
        const passwordConfirmation = passwordForm.elements.namedItem('password_confirmation');

        passwordDialog.showModal();
        newPassword.focus();
        passwordDialog.addEventListener('cancel', event => event.preventDefault());

        passwordForm.addEventListener('submit', async event => {
            event.preventDefault();
            clearFieldErrors(passwordForm);

            if (!newPassword.value) {
                showFieldError(newPassword.name, newPassword.dataset.requiredMessage, passwordForm);
            }
            if (!passwordConfirmation.value || (newPassword.value && passwordConfirmation.value !== newPassword.value)) {
                showFieldError(passwordConfirmation.name, passwordConfirmation.dataset.mismatchMessage, passwordForm);
            }
            const invalidField = passwordForm.querySelector('[aria-invalid="true"]');
            if (invalidField) {
                invalidField.focus();
                return;
            }

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
                const errors = error.errors ?? { form: [confirmation.dataset.error] };
                for (const [field, messages] of Object.entries(errors)) {
                    if (!showFieldError(field, messages.join('\n'), passwordForm)) {
                        showFieldError('form', messages.join('\n'), passwordForm);
                    }
                }
                passwordForm.querySelector('[aria-invalid="true"]')?.focus();
            }
        });
    </script>
@endpush
