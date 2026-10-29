(() => {
    'use strict';
    if (document.body.dataset.pameran !== 'true') return;
    const $ = id => document.getElementById('guest-detail-' + id);
    const dialog = document.getElementById('guest-dialog');
    const endpoint = document.body.dataset.pameranRincianEndpoint;
    const titles = {total:'Pengunjung tercatat',hari_ini:'Pengunjung hari ini',puas:'Pengunjung puas / sangat puas',nusa:'Pengunjung yang menilai NUSA',harian:'Kunjungan per hari',kepuasan:'Kepuasan pengunjung',minat:'Minat pengunjung',terbaru:'Pengunjung terbaru'};
    const fields = ['search','category','date','score','interest'];
    let jenis = '', page = 1, lastPage = 1, request = null, generation = 0, revoked = false, debounce, rowKey = '', filterKey = '';
    function message(text) { $('message').textContent = text; $('message').hidden = !text; }
    function element(tag, text) { const el = document.createElement(tag); el.textContent = text; return el; }
    function columns() {
        const penilaian = ['puas','kepuasan','nusa'].includes(jenis);
        const labels = [['nama','Pengunjung'],['kategori','Kategori']];
        if (penilaian) labels.push(['nilai',jenis === 'nusa' ? 'Nilai NUSA' : 'Nilai stand'],['predikat','Predikat'],['kunjungan','Waktu (WIB)']);
        else if (jenis === 'minat') labels.push(['minat','Pilihan minat'],['kunjungan','Waktu (WIB)']);
        else labels.push(['tanggal','Tanggal'],['waktu','Waktu (WIB)']);
        $('table').dataset.kind = penilaian ? 'penilaian' : jenis === 'minat' ? 'minat' : 'pengunjung';
        $('columns').replaceChildren(...labels.map(([key,label]) => {
            const th = element('th',label); th.scope = 'col'; th.dataset.column = key; return th;
        }));
    }
    function row(item, zone) {
        const tr = document.createElement('tr');
        const cell = (label,text) => { const td = element('td',text); td.dataset.label = label; tr.append(td); return td; };
        cell('Pengunjung',item.nama).className = 'guest-person-name';
        cell('Kategori',item.label_kategori);
        const time = new Date(item.waktu);
        const date = time.toLocaleDateString('id-ID',{timeZone:zone,day:'numeric',month:'short',year:'numeric'});
        const clock = time.toLocaleTimeString('en-GB',{timeZone:zone,hourCycle:'h23'});
        const penilaian = ['puas','kepuasan','nusa'].includes(jenis);
        if (penilaian) {
            const nilai = jenis === 'nusa' ? item.nilai_nusa : item.kepuasan;
            const predikat = jenis === 'nusa' ? item.label_nusa : item.label_kepuasan;
            const score = cell(jenis === 'nusa' ? 'Nilai NUSA' : 'Nilai stand','');
            score.className = 'guest-rating-number'; score.append(element('strong',nilai == null ? '-' : `${nilai} / 5`));
            const badge = element('span',predikat || 'Belum menilai'); badge.className = 'tag guest-rating-label'; badge.dataset.score = nilai ?? '';
            cell('Predikat','').append(badge);
        } else if (jenis === 'minat') {
            const tags = element('div',''); tags.className = 'guest-interest-tags';
            for (const label of item.label_minat || []) tags.append(element('span',label));
            const minat = cell('Pilihan minat',''); minat.className = 'guest-interest-cell'; minat.append(tags);
        }
        if (penilaian || jenis === 'minat') {
            const waktu = cell('Waktu (WIB)',''); waktu.className = 'guest-visit-time';
            const jam = element('time',clock); jam.dateTime = item.waktu;
            waktu.append(element('span',date),jam);
        } else {
            cell('Tanggal',date); cell('Waktu (WIB)',clock).className = 'guest-visit-time';
        }
        return tr;
    }
    function render(data) {
        $('title').textContent = data.judul;
        $('subtitle').textContent = data.acara;
        $('context').textContent = data.konteks;
        page = data.pagination.page; lastPage = data.pagination.last_page;
        const total = data.pagination.total, first = total ? (page - 1) * data.pagination.per_page + 1 : 0;
        $('count').textContent = `${first}-${first ? first + data.items.length - 1 : 0} dari ${total.toLocaleString('id-ID')} pengunjung`;
        $('page').textContent = `${page} / ${lastPage}`;
        $('prev').disabled = page <= 1; $('next').disabled = page >= lastPage;
        $('updated').textContent = new Date(data.diperbarui_pada).toLocaleTimeString('en-GB', {timeZone:data.zona_waktu,hourCycle:'h23'}) + ' · 3 detik';
        $('empty').hidden = data.items.length !== 0;
        const key = JSON.stringify(data.items);
        if (key !== rowKey) {
            const top = $('scroll').scrollTop;
            $('rows').replaceChildren(...data.items.map(item => row(item,data.zona_waktu)));
            $('scroll').scrollTop = top; rowKey = key;
        }
        message(Date.now() - Date.parse(data.diperbarui_pada) > 20000 ? 'Data server tertunda. Waktu pembaruan terakhir tertera di atas.' : '');
    }
    async function load(replace = false) {
        if (!dialog.open || revoked || (request && !replace)) return;
        request?.abort();
        const current = ++generation, controller = new AbortController(); request = controller;
        const url = new URL(endpoint, location.href);
        const values = {jenis,page,q:$('search').value.trim(),kategori:$('category').value};
        if (jenis === 'harian') values.tanggal = $('date').value;
        if (jenis === 'kepuasan') values.nilai = $('score').value;
        if (jenis === 'minat') values.minat = $('interest').value;
        Object.entries(values).forEach(([key,value]) => { if (value !== '') url.searchParams.set(key,value); });
        if (filterKey !== url.search) {
            filterKey = url.search; rowKey = ''; $('rows').replaceChildren(); $('empty').hidden = true;
            $('count').textContent = 'Memuat rincian...'; $('updated').textContent = '';
            $('prev').disabled = true; $('next').disabled = true; message('');
        }
        const timeout = setTimeout(() => controller.abort(),4500);
        $('scroll').setAttribute('aria-busy','true');
        try {
            const response = await fetch(url, {credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
            if (current !== generation || revoked || !dialog.open) return;
            if ([401,403].includes(response.status)) { window.dispatchEvent(new Event('monitor-access-revoked')); return; }
            if (response.status === 410) { window.dispatchEvent(new Event('pameran-event-unavailable')); return; }
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            if (!Array.isArray(data.items) || !data.pagination || data.jenis !== jenis) throw new Error('Data tidak valid');
            if (current === generation && dialog.open && !revoked) render(data);
        } catch (_) {
            if (current === generation && dialog.open && !revoked) message('Rincian belum dapat diperbarui. Mencoba kembali setiap 3 detik.');
        } finally {
            clearTimeout(timeout);
            if (current === generation) { request = null; $('scroll').setAttribute('aria-busy','false'); }
        }
    }
    function reset() {
        clearTimeout(debounce); generation++; request?.abort(); request = null;
        dialog.close(); $('rows').replaceChildren(); rowKey = ''; filterKey = ''; message('');
        $('count').textContent = ''; $('updated').textContent = '';
    }
    function open(type, preset = {}) {
        if (revoked || !titles[type] || window.NusaPameran?.tab() !== 'tamu') return;
        reset(); window.NusaMonitorRincian?.reset();
        jenis = type; page = 1; lastPage = 1;
        columns();
        fields.forEach(id => { $(id).value = ''; });
        $('date').value = preset.tanggal || ''; $('score').value = preset.nilai || ''; $('interest').value = preset.minat || '';
        for (const [id,active] of [['date','harian'],['score','kepuasan'],['interest','minat']]) $(id + '-filter').hidden = jenis !== active;
        $('title').textContent = titles[jenis]; $('count').textContent = 'Memuat rincian...'; $('context').textContent = '';
        document.body.classList.add('detail-open'); dialog.showModal(); load(true);
    }
    function changeFilter() { clearTimeout(debounce); page = 1; load(true); }
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-guest-detail]');
        if (button) open(button.dataset.guestDetail, {minat:button.dataset.guestInterest});
    });
    document.querySelectorAll('[data-guest-detail]').forEach(el => {
        el.setAttribute('aria-haspopup','dialog'); el.setAttribute('aria-controls','guest-dialog');
    });
    $('close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const box = dialog.getBoundingClientRect();
        if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close();
    });
    dialog.addEventListener('close', () => {
        clearTimeout(debounce); generation++; request?.abort(); request = null;
        $('rows').replaceChildren(); rowKey = ''; filterKey = '';
        document.body.classList.remove('detail-open');
    });
    $('search').addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(changeFilter,300); });
    for (const id of fields.slice(1)) $(id).addEventListener('change',changeFilter);
    $('reset').addEventListener('click', () => { fields.forEach(id => { $(id).value = ''; }); changeFilter(); });
    $('prev').addEventListener('click', () => { if (page > 1) { page--; load(true); } });
    $('next').addEventListener('click', () => { if (page < lastPage) { page++; load(true); } });
    window.NusaGuestRincian = {
        open, reset, refresh:() => load(), isOpen:() => dialog.open,
        options(data) {
            const signature = JSON.stringify(data.statistik.label_minat);
            if ($('interest').dataset.signature !== signature) {
                const selected = $('interest').value;
                $('interest').replaceChildren(new Option('Semua minat',''), ...Object.entries(data.statistik.label_minat).map(([key,label]) => new Option(label,key)));
                $('interest').value = selected; $('interest').dataset.signature = signature;
            }
            $('date').min = data.acara.mulai; $('date').max = data.acara.selesai;
        },
    };
    window.addEventListener('monitor-access-revoked', () => { revoked = true; reset(); });
    window.addEventListener('pameran-event-unavailable', reset);
    window.addEventListener('online', () => load());
})();
