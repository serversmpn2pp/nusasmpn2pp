(() => {
    'use strict';
    const $ = id => document.getElementById(id);
    const dialog = $('detail-dialog');
    const endpoint = document.body.dataset.detailEndpoint;
    const pameran = document.body.dataset.pameran === 'true';
    const refreshSeconds = pameran ? 3 : 5;
    const dateLabel = text => pameran ? text.replace(/hari ini/gi,'tanggal terpilih') : text;
    const photoPrefix = new URL(endpoint, location.href).pathname.replace(/\/rincian$/, '/foto/');
    const fmt = value => Number(value || 0).toLocaleString('id-ID');
    let jenis = '', page = 1, lastPage = 1, controller = null, generation = 0, debounce;
    let lastKey = '', rowsKey = '', revoked = false;
    const titles = {dipantau:'Siswa dipantau',hadir:'Siswa hadir',izin:'Siswa izin',sakit:'Siswa sakit',alfa:'Siswa alfa',belum:'Siswa belum tercatat',pengecualian:'Tanpa kewajiban scan',terlambat:'Siswa terlambat',laporan:'Laporan masuk hari ini',ibadah:'Ibadah hari ini',poin:'Saldo poin resmi siswa',poin_tambah:'Penambahan poin hari ini',poin_kurang:'Koreksi / pengurangan hari ini'};
    const contexts = {
        laporan:'Termasuk laporan belum diverifikasi, bukan pernyataan terbukti. Identitas konseling dibatasi.',
        ibadah:'Hanya peserta wajib yang hadir di sekolah. Informasi pengecualian pribadi tidak ditampilkan.',
        poin:'Saldo transaksi resmi tahun aktif, setelah koreksi. Bukan usulan poin laporan.',
        poin_tambah:'Transaksi penambahan resmi hari ini, bukan usulan poin laporan.',
        poin_kurang:'Transaksi koreksi / pengurangan resmi hari ini.',
        belum:'Belum ada catatan presensi hari ini, bukan otomatis alfa. Pengecualian tidak termasuk daftar ini.',
        terlambat:'Termasuk dalam hadir. Keterlambatan beberapa detik ditandai kurang dari 1 menit.',
    };
    function element(tag, text, className) {
        const el = document.createElement(tag);
        if (text !== undefined) el.textContent = text;
        if (className) el.className = className;
        return el;
    }
    function message(text) {
        $('detail-message').hidden = !text;
        $('detail-message').textContent = text;
    }
    function options(id, items, first) {
        const select = $(id);
        const signature = JSON.stringify(items);
        if (select.dataset.signature === signature) return;
        const value = select.value;
        select.replaceChildren(new Option(first, ''), ...items.map(item => new Option(item.nama, String(item.id))));
        select.value = [...select.options].some(option => option.value === value) ? value : '';
        select.dataset.signature = signature;
    }
    function row(item) {
        const tr = element('tr');
        tr.dataset.id = item.id;
        tr.dataset.signature = JSON.stringify(item);
        const personCell = element('td'), person = element('div', undefined, 'detail-person');
        const initials = String(item.nama || '').trim().split(/\s+/).slice(0, 2).map(word => word[0]).join('').toUpperCase();
        const avatar = element('span', initials, 'detail-avatar');
        avatar.setAttribute('aria-hidden', 'true');
        if (item.foto_url) {
            const url = new URL(item.foto_url, location.href);
            if (url.origin === location.origin && url.pathname.startsWith(photoPrefix)) {
                const img = element('img');
                img.alt = ''; img.loading = 'lazy'; img.width = 52; img.height = 64;
                img.addEventListener('error', () => { avatar.textContent = initials; }, {once:true});
                img.src = url.href; avatar.replaceChildren(img);
            }
        }
        person.append(avatar, element('span', item.nama)); personCell.append(person);
        const kelas = element('td', item.kelas), status = element('td'), waktu = element('td');
        const tone = item.status === 'Hadir' || item.status === 'Sudah tercatat' || item.resmi ? 'teal-tag' : 'yellow-tag';
        status.append(element('span', item.status, `tag ${tone}`));
        waktu.append(element('time', item.waktu || '-'));
        let extra = item.jenis || item.kegiatan || '-';
        if (item.terlambat) extra = item.menit > 0 ? `Terlambat ${fmt(item.menit)} menit` : 'Terlambat < 1 menit';
        if (item.poin !== undefined) extra = `${item.poin > 0 && jenis !== 'poin' ? '+' : ''}${fmt(item.poin)} poin`;
        const keterangan = element('td', extra);
        [kelas,status,waktu,keterangan].forEach((cell, index) => { cell.dataset.label = ['Kelas','Status','Waktu','Keterangan'][index]; });
        tr.append(personCell, kelas, status, waktu, keterangan);
        return tr;
    }
    function render(data) {
        $('detail-title').textContent = data.judul;
        $('detail-subtitle').textContent = data.tanggal;
        options('detail-class', data.kelas, 'Semua kelas');
        options('detail-activity', data.kegiatan, 'Semua kegiatan');
        page = data.pagination.page; lastPage = data.pagination.last_page;
        const total = data.pagination.total;
        const unit = jenis === 'laporan' ? 'laporan' : jenis.startsWith('poin_') ? 'transaksi' : jenis === 'ibadah' ? 'catatan peserta' : 'siswa';
        const first = total ? (page - 1) * data.pagination.per_page + 1 : 0;
        $('detail-count').textContent = `${fmt(first)}-${fmt(first ? first + data.items.length - 1 : 0)} dari ${fmt(total)} ${unit}`;
        $('detail-page').textContent = `${page} / ${lastPage}`;
        $('detail-prev').disabled = page <= 1;
        $('detail-next').disabled = page >= lastPage;
        $('detail-updated').textContent = `Data ${new Date(data.diperbarui_pada).toLocaleTimeString('en-GB', {timeZone:data.zona_waktu,hourCycle:'h23'})} \u00b7 ${refreshSeconds} detik`;
        $('detail-empty').hidden = data.items.length !== 0;
        const signature = JSON.stringify(data.items);
        if (rowsKey !== signature) {
            const scroll = $('detail-scroll'), top = scroll.scrollTop;
            const existing = new Map([...$('detail-rows').children].map(el => [el.dataset.id, el]));
            $('detail-rows').replaceChildren(...data.items.map(item => {
                const old = existing.get(item.id);
                return old?.dataset.signature === JSON.stringify(item) ? old : row(item);
            }));
            scroll.scrollTop = top;
            rowsKey = signature;
        }
        const age = Date.now() - Date.parse(data.diperbarui_pada);
        message(age > 20000 ? 'Data server tertunda. Waktu pembaruan terakhir tertera di atas.' : '');
    }
    async function load(replace = false) {
        if (!dialog.open || revoked) return;
        if (controller && !replace) return;
        controller?.abort();
        const current = ++generation;
        const url = new URL(endpoint, location.href);
        const values = {jenis,page,q:$('detail-search').value.trim(),kelas_id:$('detail-class').value};
        if (jenis === 'ibadah') Object.assign(values, {kegiatan_id:$('detail-activity').value,status_ibadah:$('detail-worship').value});
        Object.entries(values).forEach(([key,value]) => { if (value !== '') url.searchParams.set(key,value); });
        const key = url.search;
        if (lastKey !== key) {
            $('detail-rows').replaceChildren(); rowsKey = ''; lastKey = key;
            $('detail-empty').hidden = true;
            $('detail-count').textContent = 'Memuat rincian...';
            $('detail-updated').textContent = '';
            $('detail-prev').disabled = true; $('detail-next').disabled = true;
            message('');
        }
        const request = new AbortController(); controller = request;
        const timeout = setTimeout(() => request.abort(),4500);
        $('detail-scroll').setAttribute('aria-busy','true');
        try {
            const response = await fetch(url, {credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:request.signal});
            if (response.status === 401 || response.status === 403) {
                window.dispatchEvent(new Event('monitor-access-revoked')); return;
            }
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            if (pameran && data.tanggal_data !== document.body.dataset.tanggal) return;
            if (!Array.isArray(data.items) || !data.pagination || data.jenis !== jenis) throw new Error('Data tidak valid');
            if (current === generation && dialog.open && !revoked) render(data);
        } catch (_) {
            if (current !== generation || !dialog.open || revoked) return;
            message(`Rincian belum dapat diperbarui. Data terakhir, bila ada, tetap ditampilkan. Mencoba kembali setiap ${refreshSeconds} detik.`);
            if (!$('detail-rows').children.length) $('detail-count').textContent = 'Rincian belum tersedia';
        } finally {
            clearTimeout(timeout);
            if (current === generation) { controller = null; $('detail-scroll').setAttribute('aria-busy','false'); }
        }
    }
    function changeFilter() { page = 1; load(true); }
    function open(button) {
        if (revoked) return;
        jenis = button.dataset.detail;
        if (!titles[jenis]) return;
        page = 1; lastPage = 1; lastKey = ''; rowsKey = '';
        $('detail-search').value = ''; $('detail-class').value = ''; $('detail-activity').value = ''; $('detail-worship').value = '';
        $('detail-activity-filter').hidden = jenis !== 'ibadah';
        $('detail-worship-filter').hidden = jenis !== 'ibadah';
        $('detail-title').textContent = dateLabel(titles[jenis]); $('detail-subtitle').textContent = dateLabel('Hari ini');
        $('detail-context').textContent = dateLabel(contexts[jenis] || 'Presensi hari ini. Alasan medis dan catatan pribadi tidak ditampilkan.');
        $('detail-rows').replaceChildren(); message('');
        document.body.classList.add('detail-open'); dialog.showModal();
        load(true);
    }
    document.querySelectorAll('[data-detail]').forEach(button => {
        button.setAttribute('aria-haspopup','dialog'); button.setAttribute('aria-controls','detail-dialog');
    });
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-detail]');
        if (button) open(button);
    });
    $('detail-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) {
        const box = dialog.getBoundingClientRect();
        if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close();
    } });
    dialog.addEventListener('close', () => {
        clearTimeout(debounce); generation++; controller?.abort(); controller = null;
        $('detail-rows').replaceChildren(); rowsKey = ''; lastKey = '';
        document.body.classList.remove('detail-open');
    });
    $('detail-search').addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(changeFilter,300); });
    for (const id of ['detail-class','detail-activity','detail-worship']) $(id).addEventListener('change',changeFilter);
    $('detail-reset').addEventListener('click', () => {
        clearTimeout(debounce);
        for (const id of ['detail-search','detail-class','detail-activity','detail-worship']) $(id).value = '';
        changeFilter();
    });
    $('detail-prev').addEventListener('click', () => { if (page > 1) { page--; load(true); } });
    $('detail-next').addEventListener('click', () => { if (page < lastPage) { page++; load(true); } });
    window.NusaMonitorRincian = {
        refresh: () => load(), isOpen: () => dialog.open,
        reset: () => { generation++; controller?.abort(); controller=null; dialog.close(); $('detail-rows').replaceChildren(); rowsKey=''; lastKey=''; },
        revoke: () => {
            revoked = true; generation++; controller?.abort(); controller = null; clearTimeout(debounce);
            dialog.close(); $('detail-rows').replaceChildren(); $('detail-count').textContent = '';
            $('detail-updated').textContent = ''; message('');
        },
    };
})();
