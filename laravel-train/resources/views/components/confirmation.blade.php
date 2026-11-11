<dialog id="confirmation" class="popup-dialog"
    aria-labelledby="confirmation-title"
    data-save="{{ __('messages.confirm_save') }}"
    data-clear="{{ __('messages.confirm_clear') }}"
    data-exit="{{ __('messages.confirm_exit') }}"
    data-logout="{{ __('messages.confirm_logout') }}"
    data-logout-changes="{{ __('messages.confirm_logout_with_changes') }}"
    data-lock="{{ __('messages.confirm_user_lock') }}"
    data-unlock="{{ __('messages.confirm_user_unlock') }}"
    data-bulk-lock="{{ __('messages.confirm_users_lock') }}"
    data-approve="{{ __('messages.confirm_approve') }}"
    data-error="{{ __('messages.page_error') }}">
    <header class="popup-header">
        <h2 id="confirmation-title" class="popup-title">{{ __('messages.confirmation_title') }}</h2>
        <button type="button" class="popup-close" data-dismiss title="Đóng"></button>
    </header>
    <p class="popup-message"></p>
    <div class="popup-actions">
        <button type="button" class="popup-button popup-confirm" data-confirm>Đồng ý</button>
        <button type="button" class="popup-button popup-cancel" data-dismiss>Không</button>
    </div>
</dialog>
