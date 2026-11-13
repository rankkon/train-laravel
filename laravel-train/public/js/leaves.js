const leaveForm = document.getElementById('leave-form');
if (leaveForm) {
    const attachment  = document.getElementById('leave-attachment');
    const filename    = document.getElementById('leave-file-name');
    const startDate   = document.getElementById('leave-start-date');
    const endDate     = document.getElementById('leave-end-date');
    const fileControl = attachment.closest('.leave-file-control');
    const preview     = document.getElementById('leave-file-preview');
    const storedImage = /\.(jpe?g|png|gif|webp|bmp)$/i.test(filename.dataset.originalName) ? attachment.dataset.previewUrl : '';
    const pointer     = {x: 0, y: 0};
    let objectUrl;

    function updateAttachment() {
        const file = attachment.files[0];

        filename.textContent = file?.name ?? filename.dataset.originalName;
        filename.title       = filename.textContent;
        preview.hidden       = true;

        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = file?.type.startsWith('image/') ? URL.createObjectURL(file) : '';

        const source = file ? objectUrl : storedImage;
        if (source) preview.src = source;
        else preview.removeAttribute('src');
    }

    function updateDates() {
        endDate.min = startDate.value;
    }

    function showPreview(event) {
        if (event) {
            pointer.x = event.clientX;
            pointer.y = event.clientY;
        }
        if (!fileControl.matches(':hover') || !preview.complete || !preview.naturalWidth) return;

        const scale = Math.min(
            Math.min(500, window.innerWidth - 32) / preview.naturalWidth,
            Math.min(500, window.innerHeight - 32) / preview.naturalHeight,
        );

        preview.style.width  = `${preview.naturalWidth * scale}px`;
        preview.style.height = `${preview.naturalHeight * scale}px`;
        preview.hidden       = false;

        const left = pointer.x + 16 + preview.offsetWidth > window.innerWidth ? pointer.x - preview.offsetWidth - 16 : pointer.x + 16;
        const top  = pointer.y + 16 + preview.offsetHeight > window.innerHeight ? pointer.y - preview.offsetHeight - 16 : pointer.y + 16;

        preview.style.left = `${Math.max(8, left)}px`;
        preview.style.top  = `${Math.max(8, top)}px`;
    }

    fileControl.addEventListener('mouseenter', showPreview);
    fileControl.addEventListener('mousemove', showPreview);
    fileControl.addEventListener('mouseleave', () => preview.hidden = true);
    preview.addEventListener('load', () => showPreview());
    preview.addEventListener('error', () => preview.hidden = true);
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
