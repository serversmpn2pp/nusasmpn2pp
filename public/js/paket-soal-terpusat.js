(() => {
    const form = document.querySelector('[data-package-form]');
    const bank = document.querySelector('[data-bank-dialog]');
    if (!form || !bank) return;

    const list = form.querySelector('[data-package-list]');
    const openBank = form.querySelector('[data-open-bank]');
    const bankResults = bank.querySelector('[data-bank-results]');
    const bankScroll = bank.querySelector('[data-bank-scroll]');
    const bankLoading = bank.querySelector('[data-bank-loading]');
    const bankError = bank.querySelector('[data-bank-error]');
    const bankCount = bank.querySelector('[data-bank-count]');
    const add = bank.querySelector('[data-bank-add]');
    const selectPage = bank.querySelector('[data-bank-select-page]');
    const filters = [...bank.querySelectorAll('[data-bank-filter]')];
    const staged = new Map();
    const scoreFormat = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let currentRequest;
    let searchTimer;
    let currentPage = 1;

    const packageRows = () => [...list.querySelectorAll('[data-package-question]')];
    const bankRows = () => [...bankResults.querySelectorAll('[data-package-question]')];
    const refreshPackage = () => {
        const rows = packageRows();
        let total = 0;
        rows.forEach((row, index) => {
            row.querySelector('[data-package-position]').textContent = index + 1;
            row.querySelector('[data-package-move="up"]').disabled = index === 0;
            row.querySelector('[data-package-move="down"]').disabled = index === rows.length - 1;
            total += Number(row.dataset.questionScore || 0);
        });
        form.querySelector('[data-package-empty]').hidden = rows.length > 0;
        form.querySelector('[data-package-count]').textContent = rows.length;
        form.querySelector('[data-package-score]').textContent = scoreFormat.format(total);
    };

    const refreshBank = () => {
        const inPackage = new Set(packageRows().map(row => row.dataset.questionId));
        bankRows().forEach(row => {
            const included = inPackage.has(row.dataset.questionId);
            const check = row.querySelector('[data-bank-check]');
            check.disabled = included;
            check.checked = included || staged.has(row.dataset.questionId);
            row.querySelector('[data-bank-in-package]').hidden = !included;
        });
        bankCount.textContent = `${staged.size} dipilih`;
        add.disabled = staged.size === 0;
        selectPage.disabled = bankResults.hidden || !bankResults.querySelector('[data-bank-check]:not(:disabled):not(:checked)');
    };

    const loadBank = async (page = 1, focusResults = false) => {
        currentRequest?.abort();
        const request = new AbortController();
        currentRequest = request;
        currentPage = page;
        const url = new URL(bank.dataset.bankUrl, window.location.href);
        filters.forEach(filter => {
            const value = filter.value.trim();
            if (value) url.searchParams.set(filter.name, value);
        });
        url.searchParams.set('halaman', page);
        bankError.hidden = true;
        bankLoading.hidden = false;
        bankResults.hidden = true;
        selectPage.disabled = true;
        bankScroll.scrollTop = 0;

        try {
            const response = await fetch(url, { signal: request.signal, headers: { Accept: 'text/html' } });
            if (!response.ok || response.redirected) throw new Error('Bank Soal tidak dapat dimuat.');
            const html = await response.text();
            if (currentRequest !== request || !bank.open) return;
            bankResults.innerHTML = html;
            bankResults.hidden = false;
            refreshBank();
            window.lucide?.createIcons();
            if (focusResults) {
                bankResults.querySelector('[data-bank-check]:not(:disabled), [data-question-preview]')?.focus({ preventScroll: true });
            }
        } catch (error) {
            if (error.name !== 'AbortError' && currentRequest === request && bank.open) bankError.hidden = false;
        } finally {
            if (currentRequest === request) bankLoading.hidden = true;
        }
    };

    openBank.addEventListener('click', () => {
        if (bank.open) return;
        bank.showModal();
        loadBank();
    });
    bank.querySelector('[data-bank-close]').addEventListener('click', () => bank.close());
    bank.querySelector('[data-bank-retry]').addEventListener('click', () => loadBank(currentPage, true));
    bank.addEventListener('close', () => {
        currentRequest?.abort();
        clearTimeout(searchTimer);
        staged.clear();
        refreshBank();
    });

    filters.forEach(filter => {
        const searching = filter.type === 'search';
        filter.addEventListener(searching ? 'input' : 'change', () => {
            clearTimeout(searchTimer);
            if (searching) searchTimer = setTimeout(() => loadBank(), 250);
            else loadBank();
        });
    });
    bankResults.addEventListener('change', event => {
        const check = event.target.closest('[data-bank-check]');
        if (!check) return;
        const row = check.closest('[data-package-question]');
        if (check.checked) staged.set(row.dataset.questionId, row);
        else staged.delete(row.dataset.questionId);
        refreshBank();
    });
    bankResults.addEventListener('click', event => {
        const page = event.target.closest('[data-bank-page]');
        if (page && !page.disabled) loadBank(Number(page.dataset.bankPage), true);
    });
    selectPage.addEventListener('click', () => {
        bankRows().forEach(row => {
            if (!row.querySelector('[data-bank-check]').disabled) staged.set(row.dataset.questionId, row);
        });
        refreshBank();
    });

    add.addEventListener('click', () => {
        const inPackage = new Set(packageRows().map(row => row.dataset.questionId));
        staged.forEach((source, id) => {
            if (inPackage.has(id)) return;
            const row = source.cloneNode(true);
            row.querySelector('[data-bank-picker]').remove();
            row.querySelector('[data-bank-in-package]').remove();
            row.querySelector('[data-bank-folders]')?.remove();
            row.querySelector('[data-package-position]').hidden = false;
            row.querySelector('[data-package-tools]').hidden = false;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `soal[${id}][dipilih]`;
            input.value = '1';
            row.prepend(input);
            list.append(row);
        });
        refreshPackage();
        bank.close();
    });

    list.addEventListener('click', event => {
        const action = event.target.closest('[data-package-move], [data-package-remove]');
        if (!action || action.disabled) return;
        const row = action.closest('[data-package-question]');
        if (action.hasAttribute('data-package-remove')) {
            const next = row.nextElementSibling || row.previousElementSibling;
            row.remove();
            refreshPackage();
            (next?.querySelector('[data-package-remove]') || openBank).focus();
            return;
        }

        if (action.dataset.packageMove === 'up') row.previousElementSibling?.before(row);
        else row.nextElementSibling?.after(row);
        refreshPackage();
        (action.disabled ? row.querySelector('[data-package-move]:not(:disabled)') : action)?.focus();
    });
    refreshPackage();
})();
