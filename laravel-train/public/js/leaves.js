// Chọn phần xử lý theo trang hiện tại: form đơn hoặc danh sách đơn.
const leaveForm = document.getElementById('leave-form');
if (leaveForm) {
    const attachment    = document.getElementById('leave-attachment');
    const filename      = document.getElementById('leave-file-name');
    const startDate     = document.getElementById('leave-start-date');
    const endDate       = document.getElementById('leave-end-date');
    const preview       = document.getElementById('leave-file-preview');
    const previewButton = document.getElementById('leave-preview-button');
    const previewDialog = document.getElementById('leave-preview-dialog');
    let objectUrl;

    // Kiểm tra định dạng ảnh bằng các byte đầu của file.
    async function isSupportedImage(file) {
        const bytes  = new Uint8Array(await file.slice(0, 12).arrayBuffer());
        const header = String.fromCharCode(...bytes);

        return (bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff)
            || header.startsWith('\x89PNG\r\n\x1a\n')
            || header.startsWith('GIF87a') || header.startsWith('GIF89a')
            || (header.startsWith('RIFF') && header.slice(8, 12) === 'WEBP')
            || header.startsWith('BM');
    }

    function setAttachmentError(message) {
        attachment.setCustomValidity(message);
        showFieldError(attachment.name, message);
    }

    // Cập nhật tên file, kiểm tra ảnh mới và chuẩn bị ảnh xem trước.
    async function updateAttachment() {
        const file = attachment.files[0];

        filename.textContent = file?.name ?? filename.dataset.originalName;
        filename.title       = filename.textContent;
        previewButton.hidden = true;
        previewDialog.close();
        preview.removeAttribute('src');

        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = '';
        setAttachmentError('');
        if (file) attachment.setCustomValidity(attachment.dataset.checkingMessage);

        try {
            if (file) {
                const supported = await isSupportedImage(file);
                if (attachment.files[0] !== file) return;
                if (!supported) {
                    setAttachmentError(attachment.dataset.invalidMessage);
                    return;
                }
                objectUrl = URL.createObjectURL(file);
            }

            const source = objectUrl || attachment.dataset.previewUrl;
            if (!source) return;
            preview.src = source;
            await preview.decode();
            if (attachment.files[0] !== file) return;

            setAttachmentError('');
            previewButton.hidden = false;
        } catch {
            if (file && attachment.files[0] === file) {
                setAttachmentError(attachment.dataset.invalidMessage);
            }
        }
    }

    // Giới hạn ngày kết thúc từ ngày bắt đầu trở đi.
    function updateDates() {
        endDate.min = startDate.value;
    }

    // Co ảnh vừa hộp xem trước và giữ đúng tỷ lệ.
    function fitPreview() {
        const scale = Math.min(
            previewDialog.clientWidth / preview.naturalWidth,
            previewDialog.clientHeight / preview.naturalHeight,
        );

        preview.style.width  = `${preview.naturalWidth * scale}px`;
        preview.style.height = `${preview.naturalHeight * scale}px`;
    }

    // Mở, đóng và chỉnh ảnh khi kích thước cửa sổ thay đổi.
    previewButton.addEventListener('click', () => {
        previewDialog.showModal();
        fitPreview();
    });
    previewDialog.addEventListener('click', event => {
        if (event.target === previewDialog) previewDialog.close();
    });
    window.addEventListener('resize', () => {
        if (previewDialog.open) fitPreview();
    });
    // Cập nhật ảnh và giới hạn ngày khi đổi dữ liệu hoặc đặt lại form.
    attachment.addEventListener('change', updateAttachment);
    startDate.addEventListener('input', updateDates);
    leaveForm.addEventListener('reset', () => queueMicrotask(() => {
        updateAttachment();
        updateDates();
    }));
    updateAttachment();
    updateDates();
} else {
    const leaveList    = document.getElementById('leave-list');
    const message      = document.getElementById('leave-message');
    const cancelDialog = document.getElementById('cancel-dialog');
    const cancelForm   = document.getElementById('cancel-form');
    const cancelReason = document.getElementById('cancel-reason');
    let currentUrl     = new URL(window.location.href);
    let cancelUrl;

    // Tải lại phần HTML danh sách đơn bằng AJAX.
    async function loadList(url) {
        currentUrl     = await refreshList(url, leaveList);
        message.hidden = true;
    }

    function showError() {
        message.textContent = confirmation.dataset.error;
        message.hidden      = false;
    }

    // Gửi PATCH để duyệt hoặc hủy đơn, rồi tải lại danh sách nếu thành công.
    async function processLeave(url, reason) {
        try {
            await withLoading(async () => {
                const options = {method: 'PATCH'};
                if (reason !== undefined) {
                    options.headers = {'Content-Type': 'application/json'};
                    options.body    = JSON.stringify({cancel_reason: reason});
                }
                await sendRequest(url, options);
                cancelDialog.close();
                if (currentUrl.searchParams.has('sort')) currentUrl.searchParams.delete('page');
                currentUrl.searchParams.delete('sort');
                currentUrl.searchParams.delete('direction');
                await loadList(currentUrl);
            });
        } catch (error) {
            if (cancelDialog.open) {
                const reasonError = error.errors?.cancel_reason?.[0];
                if (reasonError) {
                    showFieldError(cancelReason.name, reasonError, cancelForm);
                    cancelReason.focus();
                } else {
                    showFieldError('form', confirmation.dataset.error, cancelForm);
                }
            } else {
                showError();
            }
        }
    }

    // Gửi từ khóa trên URL để lấy kết quả tìm kiếm.
    leaveList.addEventListener('submit', event => {
        event.preventDefault();
        const url  = new URL(event.target.action);
        url.search = new URLSearchParams(new FormData(event.target));
        loadList(url).catch(showError);
    });
    // Xử lý sắp xếp, phân trang và mở hộp thoại duyệt hoặc hủy đơn.
    leaveList.addEventListener('click', event => {
        const link = event.target.closest('.leave-sort-link, .leave-pagination a');
        if (link) {
            event.preventDefault();
            loadList(link.href).catch(showError);
        }
        const approve = event.target.closest('[data-approve-url]');
        if (approve) {
            showConfirmation(confirmation.dataset.approve, () => processLeave(approve.dataset.approveUrl));
        }
        const cancel = event.target.closest('[data-cancel-url]');
        if (cancel) {
            cancelUrl = cancel.dataset.cancelUrl;
            cancelForm.reset();
            clearFieldErrors(cancelForm);
            cancelDialog.showModal();
            cancelReason.focus();
        }
    });
    cancelDialog.querySelectorAll('[data-dismiss-cancel]').forEach(button => {
        button.addEventListener('click', () => cancelDialog.close());
    });
    // Yêu cầu nhập lý do trước khi gửi request hủy đơn.
    cancelForm.addEventListener('submit', event => {
        event.preventDefault();
        clearFieldErrors(cancelForm);
        if (!cancelReason.value.trim()) {
            showFieldError(cancelReason.name, cancelReason.dataset.requiredMessage, cancelForm);
            cancelReason.focus();
            return;
        }
        processLeave(cancelUrl, cancelReason.value);
    });
}
