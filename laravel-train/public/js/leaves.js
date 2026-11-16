const leaveForm = document.getElementById('leave-form');
if (leaveForm) {
    const attachment    = document.getElementById('leave-attachment');
    const filename      = document.getElementById('leave-file-name');
    const startDate     = document.getElementById('leave-start-date');
    const endDate       = document.getElementById('leave-end-date');
    const preview       = document.getElementById('leave-file-preview');
    const previewButton = document.getElementById('leave-preview-button');
    const previewDialog = document.getElementById('leave-preview-dialog');
    const storedImage   = /\.(jpe?g|png|gif|webp|bmp)$/i.test(filename.dataset.originalName) ? attachment.dataset.previewUrl : '';
    let objectUrl;

    function updateAttachment() {
        const file = attachment.files[0];

        filename.textContent = file?.name ?? filename.dataset.originalName;
        filename.title       = filename.textContent;
        previewButton.hidden = true;
        previewDialog.close();

        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = file?.type.startsWith('image/') ? URL.createObjectURL(file) : '';

        const source = file ? objectUrl : storedImage;
        if (source) preview.src = source;
        else preview.removeAttribute('src');
    }

    function updateDates() {
        endDate.min = startDate.value;
    }

    function fitPreview() {
        const scale = Math.min(
            previewDialog.clientWidth / preview.naturalWidth,
            previewDialog.clientHeight / preview.naturalHeight,
        );

        preview.style.width  = `${preview.naturalWidth * scale}px`;
        preview.style.height = `${preview.naturalHeight * scale}px`;
    }

    previewButton.addEventListener('click', () => {
        previewDialog.showModal();
        fitPreview();
    });
    previewDialog.addEventListener('click', event => {
        if (event.target === previewDialog) previewDialog.close();
    });
    preview.addEventListener('load', () => previewButton.hidden = false);
    preview.addEventListener('error', () => {
        previewButton.hidden = true;
        previewDialog.close();
    });
    window.addEventListener('resize', () => {
        if (previewDialog.open) fitPreview();
    });
    attachment.addEventListener('change', updateAttachment);
    startDate.addEventListener('change', updateDates);
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
    const cancelError  = document.getElementById('cancel-error');
    let currentUrl     = new URL(window.location.href);
    let cancelUrl;

    async function loadList(url) {
        currentUrl     = await refreshList(url, leaveList);
        message.hidden = true;
    }

    function showError() {
        message.textContent = confirmation.dataset.error;
        message.hidden      = false;
    }

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
                cancelError.textContent = error.errors?.cancel_reason?.[0] ?? confirmation.dataset.error;
                cancelError.hidden      = false;
                cancelReason.focus();
            } else {
                showError();
            }
        }
    }

    leaveList.addEventListener('submit', event => {
        event.preventDefault();
        const url  = new URL(event.target.action);
        url.search = new URLSearchParams(new FormData(event.target));
        loadList(url).catch(showError);
    });
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
            cancelError.hidden = true;
            cancelDialog.showModal();
            cancelReason.focus();
        }
    });
    cancelDialog.querySelectorAll('[data-dismiss-cancel]').forEach(button => {
        button.addEventListener('click', () => cancelDialog.close());
    });
    cancelForm.addEventListener('submit', event => {
        event.preventDefault();
        processLeave(cancelUrl, cancelReason.value);
    });
}
