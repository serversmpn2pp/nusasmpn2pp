(() => {
    'use strict';
    const REFRESH_MS = 5000;
    const endpoint = document.body.dataset.endpoint;
    const pameran = document.body.dataset.pameran === 'true';
    const labelTanggal = value => pameran && typeof value === 'string' ? value.replace(/hari ini/gi, 'tanggal terpilih').replace('Minggu ini', 'Minggu data').replace('Bulan ini', 'Bulan data') : value;
    const $ = id => document.getElementById(id);
    const fmt = value => Number(value || 0).toLocaleString('id-ID');
    const set = (id, value) => { $(id).textContent = labelTanggal(value); };
    let data = null, period = 'day', busy = false, blocked = false, lastSuccess = 0;
    let attendanceChart, reportsChart, feedPage = 0, latePage = 0, worshipPage = 0, classPage = 0, metric = 'laporan';
    let knownReports = new Set(), newReports = new Set(), signature = '';
    let generation = 0, activeController = null;
    window.lucide?.createIcons();
    if (window.Chart) {
        Chart.defaults.font.family = '"Instrument Sans", "Segoe UI", Arial, sans-serif';
        Chart.defaults.font.size = matchMedia('(min-width:1600px)').matches ? 15 : 12;
        Chart.defaults.color = '#526c80';
    }
    const connection = (message, state) => {
        set('connection', message);
        $('connection').className = `connection ${state}`;
    };
    function notice(message, danger = false) {
        $('notice').hidden = !message;
        $('notice').className = `notice${danger ? ' danger' : ''}`;
        set('notice', message || '');
        requestAnimationFrame(() => { if (data) { renderFeed(); renderLate(); renderWorship(); } });
    }
    function node(tag, text, className) {
        const el = document.createElement(tag);
        if (text !== undefined) el.textContent = text;
        if (className) el.className = className;
        return el;
    }
    function empty(container, message) { container.replaceChildren(node('p', labelTanggal(message), 'empty')); }
    function paginate(container, items, preferred, page, renderItem) {
        let size = Math.min(preferred, items.length), pages;
        do {
            pages = Math.ceil(items.length / size);
            page %= pages;
            container.replaceChildren(...items.slice(page * size, page * size + size).map(renderItem));
            const bottom = container.getBoundingClientRect().bottom;
            if ([...container.children].every(row => row.getBoundingClientRect().bottom <= bottom + 1) || size === 1) break;
            size--;
        } while (size > 0);
        return {page, pages};
    }
    function renderFeed() {
        const items = data.laporan_terbaru;
        const container = $('feed');
        if (!items.length) return empty(container, 'Belum ada laporan masuk hari ini.');
        const pagination = paginate(container, items, innerHeight < 900 ? 2 : 3, feedPage, item => {
            const row = node('article', undefined, `feed-item${newReports.has(item.id) ? ' new' : ''}`);
            const name = node('strong', item.nama);
            const time = node('time', item.waktu);
            const detail = node('p', `${item.kelas} · ${item.jenis}`);
            row.append(name, time, detail, node('span', item.status, `tag ${item.resmi ? 'teal-tag' : 'yellow-tag'}`));
            return row;
        });
        feedPage = pagination.page;
        set('feed-page', `${pagination.pages > 1 ? `Halaman ${feedPage + 1}/${pagination.pages} · ` : ''}Belum diverifikasi bukan berarti terbukti.`);
    }
    function renderLate() {
        const items = data.terlambat, container = $('late-list');
        if (!items.length) return empty(container, 'Belum ada keterlambatan tercatat.');
        const pagination = paginate(container, items, innerHeight < 900 ? 2 : 3, latePage, item => {
            const row = node('div', undefined, 'late-row'), person = node('div');
            person.append(node('strong', item.nama), node('small', item.kelas));
            row.append(person, node('b', item.menit > 0 ? `${fmt(item.menit)} mnt` : '< 1 mnt'));
            return row;
        });
        latePage = pagination.page;
        set('late-caption', `${fmt(items.length)} siswa · Total ${fmt(data.total_menit_terlambat)} menit${pagination.pages > 1 ? ` · Hal. ${latePage + 1}/${pagination.pages}` : ''}`);
    }
    function renderWorship() {
        const items = data.ibadah, container = $('worship');
        if (!items.length) return empty(container, 'Tidak ada jadwal ibadah aktif hari ini.');
        const pagination = paginate(container, items, innerHeight < 900 ? 1 : 2, worshipPage, item => {
            const row = node('div', undefined, 'worship-row'), title = node('div', undefined, 'row-title');
            title.append(node('span', item.nama), node('small', item.jam));
            const progress = node('div', undefined, 'progress'), bar = node('span');
            bar.style.width = `${Math.max(0, Math.min(100, item.persen))}%`;
            progress.append(bar);
            const meta = node('div', undefined, 'row-meta');
            meta.append(node('span', `${fmt(item.sudah)}/${fmt(item.wajib)} peserta · ${item.persen}%`), node('span', `${fmt(item.belum)} belum tercatat`));
            row.append(title, progress, meta);
            return row;
        });
        worshipPage = pagination.page;
    }
    function chart(existing, id, type, labels, datasets, horizontal = false) {
        if (!window.Chart) return existing;
        if (existing && (existing.config.type !== type || existing.options.indexAxis !== (horizontal ? 'y' : 'x'))) { existing.destroy(); existing = null; }
        if (existing) {
            existing.data.labels = labels;
            existing.data.datasets = datasets;
            existing.update('none');
            return existing;
        }
        return new Chart($(id), {type, data: {labels, datasets}, options: {
            responsive: true, maintainAspectRatio: false, animation: false, indexAxis: horizontal ? 'y' : 'x',
            plugins: {legend: {display: false}},
            scales: {x: {beginAtZero: horizontal, grid: {display: horizontal}, ticks: {precision: 0, maxTicksLimit: 7, maxRotation: 0}}, y: {beginAtZero: true, ticks: {precision: 0, maxTicksLimit: horizontal ? 6 : 5}, grid: {color: '#e6edf3',display: !horizontal}, border: {display: false}}},
            elements: {line: {tension: 0.25, borderWidth: 3}, point: {radius: 0, hitRadius: 12}, bar: {borderRadius: 3}}
        }});
    }
    function renderCharts() {
        const chosen = data.grafik_presensi[period];
        const points = chosen.points;
        attendanceChart = chart(attendanceChart, 'attendance-chart', chosen.type, points.map(p => p.date), [
            {label: 'Hadir', data: points.map(p => p.present), borderColor: '#0f766e', backgroundColor: '#0f766e', spanGaps: false},
            {label: 'Terlambat', data: points.map(p => p.late), borderColor: '#c3990a', backgroundColor: '#d8b332', spanGaps: false}
        ]);
        $('attendance-empty').hidden = !chosen.empty;
        set('attendance-empty', chosen.empty || '');
        set('attendance-caption', `${chosen.date} · ${chosen.subtitle}`);
        renderSecondaryChart();
        if (!window.Chart) notice('Grafik belum dapat dimuat. Ringkasan data tetap tersedia.', true);
    }
    function renderSecondaryChart() {
        const detail = $('secondary-detail');
        detail.dataset.detail = metric === 'kelas' ? 'dipantau' : 'laporan';
        detail.title = metric === 'kelas' ? 'Rincian presensi hari ini' : 'Rincian laporan hari ini';
        detail.setAttribute('aria-label', detail.title);
        if (metric === 'kelas') {
            const size = innerHeight < 900 ? 4 : 6, pages = Math.max(1, Math.ceil(data.kelas.length / size));
            classPage %= pages;
            const rows = data.kelas.slice(classPage * size, classPage * size + size);
            set('secondary-title', 'Presensi per kelas'); set('secondary-eyebrow', 'PRESENSI SEKOLAH');
            set('report-period', 'Hari ini');
            set('secondary-caption', `Hadir dan terlambat (termasuk hadir)${pages > 1 ? ` · Hal. ${classPage + 1}/${pages}` : ''}`);
            reportsChart = chart(reportsChart, 'reports-chart', 'bar', rows.map(p => p.nama), [
                {label: 'Hadir', data: rows.map(p => p.hadir), backgroundColor: '#0f766e', maxBarThickness: 18},
                {label: 'Terlambat', data: rows.map(p => p.terlambat), backgroundColor: '#c3990a', maxBarThickness: 18}
            ], true);
        } else {
            const reportPeriod = period === 'month' ? 'month' : 'week';
            set('secondary-title', 'Laporan masuk'); set('secondary-eyebrow', 'PELAPORAN SISWA');
            set('report-period', reportPeriod === 'month' ? 'Bulan ini' : 'Minggu ini');
            set('secondary-caption', 'Menurut waktu laporan masuk, termasuk yang belum diverifikasi.');
            const reports = data.grafik_laporan[reportPeriod];
            reportsChart = chart(reportsChart, 'reports-chart', 'bar', reports.map(p => p.date), [
                {label: 'Laporan masuk', data: reports.map(p => p.total), backgroundColor: '#15477a', maxBarThickness: 32}
            ]);
        }
    }
    function render(next) {
        const changedDate = data?.tanggal !== next.tanggal;
        data = next;
        const incoming = new Set(data.laporan_terbaru.map(item => item.id));
        newReports = new Set([...incoming].filter(id => !knownReports.has(id)));
        if (changedDate || newReports.size) feedPage = 0;
        if (changedDate) { latePage = 0; worshipPage = 0; }
        knownReports = incoming;
        for (const key of ['hadir', 'izin', 'sakit', 'alfa', 'belum', 'terlambat', 'laporan']) set(`kpi-${key}`, fmt(data.ringkasan[key]));
        set('detail-hadir', `dari ${fmt(data.ringkasan.dipantau)} siswa ${pameran ? 'dipantau' : 'aktif'}`);
        set('detail-terlambat', 'Termasuk dalam hadir');
        set('detail-laporan', 'Masuk hari ini');
        set('detail-belum', `${fmt(data.ringkasan.pengecualian)} tanpa kewajiban scan`);
        set('tanggal', data.tanggal);
        if (pameran) {
            document.body.dataset.tanggal = data.tanggal_data;
            for (const key of ['izin', 'sakit', 'alfa']) set('detail-' + key, 'Tanggal terpilih');
            const date = $('pameran-date');
            date.min = data.batas_tanggal.min; date.max = data.batas_tanggal.max;
            if (document.activeElement !== date && !date.dataset.dirty) date.value = data.tanggal_data;
            set('pameran-date-status', data.identitas_ditampilkan ? 'Identitas diaktifkan oleh admin' : 'Identitas siswa disamarkan');
        }
        set('year', data.tahun);
        set('population', `${fmt(data.ringkasan.dipantau)} siswa dipantau`);
        set('feed-count', `${fmt(data.ringkasan.laporan)} laporan`);
        set('late-total', `${fmt(data.ringkasan.terlambat)} siswa`);
        set('worship-count', `${data.ibadah.length} kegiatan`);
        set('point-balance', fmt(data.poin.saldo));
        set('point-students', `${fmt(data.poin.siswa)} siswa`);
        set('point-added', `+${fmt(data.poin.ditambahkan)}`);
        set('point-reduced', `−${fmt(data.poin.dikurangi)}`);
        set('updated', pameran ? `Diperbarui ${new Date(data.diperbarui_pada).toLocaleString('id-ID', {timeZone:data.zona_waktu,hour12:false})}` : `Data ${new Date(data.diperbarui_pada).toLocaleTimeString('id-ID', {timeZone: data.zona_waktu, hour12: false})} · 5 detik`);
        notice(data.pesan);
        renderFeed(); renderLate(); renderWorship();
        const nextSignature = JSON.stringify([data.grafik_presensi, data.grafik_laporan, data.kelas, period, metric]);
        if (signature !== nextSignature) { renderCharts(); signature = nextSignature; }
    }
    async function refresh() {
        if (busy || blocked || (pameran && window.NusaPameran?.tab() === 'tamu')) return;
        busy = true;
        const current = ++generation;
        const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 4500);
        activeController = controller;
        try {
            const response = await fetch(endpoint, {credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'}, signal: controller.signal});
            if (current !== generation) return;
            if (response.status === 401 || response.status === 403) {
                revoke();
                return;
            }
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const next = await response.json();
            if (blocked || current !== generation) return;
            if (!next.ringkasan || !next.diperbarui_pada) throw new Error('Data tidak valid');
            const age = Date.now() - Date.parse(next.diperbarui_pada);
            render(next);
            lastSuccess = Date.now();
            if (age > 20000) { connection('Data tertunda', 'waiting'); notice('Data server belum diperbarui. Menunggu sinkronisasi.', true); }
            else connection('Terhubung', '');
        } catch (_) {
            if (blocked || current !== generation) return;
            connection(navigator.onLine ? 'Koneksi terputus' : 'Offline', 'offline');
            notice(data ? 'Pembaruan terhenti. Data terakhir tetap ditampilkan; mencoba kembali setiap 5 detik.' : 'Belum dapat memuat data. Memeriksa koneksi setiap 5 detik.', true);
        } finally { clearTimeout(timeout); if(current === generation){busy = false; activeController = null;} }
    }
    function revoke() {
        if (blocked) return;
        blocked = true; data = null;
        window.NusaMonitorRincian?.revoke();
        for (const id of ['feed', 'late-list', 'worship']) empty($(id), 'Akses monitoring dinonaktifkan.');
        for (const key of ['hadir','izin','sakit','alfa','belum','terlambat','laporan']) set(`kpi-${key}`, '-');
        for (const id of ['point-balance','point-students','point-added','point-reduced','population','year','tanggal','feed-count','late-total','worship-count','detail-hadir','detail-belum']) set(id, '-');
        attendanceChart?.destroy(); reportsChart?.destroy();
        $('attendance-empty').hidden = true;
        connection('Akses dicabut', 'offline');
        notice('Akses layar monitoring tidak aktif. Hubungi administrator untuk aktivasi ulang, lalu muat ulang halaman.', true);
        window.dispatchEvent(new Event('monitor-access-revoked'));
    }
    window.addEventListener('monitor-access-revoked', revoke);
    document.querySelectorAll('[data-period]').forEach(button => button.addEventListener('click', () => {
        period = button.dataset.period;
        document.querySelectorAll('[data-period]').forEach(b => b.setAttribute('aria-pressed', String(b === button)));
        if (data) renderCharts();
    }));
    document.querySelectorAll('[data-metric]').forEach(button => button.addEventListener('click', () => {
        metric = button.dataset.metric; classPage = 0;
        document.querySelectorAll('[data-metric]').forEach(b => b.setAttribute('aria-pressed', String(b === button)));
        if (data) renderSecondaryChart();
    }));
    $('fullscreen').addEventListener('click', async () => {
        try { if (document.fullscreenElement) await document.exitFullscreen(); else await document.documentElement.requestFullscreen(); }
        catch (_) { $('fullscreen').title = 'Gunakan F11 untuk layar penuh'; }
    });
    document.addEventListener('fullscreenchange', () => {
        const label = document.fullscreenElement ? 'Keluar layar penuh' : 'Layar penuh';
        $('fullscreen').title = label; $('fullscreen').setAttribute('aria-label', label);
    });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.addEventListener('online', refresh);
    window.addEventListener('resize', () => { if (data) { renderFeed(); renderLate(); renderWorship(); } });
    window.addEventListener('offline', () => { connection('Offline', 'offline'); notice('Koneksi terputus. Data terakhir tetap ditampilkan.', true); });
    setInterval(() => {
        if (lastSuccess && Date.now() - lastSuccess > 20000 && !blocked) connection('Data belum diperbarui', 'offline');
        refresh();
        if (!blocked) window.NusaMonitorRincian?.refresh();
    }, REFRESH_MS);
    setInterval(() => {
        if (!data || blocked || window.NusaMonitorRincian?.isOpen() || (pameran && window.NusaPameran?.tab() === 'tamu')) return;
        feedPage++; latePage++; worshipPage++; classPage++;
        renderFeed(); renderLate(); renderWorship();
        if (metric === 'kelas') renderSecondaryChart();
    }, 15000);
    if(pameran){
        document.querySelector('[data-period="day"]').textContent = 'Tanggal data';
        document.querySelector('[data-detail="alfa"]>div>span').textContent = 'Alfa';
        document.querySelector('.worship-section h2').lastChild.textContent = ' Ibadah siswa';
        document.querySelector('.points-section .heading-actions>.tag').textContent = 'S.d. tanggal data';
        document.querySelector('[data-detail="poin_tambah"]').firstChild.textContent = 'Penambahan tanggal data ';
        document.querySelector('[data-detail="poin_kurang"]').firstChild.textContent = 'Koreksi / pengurangan ';
        document.querySelectorAll('[title],[aria-label]').forEach(el=>{
            for(const attr of ['title','aria-label'])if(el.hasAttribute(attr))el.setAttribute(attr,labelTanggal(el.getAttribute(attr)));
        });
    }
    window.NusaMonitoringTv = {
        refresh,
        pause: () => { generation++; activeController?.abort(); activeController=null; busy=false; },
        clear: () => {
            generation++; activeController?.abort(); activeController=null; busy=false;
            data=null;signature='';knownReports=new Set();newReports=new Set();lastSuccess=0;
            window.NusaMonitorRincian?.reset();
            for(const id of ['feed','late-list','worship'])empty($(id),'Memuat tanggal terpilih...');
            for(const key of ['hadir','izin','sakit','alfa','belum','terlambat','laporan'])set('kpi-'+key,'-');
            for(const id of ['point-balance','point-students','point-added','point-reduced','population','feed-count','late-total','worship-count'])set(id,'-');
            attendanceChart?.destroy(); reportsChart?.destroy(); attendanceChart=null; reportsChart=null;
            set('tanggal','Memuat tanggal terpilih...');
        },
    };
    refresh();
})();
