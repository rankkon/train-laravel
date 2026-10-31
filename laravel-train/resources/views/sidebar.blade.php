@if ($sidebarFormId === '')
    <input type="checkbox" id="logout-confirm-toggle" class="logout-confirm-toggle" hidden>
@endif
<aside class="sidebar">
    <nav class="menu-list">
        @if ($sidebarFormId !== '')
            <button form="{{ $sidebarFormId }}" name="action" value="exit_dashboard" class="menu-item">
                <span class="menu-label">Dashboard</span>
            </button>
            <button form="{{ $sidebarFormId }}" name="action" value="exit_users" class="menu-item{{ request()->routeIs('users.*') ? ' menu-active' : '' }}">
                <span class="menu-label">Quản lý người dùng</span>
            </button>
            <button form="{{ $sidebarFormId }}" name="action" value="exit_list" class="menu-item{{ request()->routeIs('leaves.*') ? ' menu-active' : '' }}">
                <span class="menu-label">Quản lý đơn</span>
            </button>
        @else
            <a href="{{ route('dashboard') }}" class="menu-item{{ request()->routeIs('dashboard') ? ' menu-active' : '' }}">
                <span class="menu-label">Dashboard</span>
            </a>
            <a href="{{ url('/users') }}" class="menu-item{{ request()->routeIs('users.*') ? ' menu-active' : '' }}">
                <span class="menu-label">Quản lý người dùng</span>
            </a>
            <a href="{{ url('/leaves') }}" class="menu-item{{ request()->routeIs('leaves.*') ? ' menu-active' : '' }}">
                <span class="menu-label">Quản lý đơn</span>
            </a>
        @endif
        @if ($sidebarFormId !== '')
            <button form="{{ $sidebarFormId }}" name="action" value="exit_logout" class="menu-item">
                <span class="menu-label">Đăng xuất</span>
            </button>
        @else
            <label for="logout-confirm-toggle" class="menu-item">
                <span class="menu-label">Đăng xuất</span>
            </label>
        @endif
    </nav>
</aside>
@if ($sidebarFormId === '')
<div class="popup-overlay logout-confirm-popup">
    <form method="post" action="{{ route('logout') }}" class="popup-dialog">
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
@endif
