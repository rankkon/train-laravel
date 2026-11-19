const confirmation  = document.getElementById('confirmation');
const confirmButton = confirmation.querySelector('[data-confirm]');
let confirmAction;

// Mở hộp xác nhận và lưu thao tác sẽ chạy khi bấm OK.
function showConfirmation(message, action) {
    confirmation.querySelector('.popup-message').textContent = message;
    confirmAction = action;
    confirmation.showModal();
    confirmButton.focus();
}

// Đóng hộp thoại khi hủy, hoặc chạy thao tác đã lưu khi xác nhận.
confirmation.querySelectorAll('[data-dismiss]').forEach(button => {
    button.addEventListener('click', () => confirmation.close());
});

confirmButton.addEventListener('click', () => {
    confirmation.close();
    confirmAction?.();
});

// Gửi AJAX kèm CSRF token, xử lý chuyển hướng và chuyển lỗi cho nơi gọi.
async function sendRequest(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            ...options.headers,
        },
    });
    if (response.redirected || response.status === 401) {
        window.location.assign(response.redirected ? response.url : document.body.dataset.loginUrl);
        throw new Error(confirmation.dataset.error);
    }
    if (!response.ok) {
        throw await response.json();
    }
    return response;
}

// Gửi GET để lấy HTML mới, thay phần danh sách và đồng bộ URL phân trang.
async function refreshList(url, container) {
    const response      = await sendRequest(url);
    container.innerHTML = await response.text();
    const currentUrl    = new URL(url);
    currentUrl.searchParams.set('page', container.querySelector('[data-page]').dataset.page);
    history.replaceState(null, '', currentUrl);
    return currentUrl;
}

const recordForm = document.querySelector('[data-record-form]');

// Hiện hoặc xóa thông báo lỗi và viền đỏ của một ô nhập.
function showFieldError(field, message, form = recordForm) {
    const element = form.querySelector(`[data-form-error="${field}"]`);
    if (!element) return false;

    const input         = form.elements.namedItem(field);
    element.textContent = message;
    element.hidden      = !message;
    if (form.dataset.errorClass) input?.classList.toggle(form.dataset.errorClass, Boolean(message));
    if (message) input?.setAttribute('aria-invalid', 'true');
    else input?.removeAttribute('aria-invalid');

    return true;
}

// Xóa toàn bộ lỗi trong form trước lần gửi tiếp theo.
function clearFieldErrors(form) {
    form.querySelectorAll('[data-form-error]').forEach(element => {
        showFieldError(element.dataset.formError, '', form);
    });
}

// Xử lý chung cho form thêm và sửa người dùng hoặc đơn nghỉ phép.
if (recordForm) {
    const initialValues = new URLSearchParams(new FormData(recordForm)).toString();
    let allowLeave      = false;
    recordForm.noValidate = true;

    // So sánh với dữ liệu lúc mở form để phát hiện thay đổi chưa lưu.
    function hasChanges() {
        return new URLSearchParams(new FormData(recordForm)).toString() !== initialValues
            || Boolean(recordForm.querySelector('input[type="file"]')?.files.length);
    }

    function clearErrors() {
        clearFieldErrors(recordForm);
    }

    // Kiểm tra dữ liệu và hỏi xác nhận trước khi gửi form.
    recordForm.addEventListener('submit', event => {
        event.preventDefault();
        clearErrors();
        const invalidFields = recordForm.querySelectorAll(':invalid');
        if (invalidFields.length) {
            for (const field of invalidFields) {
                let message = field.dataset.invalidMessage ?? field.validationMessage;
                if (field.validity.valueMissing) message = field.dataset.requiredMessage ?? message;
                if (field.validity.rangeUnderflow) message = field.dataset.minMessage ?? message;
                if (field.validity.customError) message = field.validationMessage;
                showFieldError(field.name, message);
            }
            invalidFields[0].focus();
            return;
        }
        showConfirmation(confirmation.dataset.save, async () => {
            try {
                await withLoading(async () => {
                    const response = await sendRequest(recordForm.action, {method: 'POST', body: new FormData(recordForm)});
                    const result   = await response.json();
                    allowLeave     = true;
                    // Lưu thành công thì mở lại trang danh sách từ URL server trả về.
                    window.location.assign(result.redirect);
                });
            } catch (error) {
                const errors = error.errors ?? {form: [confirmation.dataset.error]};
                for (const [field, messages] of Object.entries(errors)) {
                    if (!showFieldError(field, messages[0])) {
                        showConfirmation(messages[0]);
                        return;
                    }
                }
                recordForm.elements.namedItem(Object.keys(errors)[0])?.focus();
            }
        });
    });

    // Nút Xóa trống đưa form về giá trị lúc mới mở.
    document.querySelector('[data-clear-form]').addEventListener('click', () => {
        if (hasChanges()) {
            showConfirmation(confirmation.dataset.clear, () => {
                recordForm.reset();
                clearErrors();
            });
        } else {
            clearErrors();
        }
    });

    // Cảnh báo khi chuyển trang, đăng xuất hoặc rời trang với dữ liệu chưa lưu.
    document.querySelectorAll('.sidebar a').forEach(link => {
        link.addEventListener('click', event => {
            if (hasChanges()) {
                event.preventDefault();
                showConfirmation(confirmation.dataset.exit, () => {
                    allowLeave = true;
                    window.location.assign(link.href);
                });
            }
        });
    });
    document.getElementById('logout-confirm-toggle').addEventListener('change', () => {
        document.querySelector('.logout-confirm-popup .popup-message').textContent = hasChanges()
            ? confirmation.dataset.logoutChanges : confirmation.dataset.logout;
    });
    document.querySelector('.logout-confirm-popup form').addEventListener('submit', () => { allowLeave = true; });
    window.addEventListener('beforeunload', event => {
        if (!allowLeave && hasChanges()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
}
