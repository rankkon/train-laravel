<button type="button" class="password-toggle" aria-controls="password" aria-label="Hiện mật khẩu" aria-pressed="false" title="Hiện mật khẩu">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
        <circle cx="12" cy="12" r="3" />
        <path class="password-eye-slash" d="m3 3 18 18" />
    </svg>
</button>
<script id="password-toggle-script">
    const passwordInput = document.getElementById('password');
    const toggleButton  = document.querySelector('.password-toggle');

    toggleButton.addEventListener('click', function () {
        const showPassword = passwordInput.type === 'password';
        const label        = showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu';
        passwordInput.type = showPassword ? 'text' : 'password';
        toggleButton.title = label;
        toggleButton.setAttribute('aria-label', label);
        toggleButton.setAttribute('aria-pressed', String(showPassword));
    });
</script>
