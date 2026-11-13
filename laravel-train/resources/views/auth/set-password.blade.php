<dialog id="password-setup-dialog" class="popup-dialog password-setup-dialog" aria-labelledby="password-setup-title" aria-describedby="password-setup-description">
    <div class="popup-header">
        <h2 id="password-setup-title" class="popup-title">{{ __('messages.password_setup_title') }}</h2>
        <button type="button" class="popup-close" data-cancel-password aria-label="Đóng"></button>
    </div>

    <p id="password-setup-description" class="password-setup-description">{{ __('messages.password_setup_description') }}</p>

    <form id="password-setup-form" method="post" action="{{ route('login.password') }}" data-error="{{ __('messages.page_error') }}" novalidate>
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
            <button type="button" class="popup-button popup-cancel" data-cancel-password>Hủy</button>
        </div>
    </form>
</dialog>

<form id="password-setup-cancel" method="post" action="{{ route('logout') }}" hidden>
    @csrf
</form>

@push('scripts')
    <script>
        const passwordDialog    = document.getElementById('password-setup-dialog');
        const passwordForm      = document.getElementById('password-setup-form');
        const passwordError     = document.getElementById('password-setup-error');
        const passwordCancel    = document.getElementById('password-setup-cancel');
        const cancelPassword    = () => {
            if (setLoading(true)) passwordCancel.submit();
        };

        passwordDialog.showModal();
        document.getElementById('new-password').focus();
        passwordDialog.querySelectorAll('[data-cancel-password]').forEach(button => {
            button.addEventListener('click', cancelPassword);
        });
        passwordDialog.addEventListener('cancel', event => {
            event.preventDefault();
            cancelPassword();
        });

        passwordForm.addEventListener('submit', async event => {
            event.preventDefault();
            passwordError.hidden = true;

            try {
                await withLoading(async () => {
                    const response = await fetch(passwordForm.action, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: new FormData(passwordForm),
                    });
                    const result   = await response.json();
                    if (!response.ok) throw result;
                    window.location.href = result.redirect;
                });
            } catch (error) {
                passwordError.textContent = Object.values(error.errors ?? { form: [passwordForm.dataset.error] }).flat().join('\n');
                passwordError.hidden      = false;
            }
        });
    </script>
@endpush
