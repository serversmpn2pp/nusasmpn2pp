<script>
(() => {
    const form = document.querySelector('[data-alumni-form]');
    if (!form) return;
    const one = selector => form.querySelector(selector);
    const search = one('#cari_siswa'), member = one('#anggota_kelas_id');
    const school = one('[data-school-fields]'), trace = one('#status_penelusuran');
    const state = one('[data-source-state]'), results = one('[data-source-results]');
    const more = one('[data-source-more]'), searchButton = one('[data-cari-siswa]');
    let selected = @json($sumberSiswa), nextPage = null, query = '', pending = null, revision = 0;
    const endpoint = @json(route('alumni-humas.siswa'));
    const alumniId = @json($alumni->id);
    const today = @json(today()->format('Y-m-d'));
    const isLinked = () => one('[name="metode"]:checked').value === 'siswa';
    const syncMember = (copy = false) => {
        const option = member.selectedOptions[0];
        one('#kelas_terakhir').readOnly = isLinked() && !!member.value;
        if (copy && member.value) {
            one('#kelas_terakhir').value = option.dataset.nama || '';
            if (option.dataset.tahun) one('#tahun_lulus').value = option.dataset.tahun;
        }
        syncDates();
    };
    const syncDates = () => {
        const year = Number(one('#tahun_lulus').value);
        one('#tahun_masuk').max = year >= 1900 ? year : today.slice(0, 4);
        one('#tanggal_lulus').min = year >= 1900 ? `${year}-01-01` : '';
        one('#tanggal_lulus').max = year >= 1900 && year < Number(today.slice(0, 4)) ? `${year}-12-31` : today;
        one('#tanggal_penelusuran').min = one('#tanggal_lulus').value || (year >= 1900 ? `${year}-01-01` : '');
    };
    const syncSource = () => {
        const linked = isLinked();
        one('[data-source-panel]').hidden = !linked;
        one('[data-member-panel]').hidden = !linked || !selected?.kelas?.length;
        member.disabled = !linked;
        search.required = linked && !selected;
        search.setCustomValidity(linked && !selected ? 'Pilih siswa dari hasil pencarian terlebih dahulu.' : '');
        form.querySelectorAll('[data-identity]').forEach(input => input.readOnly = linked);
        one('#jenis_kelamin').disabled = linked;
        one('#siswa_id').value = linked && selected ? selected.id : '';
        if (linked && selected) for (const key of ['nama_lengkap', 'nis', 'nisn', 'jenis_kelamin']) one(`#${key}`).value = selected[key] || '';
        one('[data-source-selected]').textContent = linked && selected ? `Dipilih: ${selected.nama_lengkap}` : '';
        syncMember();
    };
    const choose = student => {
        pending?.abort(); ++revision;
        searchButton.removeAttribute('aria-busy');
        selected = student;
        for (const key of ['nama_lengkap', 'nis', 'nisn', 'jenis_kelamin']) one(`#${key}`).value = student[key] || '';
        member.replaceChildren(new Option('Tidak dikaitkan', ''));
        for (const item of student.kelas) {
            const option = new Option(`${item.nama} - ${item.tahun_pelajaran || ''}`, item.id);
            option.dataset.nama = item.nama;
            option.dataset.tahun = item.tahun_lulus || '';
            member.append(option);
        }
        results.replaceChildren();
        more.hidden = true;
        state.textContent = '';
        syncSource();
    };
    const load = async (page = 1) => {
        pending?.abort();
        const controller = new AbortController();
        pending = controller;
        const current = ++revision;
        if (page === 1) { query = search.value.trim(); results.replaceChildren(); }
        const url = new URL(endpoint);
        url.searchParams.set('q', query);
        url.searchParams.set('page', page);
        if (alumniId) url.searchParams.set('alumni_id', alumniId);
        state.textContent = 'Mencari siswa...';
        more.hidden = true;
        searchButton.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {signal:controller.signal,headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error('Gagal memuat siswa. Coba cari kembali.');
            const payload = await response.json();
            if (current !== revision || !isLinked()) return;
            for (const student of payload.data) {
                const li = document.createElement('li'), button = document.createElement('button');
                button.type = 'button';
                const name = document.createElement('strong'), info = document.createElement('small');
                name.textContent = student.nama_lengkap;
                info.textContent = `NIS ${student.nis || '-'} / NISN ${student.nisn || '-'}`;
                button.append(name, info);
                button.addEventListener('click', () => choose(student));
                li.append(button); results.append(li);
            }
            nextPage = payload.next_page;
            more.hidden = !nextPage;
            state.textContent = results.children.length ? '' : 'Siswa tidak ditemukan atau sudah tercatat sebagai alumni.';
        } catch (error) {
            if (error.name !== 'AbortError' && current === revision) state.textContent = error.message;
        } finally {
            if (current === revision) searchButton.removeAttribute('aria-busy');
        }
    };
    form.querySelectorAll('[name="metode"]').forEach(input => input.addEventListener('change', () => {
        pending?.abort(); ++revision;
        searchButton.removeAttribute('aria-busy');
        syncSource();
    }));
    searchButton.addEventListener('click', () => load());
    more.addEventListener('click', () => load(nextPage));
    search.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); load(); } });
    member.addEventListener('change', () => syncMember(true));
    for (const id of ['tahun_lulus', 'tanggal_lulus']) one(`#${id}`).addEventListener('change', syncDates);
    const syncTrace = () => {
        const continues = trace.value === 'melanjutkan', known = trace.value !== 'belum_terdata';
        school.hidden = !continues;
        school.disabled = !continues;
        one('#jenis_sekolah').required = continues;
        one('#nama_sekolah').required = continues;
        one('[data-trace-date]').hidden = !known;
        one('#tanggal_penelusuran').disabled = !known;
        one('#tanggal_penelusuran').required = known;
        one('#catatan_penelusuran').required = trace.value === 'tidak_melanjutkan';
        one('[data-trace-note-label]').textContent = trace.value === 'tidak_melanjutkan' ? 'Alasan tidak melanjutkan (privat)' : 'Catatan penelusuran (privat)';
    };
    trace.addEventListener('change', syncTrace);
    syncSource(); syncTrace();
})();
</script>
