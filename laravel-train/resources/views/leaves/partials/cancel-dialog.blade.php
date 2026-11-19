<dialog id="cancel-dialog" class="popup-dialog leave-cancel-dialog" aria-labelledby="cancel-title">
    <form id="cancel-form" novalidate>
        <header class="popup-header">
            <h2 id="cancel-title" class="popup-title">{{ __('messages.confirmation_title') }}</h2>
            <button type="button" class="popup-close" data-dismiss-cancel title="Đóng"></button>
        </header>
        <div class="leave-cancel-fields">
            <label for="cancel-reason">Lý do hủy đơn <span class="leave-cancel-required">*</span></label>
            <textarea id="cancel-reason" name="cancel_reason" class="leave-cancel-reason" required
                aria-describedby="cancel-error" data-required-message="{{ __('validation.custom.cancel_reason.required') }}"></textarea>
            <p id="cancel-error" class="leave-process-error" data-form-error="cancel_reason" role="alert" hidden></p>
            <p class="leave-process-error" data-form-error="form" role="alert" hidden></p>
        </div>
        <div class="popup-actions leave-cancel-actions">
            <button type="submit" class="popup-button popup-confirm">OK</button>
            <button type="button" class="popup-button popup-cancel" data-dismiss-cancel>Cancel</button>
        </div>
    </form>
</dialog>
