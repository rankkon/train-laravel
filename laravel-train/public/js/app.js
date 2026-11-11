const confirmation  = document.getElementById('confirmation');
const confirmButton = confirmation.querySelector('[data-confirm]');
let confirmAction;

function showConfirmation(message, action) {
    confirmation.querySelector('.popup-message').textContent = message;
    confirmAction = action;
    confirmation.showModal();
    confirmButton.focus();
}

confirmation.querySelectorAll('[data-dismiss]').forEach(button => {
    button.addEventListener('click', () => confirmation.close());
});

confirmButton.addEventListener('click', () => {
    confirmation.close();
    confirmAction();
});

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

async function refreshList(url, container) {
    const response      = await sendRequest(url);
    container.innerHTML = await response.text();
    const currentUrl    = new URL(url);
    currentUrl.searchParams.set('page', container.querySelector('[data-page]').dataset.page);
    history.replaceState(null, '', currentUrl);
    return currentUrl;
}

const recordForm = document.querySelector('[data-record-form]');
if (recordForm) {
    const initialValues = new URLSearchParams(new FormData(recordForm)).toString();
    let allowLeave      = false;

    function hasChanges() {
        return new URLSearchParams(new FormData(recordForm)).toString() !== initialValues
            || Boolean(recordForm.querySelector('input[type="file"]')?.files.length);
    }

    function clearErrors() {
        recordForm.querySelectorAll('[data-form-error]').forEach(element => {
            element.textContent = '';
            element.hidden      = true;
        });
        recordForm.querySelectorAll('.' + recordForm.dataset.errorClass).forEach(element => element.classList.remove(recordForm.dataset.errorClass));
    }

    recordForm.addEventListener('submit', event => {
        event.preventDefault();
        showConfirmation(confirmation.dataset.save, async () => {
            clearErrors();
            try {
                const response = await sendRequest(recordForm.action, {method: 'POST', body: new FormData(recordForm)});
                const result   = await response.json();
                allowLeave     = true;
                window.location.assign(result.redirect);
            } catch (error) {
                const errors = error.errors ?? {form: [confirmation.dataset.error]};
                for (const [field, messages] of Object.entries(errors)) {
                    const element       = recordForm.querySelector(`[data-form-error="${field}"]`);
                    element.textContent = messages[0];
                    element.hidden      = false;
                    recordForm.elements.namedItem(field)?.classList.add(recordForm.dataset.errorClass);
                }
                recordForm.elements.namedItem(Object.keys(errors)[0])?.focus();
            }
        });
    });

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
