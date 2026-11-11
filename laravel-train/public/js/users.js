const userList = document.getElementById('user-list');
const message  = document.getElementById('user-message');
let currentUrl = new URL(window.location.href);

function showError() {
    message.textContent = confirmation.dataset.error;
    message.hidden      = false;
}

function updateSelection() {
    const checkboxes = [...userList.querySelectorAll('input[name="user_ids[]"]:not(:disabled)')];
    const selected   = checkboxes.filter(checkbox => checkbox.checked);
    const selectAll  = document.getElementById('select-all-users');
    const bulkButton = document.getElementById('user-bulk-lock');
    checkboxes.forEach(checkbox => {
        const lock = checkbox.closest('tr').querySelector('[data-lock]');
        if (lock) lock.disabled = !checkbox.checked;
    });
    selectAll.disabled      = checkboxes.length === 0;
    selectAll.checked       = selected.length > 0 && selected.length === checkboxes.length;
    selectAll.indeterminate = selected.length > 0 && selected.length < checkboxes.length;
    if (bulkButton) bulkButton.disabled = selected.length === 0;
}

async function loadList(url) {
    try {
        currentUrl     = await refreshList(url, userList);
        message.hidden = true;
        updateSelection();
    } catch {
        showError();
    }
}

async function changeStatus(url, userIds = null) {
    try {
        const options = {method: 'PATCH'};
        if (userIds) {
            options.headers = {'Content-Type': 'application/json'};
            options.body    = JSON.stringify({user_ids: userIds});
        }
        const response = await sendRequest(url, options);
        const result   = await response.json();
        if (result.redirect) {
            window.location.assign(result.redirect);
            return;
        }
        if (currentUrl.searchParams.has('sort')) currentUrl.searchParams.delete('page');
        currentUrl.searchParams.delete('sort');
        currentUrl.searchParams.delete('direction');
        await loadList(currentUrl);
    } catch {
        showError();
    }
}

userList.addEventListener('submit', event => {
    event.preventDefault();
    const url  = new URL(event.target.action);
    url.search = new URLSearchParams(new FormData(event.target));
    loadList(url);
});
userList.addEventListener('click', event => {
    const link = event.target.closest('.user-sort-link, .leave-pagination a');
    if (link) {
        event.preventDefault();
        loadList(link.href);
    }
    const button = event.target.closest('[data-status-url]');
    if (button) {
        showConfirmation(button.hasAttribute('data-lock') ? confirmation.dataset.lock : confirmation.dataset.unlock,
            () => changeStatus(button.dataset.statusUrl));
    }
    if (event.target.closest('#user-bulk-lock')) {
        const ids = [...userList.querySelectorAll('input[name="user_ids[]"]:checked:not(:disabled)')].map(input => input.value);
        showConfirmation(confirmation.dataset.bulkLock, () => changeStatus(userList.dataset.bulkUrl, ids));
    }
});
userList.addEventListener('change', event => {
    if (event.target.id === 'user-status') {
        event.target.form.requestSubmit();
    }
    if (event.target.id === 'select-all-users') {
        userList.querySelectorAll('input[name="user_ids[]"]:not(:disabled)').forEach(input => { input.checked = event.target.checked; });
    }
    updateSelection();
});
window.addEventListener('pageshow', updateSelection);
