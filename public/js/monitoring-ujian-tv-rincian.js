(() => {
    'use strict';
    const $ = id => document.getElementById(id), dialog = $('exam-detail-dialog');
    const fmt = n => Number(n || 0).toLocaleString('id-ID');
    const labels = {mengerjakan:'Mengerjakan',selesai:'Selesai',menunggu:'Belum mulai',belum_hadir:'Belum hadir',hadir_belum_mulai:'Hadir, belum mulai',tidak_hadir:'Tidak hadir',perlu_perhatian:'Perlu perhatian',terblokir:'Terblokir',nonaktif:'Nonaktif'};
    let data = null, mode = 'peserta', page = 1, lastPage = 1, participantId = null, blocked = false, warning = '', signature = '';
    let serverTime = 0, receivedTime = 0, opener = null;
    const node = (tag,text,cls) => { const e = document.createElement(tag); if (text !== undefined) e.textContent = text; if (cls) e.className = cls; return e; };
    const time = value => value ? new Date(value).toLocaleTimeString('id-ID',{timeZone:data?.zona_waktu || 'Asia/Jakarta',hour12:false}) : '-';
    function message(text) { $('exam-detail-message').textContent = text; $('exam-detail-message').hidden = !text; }
    function options(id,rows,label) {
        const select = $(id), key = JSON.stringify(rows), value = select.value;
        if (select.dataset.options === key) return;
        select.replaceChildren(new Option(label,''), ...rows.map(r => new Option(r.nama,String(r.id))));
        select.value = rows.some(r => String(r.id) === value) ? value : ''; select.dataset.options = key;
    }
    function cell(label,...children) {
        const td = node('td'); td.dataset.label = label; td.append(...children); return td;
    }
    function person(p) {
        const result = node('div',undefined,'exam-detail-person');
        result.append(node('strong',p.nama),node('small',p.kelas || '-')); return result;
    }
    function participant(p,rooms) {
        const status = node('div'), timing = node('div');
        status.append(node('span',labels[p.status] || p.status,`tag ${p.status === 'selesai' ? 'teal-tag' : p.peringatan.length ? 'yellow-tag' : ''}`),node('small',p.presensi));
        if (p.cara_selesai) status.append(node('small',p.cara_selesai));
        for (const reason of p.peringatan) status.append(node('small',reason,'exam-detail-attention'));
        if (['mengerjakan','terblokir'].includes(p.status) && p.batas_waktu) {
            const countdown = node('b'); countdown.dataset.examDeadline = p.batas_waktu; timing.append(countdown,node('small',`Batas ${time(p.batas_waktu)}`));
        } else timing.append(node('span','-'));
        if (p.tambahan_waktu) timing.append(node('small','Tambahan waktu'));
        const tr = node('tr'); tr.dataset.id = String(p.id); tr.dataset.signature = JSON.stringify([p,rooms.get(p.ruang_id)]);
        const room = node('div'); room.append(node('strong',rooms.get(p.ruang_id) || 'Belum ditempatkan'),node('small',`Meja ${p.meja ?? '-'} \u00b7 ${p.sesi}`));
        tr.append(cell('Peserta',person(p)),cell('Ruang / meja',room),cell('Status / presensi',status),cell('Waktu',timing),cell('Komunikasi',node('time',time(p.komunikasi_terakhir))));
        return tr;
    }
    function activity(p) {
        const tr = node('tr'); tr.dataset.id = p.id; tr.dataset.signature = JSON.stringify(p);
        tr.append(cell('Peserta',person(p)),cell('Ruang',node('span',p.ruang)),cell('Aktivitas',node('span',p.label)),cell('Waktu',node('time',time(p.waktu))),cell('Sesi',node('span',p.sesi || '-'))); return tr;
    }
    function headings() {
        const titles = mode === 'aktivitas' ? ['Peserta','Ruang','Aktivitas','Waktu','Sesi'] : ['Peserta','Ruang / meja','Status / presensi','Waktu','Komunikasi'];
        const key = titles.join('|'); if ($('exam-detail-head').dataset.signature === key) return;
        const tr = node('tr'); tr.append(...titles.map(title => node('th',title))); $('exam-detail-head').replaceChildren(tr); $('exam-detail-head').dataset.signature = key;
        dialog.classList.toggle('activity-detail',mode === 'aktivitas');
    }
    function render() {
        if (!dialog.open) return;
        if (!data) {
            $('exam-detail-rows').replaceChildren(); $('exam-detail-count').textContent = 'Menunggu data jadwal';
            $('exam-detail-updated').textContent = ''; $('exam-detail-empty').hidden = true;
            $('exam-detail-prev').disabled = true; $('exam-detail-next').disabled = true;
            message(warning || 'Data ujian belum tersedia. Pembaruan otomatis setiap 5 detik.'); return;
        }
        options('exam-detail-room-filter',data.ruang,'Semua ruang');
        const classes = [...new Set(data.peserta.map(p => p.kelas))].sort((a,b) => a.localeCompare(b,'id',{numeric:true}));
        options('exam-detail-class',classes.map(nama => ({id:nama,nama})),'Semua kelas');
        const roomId = $('exam-detail-room-filter').value, kelas = $('exam-detail-class').value;
        const status = $('exam-detail-status').value, q = $('exam-detail-search').value.trim().toLocaleLowerCase('id');
        const room = data.ruang.find(r => String(r.id) === roomId), meta = $('exam-detail-room'); meta.hidden = !room;
        meta.textContent = room ? `${room.nama} \u00b7 Pengawas: ${room.pengawas} \u00b7 ${room.sesi}` : '';
        $('exam-detail-title').textContent = mode === 'aktivitas' ? 'Aktivitas terbaru ujian' : participantId !== null ? 'Rincian peserta' : room ? `Peserta ${room.nama}` : status ? `Peserta ${labels[status].toLocaleLowerCase('id')}` : 'Semua peserta ujian';
        $('exam-detail-subtitle').textContent = data.jadwal ? `${data.jadwal.nama} \u00b7 ${data.jadwal.tanggal} \u00b7 ${data.jadwal.jam}` : 'Tidak ada jadwal hari ini';
        const source = mode === 'aktivitas' ? data.aktivitas : data.peserta;
        const filtered = source.filter(p => (roomId === '' || String(p.ruang_id) === roomId) && (!kelas || p.kelas === kelas)
            && (!q || p.nama.toLocaleLowerCase('id').includes(q)) && (participantId === null || p.id === participantId)
            && (mode === 'aktivitas' || !status || (status === 'menunggu' ? ['belum_hadir','hadir_belum_mulai'].includes(p.status) : status === 'perlu_perhatian' ? p.peringatan.length > 0 : p.status === status)));
        lastPage = Math.max(1,Math.ceil(filtered.length / 20)); page = Math.min(page,lastPage);
        const start = (page - 1) * 20, visible = filtered.slice(start,start + 20), rooms = new Map(data.ruang.map(r => [r.id,r.nama]));
        const key = JSON.stringify([mode,visible,data.ruang.map(r => [r.id,r.nama])]);
        if (signature !== key) {
            const top = $('exam-detail-scroll').scrollTop, old = new Map([...$('exam-detail-rows').children].map(tr => [tr.dataset.id,tr]));
            $('exam-detail-rows').replaceChildren(...visible.map(p => {
                const previous = old.get(String(p.id));
                const rowSignature = mode === 'aktivitas' ? JSON.stringify(p) : JSON.stringify([p,rooms.get(p.ruang_id)]);
                return previous?.dataset.signature === rowSignature ? previous : mode === 'aktivitas' ? activity(p) : participant(p,rooms);
            })); $('exam-detail-scroll').scrollTop = top; signature = key;
        }
        headings();
        $('exam-detail-count').textContent = `${filtered.length ? start + 1 : 0}-${start + visible.length} dari ${fmt(filtered.length)} ${mode === 'aktivitas' ? 'aktivitas' : 'peserta'}`;
        $('exam-detail-updated').textContent = `Data ${time(data.diperbarui_pada)} \u00b7 5 detik`;
        $('exam-detail-page').textContent = `${page} / ${lastPage}`;
        $('exam-detail-prev').disabled = page <= 1; $('exam-detail-next').disabled = page >= lastPage;
        $('exam-detail-empty').hidden = visible.length > 0;
        $('exam-detail-empty').textContent = mode === 'aktivitas' ? 'Tidak ada aktivitas yang sesuai.' : 'Tidak ada peserta yang sesuai.';
        $('exam-detail-context').textContent = mode === 'aktivitas' ? 'Maksimal 20 aktivitas terbaru dari jadwal / sesi terpilih, bukan seluruh riwayat. Indikator bukan vonis pelanggaran.' : 'Monitoring baca-saja. Status mengikuti server ujian; indikator perhatian perlu diperiksa petugas.';
        message(warning); tick(serverTime + performance.now() - receivedTime);
    }
    function tick(now) {
        if (!dialog.open || !data) return;
        dialog.querySelectorAll('[data-exam-deadline]').forEach(el => {
            const seconds = Math.max(0,Math.ceil((Date.parse(el.dataset.examDeadline) - now) / 1000));
            el.textContent = seconds ? `${String(Math.floor(seconds / 60)).padStart(2,'0')}:${String(seconds % 60).padStart(2,'0')}` : 'Waktu habis';
            el.classList.toggle('warning-time',seconds < 300);
        });
    }
    function resetFilters() {
        for (const id of ['exam-detail-search','exam-detail-room-filter','exam-detail-class','exam-detail-status']) $(id).value = '';
        participantId = null; page = 1; signature = '';
    }
    function open(button) {
        if (blocked) return;
        const type = button.dataset.examDetail; mode = type === 'aktivitas' ? 'aktivitas' : 'peserta'; resetFilters();
        dialog.classList.remove('filters-open'); $('exam-detail-filters-toggle').setAttribute('aria-expanded','false');
        if (data) { options('exam-detail-room-filter',data.ruang,'Semua ruang'); $('exam-detail-room-filter').value = button.dataset.detailRoomId ?? ''; }
        $('exam-detail-status').value = labels[type] ? type : '';
        if (button.dataset.participantId) participantId = Number(button.dataset.participantId);
        $('exam-detail-status-label').hidden = mode === 'aktivitas'; $('exam-detail-search').value = button.dataset.detailQuery || '';
        $('exam-detail-title').textContent = mode === 'aktivitas' ? 'Aktivitas terbaru ujian' : 'Rincian peserta';
        $('exam-detail-room').hidden = true; $('exam-detail-subtitle').textContent = 'Jadwal terpilih';
        $('exam-detail-rows').replaceChildren(); headings(); opener = button;
        document.body.classList.add('detail-open'); dialog.showModal(); render();
    }
    document.addEventListener('click',event => { const button = event.target.closest('[data-exam-detail]'); if (button) open(button); });
    $('exam-detail-close').addEventListener('click',() => dialog.close());
    dialog.addEventListener('click',event => {
        if (event.target !== dialog) return;
        const r = dialog.getBoundingClientRect();
        if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close();
    });
    dialog.addEventListener('close',() => {
        $('exam-detail-rows').replaceChildren(); signature = ''; document.body.classList.remove('detail-open');
        // Polling can replace the opening room card while the modal is open.
        if (opener && !opener.isConnected && !blocked) {
            const replacement = [...document.querySelectorAll('[data-exam-detail]')].find(b => b.dataset.examDetail === opener.dataset.examDetail && b.dataset.detailRoomId === opener.dataset.detailRoomId && b.dataset.participantId === opener.dataset.participantId);
            replacement?.focus({preventScroll:true});
        }
    });
    $('exam-detail-search').addEventListener('input',() => { page = 1; render(); });
    for (const id of ['exam-detail-room-filter','exam-detail-class','exam-detail-status']) $(id).addEventListener('change',() => { participantId = null; page = 1; render(); });
    $('exam-detail-reset').addEventListener('click',() => { resetFilters(); render(); });
    $('exam-detail-filters-toggle').addEventListener('click',() => {
        const expanded = dialog.classList.toggle('filters-open'); $('exam-detail-filters-toggle').setAttribute('aria-expanded',String(expanded));
    });
    $('exam-detail-prev').addEventListener('click',() => { if (page > 1) { page--; render(); $('exam-detail-scroll').scrollTop = 0; } });
    $('exam-detail-next').addEventListener('click',() => { if (page < lastPage) { page++; render(); $('exam-detail-scroll').scrollTop = 0; } });
    window.NusaExamDetails = {
        isOpen: () => dialog.open, tick,
        update: next => {
            if (blocked) return;
            if (data && (data.jadwal?.id !== next.jadwal?.id || data.sesi_id !== next.sesi_id)) dialog.close();
            data = {jadwal:next.jadwal,sesi_id:next.sesi_id,ruang:next.ruang,aktivitas:next.aktivitas,zona_waktu:next.zona_waktu,diperbarui_pada:next.diperbarui_pada,
                peserta:next.peserta.map(p => {
                    const {id,nama,kelas,ruang_id,meja,sesi,status,presensi,peringatan,cara_selesai,batas_waktu,tambahan_waktu,komunikasi_terakhir} = p;
                    return {id,nama,kelas,ruang_id,meja,sesi,status,presensi,peringatan,cara_selesai,batas_waktu,tambahan_waktu,komunikasi_terakhir};
                })}; serverTime = Date.parse(next.diperbarui_pada); receivedTime = performance.now(); warning = ''; render();
        },
        unavailable: text => { warning = text; message(text); },
        reset: () => {
            dialog.close(); data = null; resetFilters(); warning = 'Menunggu data jadwal / sesi terpilih.';
            $('exam-detail-rows').replaceChildren(); $('exam-detail-room').textContent = ''; $('exam-detail-room').hidden = true;
            for (const [id,label] of [['exam-detail-room-filter','Semua ruang'],['exam-detail-class','Semua kelas']]) {
                $(id).replaceChildren(new Option(label,'')); delete $(id).dataset.options;
            }
        },
        revoke: () => {
            resetFilters();
            blocked = true; dialog.close(); data = null; warning = ''; opener = null; $('exam-detail-rows').replaceChildren();
            for (const id of ['exam-detail-subtitle','exam-detail-room','exam-detail-count','exam-detail-updated']) $(id).textContent = '';
            for (const id of ['exam-detail-room-filter','exam-detail-class']) $(id).replaceChildren();
            document.querySelectorAll('[data-exam-detail]').forEach(b => { b.disabled = true; }); message('');
        },
    };
})();
