(() => {
    const text = (key, fallback) => typeof i18nText === 'function' ? i18nText(key, fallback) : fallback;
    const section = document.getElementById('payment-return');
    const button = document.getElementById('payment-check');
    if (!section || !button) return;
    const note = document.getElementById('payment-check-status');
    const endpoint = new URL('api/payment_return_status.php', window.location.href);
    endpoint.searchParams.set('token', section.dataset.token);
    endpoint.searchParams.set('state', section.dataset.returnState);
    let deadline = Date.now() + 120000;
    let terminal = section.dataset.terminal === 'true';
    let running = false;
    let timer;

    function render(status) {
        terminal = status.terminal;
        section.dataset.status = status.state;
        document.getElementById('payment-title').textContent = status.title;
        document.getElementById('payment-message').textContent = status.message;
        document.getElementById('payment-icon').textContent = status.state === 'paid' ? '✓' : (status.state === 'pending' ? '…' : '!');
        document.getElementById('payment-amount').textContent = status.amount || '';
        document.getElementById('payment-amount-box').classList.toggle('hidden', status.amount === null);
        document.getElementById('payment-return-link').href = status.request_url;
    }

    function schedule() {
        clearTimeout(timer);
        if (terminal) return;
        if (Date.now() >= deadline) {
            note.textContent = text('payment_check_slow', 'Confirmation is taking longer than expected. Use Check again, or return to your request.');
            return;
        }
        timer = setTimeout(() => check(false), 4000);
    }

    async function check(manual) {
        if (running) return;
        clearTimeout(timer);
        if (manual) deadline = Date.now() + 120000;
        running = true;
        button.disabled = true;
        note.textContent = text('checking_payment', 'Checking payment status…');
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(endpoint, { cache: 'no-store', credentials: 'omit', signal: controller.signal });
            if (!response.ok) throw new Error('Status unavailable');
            const status = await response.json();
            render(status);
            note.textContent = terminal ? text('status_updated', 'Status updated.') : text('checking_automatically', 'Checking automatically every few seconds.');
        } catch {
            note.textContent = text('error_check_payment', 'Could not check the status. Please check your connection and try again.');
        } finally {
            clearTimeout(timeout);
            running = false;
            button.disabled = false;
            schedule();
        }
    }
    button.addEventListener('click', () => check(true));
    window.addEventListener('pagehide', () => clearTimeout(timer));
    window.addEventListener('pageshow', (event) => { if (event.persisted) check(false); });
    schedule();
})();
