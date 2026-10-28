(() => {
    'use strict';
    const editor = document.querySelector('[data-interest-editor]');
    if (!editor) return;
    const list = editor.querySelector('[data-interest-list]');
    const add = editor.querySelector('[data-interest-add]');
    const notice = editor.querySelector('[data-interest-notice]');
    const rows = () => [...list.querySelectorAll('[data-interest-row]')];
    const icons = () => window.lucide?.createIcons();

    function update() {
        const all = rows();
        all.forEach((row, index) => {
            row.querySelectorAll('[data-interest-field]').forEach(input => {
                const key = input.dataset.interestField;
                input.name = `pilihan_minat[${index}][${key}]`;
                if (key === 'nama' || key === 'ikon') input.id = `minat-${key}-${index}`;
            });
            row.querySelectorAll('[data-interest-label]').forEach(label => {
                label.htmlFor = `minat-${label.dataset.interestLabel}-${index}`;
            });
            row.querySelector('[data-interest-action="up"]').disabled = index === 0;
            row.querySelector('[data-interest-action="down"]').disabled = index === all.length - 1;
            row.querySelector('[data-interest-action="remove"]').disabled = all.length === 1 || row.dataset.used === 'true';
        });
        add.disabled = all.length >= 30;
    }

    add.addEventListener('click', () => {
        if (rows().length >= 30) return;
        const row = editor.querySelector('template').content.firstElementChild.cloneNode(true);
        const suffix = window.crypto?.randomUUID ? crypto.randomUUID().replaceAll('-', '') : Date.now().toString(36) + Math.random().toString(36).slice(2);
        row.querySelector('[data-interest-field="kode"]').value = 'minat_' + suffix;
        list.append(row);
        update();
        icons();
        row.querySelector('[data-interest-field="nama"]').focus();
        notice.textContent = `${rows().length} pilihan`;
    });

    list.addEventListener('click', event => {
        const button = event.target.closest('[data-interest-action]');
        if (!button || button.disabled) return;
        const row = button.closest('[data-interest-row]');
        if (button.dataset.interestAction === 'up' && row.previousElementSibling) {
            list.insertBefore(row, row.previousElementSibling);
        } else if (button.dataset.interestAction === 'down' && row.nextElementSibling) {
            list.insertBefore(row.nextElementSibling, row);
        } else if (button.dataset.interestAction === 'remove' && rows().length > 1 && row.dataset.used !== 'true') {
            const next = row.nextElementSibling || row.previousElementSibling;
            row.remove();
            next.querySelector('[data-interest-field="nama"]').focus();
        }
        update();
        notice.textContent = `${rows().length} pilihan`;
    });

    list.addEventListener('change', event => {
        if (event.target.dataset.interestField !== 'ikon') return;
        const icon = document.createElement('i');
        icon.dataset.lucide = event.target.value;
        icon.setAttribute('aria-hidden', 'true');
        event.target.closest('[data-interest-row]').querySelector('[data-interest-preview]').replaceChildren(icon);
        icons();
    });
    update();
})();
