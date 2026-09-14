document.addEventListener('DOMContentLoaded', () => {
    const timers = new Map();

    async function loadTable(key, page = 1) {
        const input = document.querySelector(`[data-admin-table-search="${key}"]`);
        const body = document.querySelector(`[data-admin-table-body="${key}"]`);
        if (!input || !body) return;

        const url = new URL(window.location.href);
        url.searchParams.delete('delete');
        url.searchParams.set(`${key}_search`, input.value.trim());
        url.searchParams.set(`${key}_page`, String(page));
        body.classList.add('opacity-50', 'pointer-events-none');

        try {
            const response = await fetch(url.toString(), {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin'
            });
            if (!response.ok) throw new Error('Request failed');

            const html = await response.text();
            const parsed = new DOMParser().parseFromString(html, 'text/html');
            const nextBody = parsed.querySelector(`[data-admin-table-body="${key}"]`);
            const nextInfo = parsed.querySelector(`[data-admin-table-info="${key}"]`);
            const nextPagination = parsed.querySelector(`[data-admin-table-pagination="${key}"]`);
            if (!nextBody || !nextInfo || !nextPagination) throw new Error('Invalid table response');

            body.innerHTML = nextBody.innerHTML;
            document.querySelector(`[data-admin-table-info="${key}"]`).innerHTML = nextInfo.innerHTML;
            document.querySelector(`[data-admin-table-pagination="${key}"]`).innerHTML = nextPagination.innerHTML;
            window.history.replaceState({}, '', url.toString());
        } catch (error) {
            if (typeof showToast === 'function') showToast('Could not update the table. Please try again.');
        } finally {
            body.classList.remove('opacity-50', 'pointer-events-none');
        }
    }

    document.addEventListener('input', (event) => {
        const input = event.target.closest('[data-admin-table-search]');
        if (!input) return;
        const key = input.dataset.adminTableSearch;
        clearTimeout(timers.get(key));
        timers.set(key, setTimeout(() => loadTable(key, 1), 350));
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-admin-table-page]');
        if (!button || button.disabled) return;
        const pagination = button.closest('[data-admin-table-pagination]');
        if (!pagination) return;
        loadTable(pagination.dataset.adminTablePagination, Number(button.dataset.adminTablePage));
    });
});
