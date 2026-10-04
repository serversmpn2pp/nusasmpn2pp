@push('scripts')
@php($pesertaAwal = old('peserta', $prestasi->peserta->map(fn ($p) => $p->only(['siswa_id', 'pegawai_id', 'nama', 'kelas']))->all()))
<script>
(() => {
    const form = document.querySelector('[data-prestasi-form]');
    if (!form) return;
    const one = s => form.querySelector(s), recipient = one('#penerima'), shape = one('#bentuk');
    const search = one('#cari_penerima'), state = one('[data-participant-state]'), results = one('[data-results]');
    const raw = @json($pesertaAwal);
    let selected = Array.isArray(raw) ? raw.filter(p => p && typeof p === 'object') : [];
    let previous = recipient.value, controller, revision = 0, nextPage = null, query = '';
    const endpoint = @json(route('prestasi-sekolah.pilihan'));
    const render = () => {
        const list = one('[data-selected]'), inputs = one('[data-participant-inputs]');
        list.replaceChildren(); inputs.replaceChildren();
        selected.forEach((p, i) => {
            const li = document.createElement('li'), label = document.createElement('span'), remove = document.createElement('button');
            label.textContent = `${p.nama || 'Identitas dipilih'}${p.kelas ? ` (${p.kelas})` : ''}${p.siswa_id || p.pegawai_id ? ' - NUSA' : ' - Manual'}`;
            remove.type = 'button'; remove.className = 'prestasi-remove'; remove.textContent = '\u00d7'; remove.title = 'Hapus penerima'; remove.setAttribute('aria-label', `Hapus penerima ${p.nama || ''}`);
            remove.addEventListener('click', () => {selected.splice(i, 1); render();});
            li.append(label, remove); list.append(li);
            for (const key of ['siswa_id', 'pegawai_id', 'nama', 'kelas']) {
                const input = document.createElement('input'); input.type = 'hidden'; input.name = `peserta[${i}][${key}]`; input.value = p[key] || ''; inputs.append(input);
            }
        });
        search.setCustomValidity(recipient.value !== 'sekolah' && (!selected.length || (shape.value === 'individu' && selected.length !== 1)) ? 'Tambahkan satu penerima untuk individu, atau anggota/perwakilan untuk tim.' : '');
    };
    const add = p => {
        if (selected.length >= 100) {state.textContent = 'Maksimal 100 penerima per prestasi.'; return;}
        if (shape.value === 'individu' && selected.length) {state.textContent = 'Individu hanya memiliki satu penerima. Hapus penerima lama atau pilih Tim.'; return;}
        const key = p.siswa_id ? 'siswa_id' : p.pegawai_id ? 'pegawai_id' : null;
        if (selected.some(s => key ? String(s[key]) === String(p[key]) : !s.siswa_id && !s.pegawai_id && s.nama.toLocaleLowerCase() === p.nama.toLocaleLowerCase())) {state.textContent = 'Penerima ini sudah dipilih.'; return;}
        selected.push(p); state.textContent = ''; render();
    };
    const syncType = () => {
        const school = recipient.value === 'sekolah';
        one('[data-participants]').hidden = school;
        search.disabled = school;
        one('[data-school-recipient]').hidden = !school;
        for (const option of shape.options) {option.disabled = school ? option.value !== 'sekolah' : option.value === 'sekolah'; option.hidden = option.disabled;}
        if (school) shape.value = 'sekolah';
        else if (shape.value === 'sekolah') shape.value = 'individu';
        const team = shape.value === 'tim';
        one('[data-team]').hidden = !team; one('#nama_tim').required = team; one('#nama_tim').disabled = !team;
        one('[data-manual-class]').hidden = recipient.value !== 'siswa';
        render();
    };
    recipient.addEventListener('change', () => {
        if (selected.length && !confirm('Mengganti jenis penerima akan mengosongkan daftar yang dipilih. Lanjutkan?')) {recipient.value = previous; return;}
        selected = []; previous = recipient.value; controller?.abort(); ++revision; results.replaceChildren(); one('[data-more]').hidden = true; state.textContent = ''; syncType();
    });
    shape.addEventListener('change', syncType);
    one('[data-add-manual]').addEventListener('click', () => {
        const input = one('#nama_manual'), name = input.value.trim().replace(/\s+/g, ' ');
        input.setCustomValidity(name ? '' : 'Isi nama penerima.');
        if (!input.reportValidity()) return;
        add({nama:name,kelas:recipient.value === 'siswa' ? one('#kelas_manual').value.trim() : '',siswa_id:null,pegawai_id:null});
        input.value = ''; one('#kelas_manual').value = '';
    });
    one('#nama_manual').addEventListener('input', event => event.target.setCustomValidity(''));
    const load = async (page = 1) => {
        controller?.abort(); controller = new AbortController(); const signal = controller.signal, current = ++revision;
        if (page === 1) {query = search.value.trim(); results.replaceChildren();}
        state.textContent = 'Mencari penerima...'; one('[data-more]').hidden = true;
        try {
            const url = new URL(endpoint); url.searchParams.set('jenis', recipient.value); url.searchParams.set('q', query); url.searchParams.set('page', page);
            const response = await fetch(url, {signal, headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error(); const data = await response.json();
            if (current !== revision || signal.aborted) return;
            for (const p of data.data) {
                const li = document.createElement('li'), button = document.createElement('button'); button.type = 'button'; button.textContent = p.nama_lengkap;
                button.addEventListener('click', () => add({siswa_id:recipient.value === 'siswa' ? p.id : null,pegawai_id:recipient.value === 'pegawai' ? p.id : null,nama:p.nama_lengkap,kelas:null}));
                li.append(button); results.append(li);
            }
            nextPage = data.next_page; one('[data-more]').hidden = !nextPage; state.textContent = data.data.length ? '' : 'Tidak ada identitas yang sesuai.';
        } catch (error) {if (error.name !== 'AbortError' && current === revision) state.textContent = 'Pencarian gagal. Coba kembali.';}
    };
    one('[data-search]').addEventListener('click', () => load());
    one('[data-more]').addEventListener('click', () => load(nextPage));
    search.addEventListener('keydown', event => {if (event.key === 'Enter') {event.preventDefault(); load();}});
    const syncStatus = () => {
        const verified = one('#status').value === 'terverifikasi', method = one('[name="metode"]:checked')?.value;
        one('[data-verification]').hidden = !verified;
        const check = one('[name="konfirmasi_prestasi"]'); check.disabled = !verified; check.required = verified;
        one('#tautan').required = verified && (['tanpa','hapus'].includes(method) || (method === 'tetap' && form.dataset.buktiTersimpan !== '1'));
    };
    one('#status').addEventListener('change', syncStatus);
    form.querySelectorAll('[name="metode"]').forEach(input => input.addEventListener('change', syncStatus));
    one('#berkas')?.addEventListener('change', event => {
        const file = event.target.files[0];
        event.target.setCustomValidity(file && file.size > 10 * 1024 * 1024 ? 'Maksimal 10 MB untuk bukti prestasi.' : file && !['application/pdf','image/jpeg','image/png','image/webp'].includes(file.type) ? 'Pilih PDF atau gambar.' : '');
    });
    syncType(); syncStatus();
})();
</script>
@endpush
