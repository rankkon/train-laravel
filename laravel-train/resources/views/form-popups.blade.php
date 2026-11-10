{{-- Checkbox ẩn điều khiển popup lưu bằng CSS; Đồng ý gửi action save, Không chỉ đóng popup. --}}
<input type="checkbox" id="form-save-toggle" class="form-popup-toggle" @checked($showSavePopup) hidden>
<div id="form-save-popup" class="popup-overlay form-popup">
    <section class="popup-dialog" role="dialog" aria-modal="true" aria-labelledby="form-save-title">
        <header class="popup-header">
            <h2 id="form-save-title" class="popup-title">{{ __('messages.confirmation_title') }}</h2>
            <label for="form-save-toggle" class="popup-close" title="Đóng"></label>
        </header>
        <p class="popup-message">{{ __('messages.confirm_save') }}</p>
        <div class="popup-actions">
            <button type="submit" form="{{ $formId }}" name="action" value="save" class="popup-button popup-confirm" @if ($showSavePopup) autofocus @endif>Đồng ý</button>
            <label for="form-save-toggle" class="popup-button popup-cancel">Không</label>
        </div>
    </section>
</div>
{{-- Popup rời trang chỉ chuyển trang hoặc đăng xuất sau khi người dùng đồng ý. --}}
<input type="checkbox" id="form-exit-toggle" class="form-popup-toggle" @checked($showExitPopup) hidden>
<div id="form-exit-popup" class="popup-overlay form-popup">
    <section class="popup-dialog" role="dialog" aria-modal="true" aria-labelledby="form-exit-title">
        <header class="popup-header">
            <h2 id="form-exit-title" class="popup-title">{{ __('messages.confirmation_title') }}</h2>
            <label for="form-exit-toggle" class="popup-close" title="Đóng"></label>
        </header>
        <p class="popup-message">{{ $exitMessage }}</p>
        <div class="popup-actions">
            {{-- Đăng xuất dùng form POST riêng; các trang đích còn lại gửi confirm_exit về form hiện tại. --}}
            @if ($exitDestination === 'logout')
                <button type="submit" form="form-logout" class="popup-button popup-confirm" @if ($showExitPopup) autofocus @endif>Đồng ý</button>
            @else
                <button type="submit" form="{{ $formId }}" name="action" value="confirm_exit" class="popup-button popup-confirm" @if ($showExitPopup) autofocus @endif>Đồng ý</button>
            @endif
            <label for="form-exit-toggle" class="popup-button popup-cancel">Không</label>
        </div>
    </section>
</div>
{{-- Popup Xóa trống chỉ gửi confirm_clear khi đồng ý; đóng popup giữ dữ liệu đang nhập. --}}
<input type="checkbox" id="form-clear-toggle" class="form-popup-toggle" @checked($showClearPopup) hidden>
<div id="form-clear-popup" class="popup-overlay form-popup">
    <section class="popup-dialog" role="dialog" aria-modal="true" aria-labelledby="form-clear-title">
        <header class="popup-header">
            <h2 id="form-clear-title" class="popup-title">{{ __('messages.confirmation_title') }}</h2>
            <label for="form-clear-toggle" class="popup-close" title="Đóng"></label>
        </header>
        <p class="popup-message">{{ __('messages.confirm_clear') }}</p>
        <div class="popup-actions">
            <button type="submit" form="{{ $formId }}" name="action" value="confirm_clear" class="popup-button popup-confirm" @if ($showClearPopup) autofocus @endif>Đồng ý</button>
            <label for="form-clear-toggle" class="popup-button popup-cancel">Không</label>
        </div>
    </section>
</div>
<form id="form-logout" method="post" action="{{ route('logout') }}" hidden></form> {{-- Form độc lập để thực hiện đăng xuất bằng POST. --}}
