@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-komite-form]');
    if (!form) return;
    const members = form.querySelector('[data-komite-members]');
    const add = form.querySelector('[data-add-member]');
    const syncMembers = () => {
        const rows = [...members.querySelectorAll('[data-komite-member]')];
        rows.forEach((row,index) => {
            row.querySelector('[data-member-number]').textContent = index + 1;
            row.querySelectorAll('[data-member-field]').forEach(input => {
                const field = input.dataset.memberField;
                input.name = `pengurus[${index}][${field}]`;
                input.id = `pengurus_${index}_${field}`;
            });
            row.querySelectorAll('[data-member-label]').forEach(label => {label.htmlFor = `pengurus_${index}_${label.dataset.memberLabel}`;});
            row.classList.toggle('komite-member--nonaktif', row.querySelector('[data-member-field=aktif]').value === '0');
            const name = row.querySelector('[data-member-field=nama]');
            name.required = !!row.querySelector('[data-member-field=id]').value || !!row.querySelector('[data-member-field=nomor_telepon]').value;
        });
        add.disabled = rows.length >= 50;
        form.querySelector('[data-member-count]').textContent = `${rows.length} baris pengurus`;
    };
    add.addEventListener('click', () => {
        if (members.children.length >= 50) return;
        members.append(document.querySelector('[data-member-template]').content.cloneNode(true));
        syncMembers();
        members.lastElementChild.querySelector('[data-member-field=nama]').focus();
    });
    members.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-member]');
        if (!button) return;
        button.closest('[data-komite-member]').remove();
        syncMembers();
    });
    members.addEventListener('change', syncMembers);
    syncMembers();
    const updateSource = () => {
        const method = form.querySelector('[name=metode]:checked')?.value;
        form.querySelectorAll('[data-komite-source]').forEach(section => {
            const active = section.dataset.komiteSource === method;
            section.hidden = !active;
            section.querySelectorAll('input,select').forEach(input => {input.disabled = !active; input.required = active && ['berkas','dokumen_humas_id'].includes(input.name);});
        });
        const active = form.querySelector('#status').value === 'aktif';
        form.querySelector('#nomor_sk').required = active;
        form.querySelector('#tanggal_sk').required = active;
        form.querySelector('[data-sk-lama]')?.toggleAttribute('hidden', method !== 'tetap');
    };
    form.querySelectorAll('[name=metode], #status').forEach(input => input.addEventListener('change', updateSource));
    updateSource();
    const fileInput = form.querySelector('#berkas');
    const preview = form.querySelector('[data-komite-preview]');
    let url;
    fileInput?.addEventListener('change', () => {
        if (url) URL.revokeObjectURL(url);
        preview.replaceChildren();
        const file = fileInput.files[0];
        fileInput.setCustomValidity(file && (file.size > 20 * 1024 * 1024 || !['application/pdf','image/jpeg','image/png','image/webp'].includes(file.type)) ? 'Pilih SK PDF/gambar, maksimal 20 MB.' : '');
        if (file && ['image/jpeg','image/png','image/webp'].includes(file.type)) {
            const img = document.createElement('img');
            url = URL.createObjectURL(file);
            img.src = url; img.alt = file.name; img.className = 'komite-preview';
            preview.append(img);
        }
    });
    const search = form.querySelector('[data-dokumen-search]');
    if (!search) return;
    const select = form.querySelector('#dokumen_humas_id');
    const state = form.querySelector('[data-dokumen-state]');
    let timer, controller;
    search.addEventListener('input', () => {
        clearTimeout(timer); controller?.abort();
        timer = setTimeout(async () => {
            controller = new AbortController();
            const signal = controller.signal;
            state.textContent = 'Mencari SK...';
            try {
                const url = new URL(search.dataset.dokumenSearch, location.origin);
                url.searchParams.set('cari', search.value);
                const response = await fetch(url, {headers:{Accept:'application/json'},signal});
                if (!response.ok) throw new Error();
                const data = await response.json();
                if (signal.aborted) return;
                const selected = select.selectedOptions[0];
                const options = [new Option('Pilih dokumen',''), ...data.dokumen.map(doc => new Option(doc.judul,doc.id))];
                if (selected?.value && !options.some(option => option.value === selected.value)) options.push(new Option(selected.textContent,selected.value));
                const value = select.value;
                select.replaceChildren(...options); select.value = value;
                state.textContent = data.dokumen.length ? `${data.dokumen.length} SK ditemukan` : 'Tidak ada SK yang sesuai.';
            } catch (error) {if (error.name !== 'AbortError') state.textContent = 'Pencarian gagal. Coba kembali.';}
        }, 300);
    });
})();
</script>
@endpush
