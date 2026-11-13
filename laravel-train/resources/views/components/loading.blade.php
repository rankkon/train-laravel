<dialog id="loading-dialog" class="loading-dialog" aria-labelledby="loading-message">
    <span class="loading-spinner" aria-hidden="true"></span>
    <p id="loading-message" role="status">{{ __('messages.processing') }}</p>
</dialog>

<script>
    const loadingDialog = document.getElementById('loading-dialog');

    function setLoading(active) {
        if (active) {
            if (loadingDialog.open) return false;
            loadingDialog.showModal();
        } else {
            loadingDialog.close();
        }
        return true;
    }

    async function withLoading(action) {
        if (!setLoading(true)) return;

        const buttons = [...document.querySelectorAll('button:enabled, input[type="submit"]:enabled')];
        buttons.forEach(button => button.disabled = true);

        try {
            return await action();
        } finally {
            buttons.forEach(button => button.disabled = false);
            setLoading(false);
        }
    }

    loadingDialog.addEventListener('cancel', event => event.preventDefault());
    document.addEventListener('submit', event => {
        if (!event.defaultPrevented && event.target.method === 'post' && !setLoading(true)) {
            event.preventDefault();
        }
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted) setLoading(false);
    });
</script>
