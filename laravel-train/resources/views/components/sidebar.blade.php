<input type="checkbox" id="logout-confirm-toggle" class="logout-confirm-toggle" hidden>
<aside class="sidebar">
    <nav class="menu-list">
        <a href="{{ route('dashboard') }}" class="menu-item{{ request()->routeIs('dashboard') ? ' menu-active' : '' }}">
            <span class="menu-label">Dashboard</span>
        </a>
        <a href="{{ route('users.index') }}" class="menu-item{{ request()->routeIs('users.*') ? ' menu-active' : '' }}">
            <span class="menu-label">Quản lý người dùng</span>
        </a>
        <a href="{{ route('leaves.index') }}" class="menu-item{{ request()->routeIs('leaves.*') ? ' menu-active' : '' }}">
            <span class="menu-label">Quản lý đơn</span>
        </a>
        <label for="logout-confirm-toggle" class="menu-item">
            <span class="menu-label">Đăng xuất</span>
        </label>
    </nav>
</aside>
{{-- Trang không có form nghiệp vụ dùng checkbox và CSS để mở popup xác nhận đăng xuất. --}}
<div class="popup-overlay logout-confirm-popup">
    <form method="post" action="{{ route('logout') }}" class="popup-dialog">
        @csrf
        <header class="popup-header">
            <h2 class="popup-title">{{ __('messages.confirmation_title') }}</h2>
            <label for="logout-confirm-toggle" class="popup-close" title="Đóng"></label>
        </header>
        <p class="popup-message">{{ __('messages.confirm_logout') }}</p>
        <div class="popup-actions">
            <button class="popup-button popup-confirm">OK</button>
            <label for="logout-confirm-toggle" class="popup-button popup-cancel">Cancel</label>
        </div>
    </form>
</div>
<script>
    // Đưa focus vào nút đồng ý khi mở popup đăng xuất ngoài trang form.
    document.getElementById('logout-confirm-toggle').addEventListener('change', function () {
        if (this.checked) {
            document.querySelector('.logout-confirm-popup .popup-confirm').focus();
        }
    });
</script>
