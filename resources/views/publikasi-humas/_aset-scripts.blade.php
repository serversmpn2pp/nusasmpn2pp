<script>
(() => {
    const search = document.querySelector('[data-aset-search]');
    if (!search) return;
    const list = document.querySelector('[data-aset-picks]');
    const state = document.querySelector('[data-aset-pick-state]');
    let timer, controller;
    search.addEventListener('input', () => {
        clearTimeout(timer);
        controller?.abort();
        timer = setTimeout(async () => {
            controller = new AbortController();
            const signal = controller.signal;
            state.textContent = 'Mencari aset...';
            try {
                const url = new URL(search.dataset.asetSearch, location.origin);
                url.searchParams.set('cari', search.value);
                const response = await fetch(url, {headers:{Accept:'application/json'}, signal});
                if (!response.ok) throw new Error();
                const data = await response.json();
                if (signal.aborted) return;
                const matches = new Set(data.aset.map(asset => String(asset.id)));
                list.querySelectorAll('[data-aset-id]').forEach(row => { row.hidden = !matches.has(row.dataset.asetId) && !row.querySelector('[name="aset_ids[]"]:checked'); });
                data.aset.forEach(asset => {
                    if ([...list.children].some(row => row.dataset.asetId === String(asset.id))) return;
                    const row = document.createElement('div');
                    row.className = 'aset-pick'; row.dataset.asetId = asset.id;
                    const label = document.createElement('label');
                    const input = document.createElement('input');
                    input.type = 'checkbox'; input.name = 'aset_ids[]'; input.value = asset.id;
                    const text = document.createElement('span');
                    text.textContent = `${asset.nama} - ${asset.kategori} - Versi ${asset.versi}`;
                    label.append(input, text); row.append(label); list.append(row);
                });
                state.textContent = data.aset.length ? `${data.aset.length} aset ditemukan` : 'Tidak ada aset yang sesuai.';
            } catch (error) {
                if (error.name !== 'AbortError') state.textContent = 'Pencarian gagal. Coba kembali.';
            }
        }, 300);
    });
    list.addEventListener('change', event => {
        if (event.target.name === 'aset_ids[]' && !event.target.checked) {
            const refresh = event.target.closest('.aset-pick').querySelector('[name="perbarui_aset[]"]');
            if (refresh) refresh.checked = false;
        }
        if (event.target.name === 'perbarui_aset[]' && event.target.checked) event.target.closest('.aset-pick').querySelector('[name="aset_ids[]"]').checked = true;
    });
})();
</script>
