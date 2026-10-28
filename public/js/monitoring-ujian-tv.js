(() => {
    'use strict';
    const REFRESH_MS = 5000;
    const $ = id => document.getElementById(id), fmt = n => Number(n || 0).toLocaleString('id-ID');
    const statusLabels = {belum_hadir:'Belum hadir',hadir_belum_mulai:'Hadir, belum mulai',tidak_hadir:'Tidak hadir',mengerjakan:'Mengerjakan',selesai:'Selesai',terblokir:'Terblokir',nonaktif:'Nonaktif'};
    let snapshot = null, mode = 'overview', roomId = null, roomPage = 0, chartPage = 0, participantPage = 0, attentionPage = 0, activityPage = 0;
    let busy = false, blocked = false, lastSuccess = 0, serverTime = 0, receivedTime = 0, graphSignature = '';
    let roomChart, finishChart, newEvents = new Set(), knownEvents = new Set();
    let knownAttention = new Set(), attentionPriority = new Set();
    const node = (tag, text, cls) => { const e=document.createElement(tag); if(text !== undefined)e.textContent=text; if(cls)e.className=cls; return e; };
    const text = (id, value) => { $(id).textContent = value; };
    const empty = (id, message) => $(id).replaceChildren(node('p',message,'empty'));
    const clock = () => serverTime + performance.now() - receivedTime;
    const time = value => value ? new Date(value).toLocaleTimeString('id-ID',{timeZone:snapshot?.zona_waktu || 'Asia/Jakarta',hour12:false}) : '-';
    const filters = () => new URLSearchParams([...($('jadwal').value ? [['jadwal_id',$('jadwal').value]] : []),...($('sesi').value ? [['sesi_id',$('sesi').value]] : [])]).toString();
    const participantLink = row => {
        const button=node('button',row.nama,'exam-name-button');button.type='button';button.dataset.examDetail='peserta';button.dataset.participantId=row.id;
        button.title=`Rincian peserta ${row.nama}`;button.setAttribute('aria-haspopup','dialog');button.setAttribute('aria-controls','exam-detail-dialog');return button;
    };
    document.querySelectorAll('[data-exam-detail]').forEach(e=>{e.setAttribute('aria-haspopup','dialog');e.setAttribute('aria-controls','exam-detail-dialog');});
    window.lucide?.createIcons();
    if(window.Chart){Chart.defaults.font.family='"Instrument Sans","Segoe UI",Arial,sans-serif';Chart.defaults.font.size=innerWidth>=1600?13:10;Chart.defaults.color='#526c80';}
    function connection(message, state) { text('connection',message);$('connection').className=`connection ${state}`; }
    function notice(message, danger=false){$('notice').hidden=!message; text('notice',message || '');$('notice').className=`notice${danger?' danger':''}`;requestAnimationFrame(()=>{if(snapshot)renderWorkspace();});}
    function options(id, rows, firstLabel) {
        const select=$(id), value=select.value, signature=JSON.stringify(rows);
        if(select.dataset.options===signature)return;
        select.replaceChildren(node('option',firstLabel));select.firstChild.value='';
        for(const row of rows){const option=node('option',row.label);option.value=String(row.id);select.append(option);}
        select.value=value;select.dataset.options=signature;
    }
    function pageItems(id, rows, preferred, page, render, grid=false) {
        const container=$(id);
        const active=document.activeElement;
        const focusKey=container.contains(active)&&active.dataset.examDetail?[active.dataset.examDetail,active.dataset.detailRoomId,active.dataset.participantId,active.dataset.detailQuery]:null;
        let size=Math.min(preferred,rows.length), pages=1;
        if(!size)return {page:0,pages:1,size:0};
        do {
            pages=Math.ceil(rows.length/size);page%=pages;
            const next=rows.slice(page*size,page*size+size).map(render),existing=[...container.children];
            if(next.length!==existing.length||next.some((e,i)=>e.outerHTML!==existing[i]?.outerHTML))container.replaceChildren(...next);
            if(focusKey){const replacement=[...container.querySelectorAll('[data-exam-detail]')].find(e=>[e.dataset.examDetail,e.dataset.detailRoomId,e.dataset.participantId,e.dataset.detailQuery].every((value,i)=>value===focusKey[i]));replacement?.focus({preventScroll:true});}
            if(grid){const cols=innerHeight<900 || innerWidth<=1100?2:3;container.style.gridTemplateRows=`repeat(${Math.ceil(size/cols)},minmax(0,1fr))`;}
            const limit=id==='participants'?$('participant-table').getBoundingClientRect().bottom:container.getBoundingClientRect().bottom;
            const fits=[...container.children].every(e=>e.getBoundingClientRect().bottom<=limit+1 && (!grid || e.scrollHeight<=e.clientHeight+2));
            if(fits || size===1 || innerWidth<=1100)break;
            size--;
        } while(size>0);
        return {page,pages,size};
    }
    function roomCard(room) {
        const r=room.ringkasan, card=node('button',undefined,`room-card${r.perlu_perhatian?' attention':''}`);card.type='button';card.dataset.roomId=room.id;
        card.dataset.examDetail='ruang';card.dataset.detailRoomId=room.id;card.title=`Rincian ${room.nama}`;card.setAttribute('aria-haspopup','dialog');card.setAttribute('aria-controls','exam-detail-dialog');
        const heading=node('div',undefined,'room-card-header');heading.append(node('strong',room.nama),node('span',`${r.selesai}/${r.total}`,'tag teal-tag'));
        const numbers=node('div',undefined,'room-numbers');
        for(const [label,value] of [['Mengerjakan',r.mengerjakan],['Selesai',r.selesai],['Belum mulai',r.belum_hadir+r.hadir_belum_mulai]]){const n=node('span');n.append(node('b',fmt(value)),node('span',label));numbers.append(n);}
        const progress=node('div',undefined,'room-progress'),bar=node('span');bar.style.width=`${r.persen_selesai}%`;progress.append(bar);
        card.append(heading,node('p',`${room.sesi} · ${room.pengawas}`,'room-supervisor'),numbers,progress,node('p',`${r.persen_selesai}% selesai · ${r.perlu_perhatian} perlu perhatian · ${r.tidak_hadir} tidak hadir`,'room-other'));
        return card;
    }
    function participant(row) {
        const tr=node('tr'), name=node('td'), status=node('td'), answer=node('td'), remaining=node('td'), communication=node('td');
        const nameHeading=node('strong');nameHeading.append(participantLink(row));name.append(nameHeading,node('small',row.kelas));
        status.append(node('span',statusLabels[row.status] || row.status,`tag ${row.status==='selesai'?'teal-tag':row.peringatan.length?'yellow-tag':''}`),node('small',row.presensi));
        if(row.cara_selesai) status.append(node('small',row.cara_selesai));
        answer.append(node('strong',`${row.jawaban}/${row.jumlah_soal}`));
        const progress=node('div',undefined,'mini-progress'),bar=node('span');bar.style.width=`${row.persen}%`;progress.append(bar);answer.append(progress);
        if(['mengerjakan','terblokir'].includes(row.status) && row.batas_waktu){remaining.dataset.deadline=row.batas_waktu;}
        else remaining.textContent='-';
        if(row.tambahan_waktu)remaining.append(node('small','Tambahan waktu'));
        communication.append(node('span',time(row.komunikasi_terakhir)));
        if(row.peringatan.some(p=>p.includes('Komunikasi')))communication.append(node('small','Perlu diperiksa'));
        tr.append(node('td',row.meja ?? '-'),name,status,answer,remaining,communication);
        return tr;
    }
    function renderWorkspace() {
        if(!snapshot)return;
        const rooms=snapshot.ruang;
        if(!rooms.some(r=>r.id===roomId))roomId=rooms[0]?.id ?? null;
        $('workspace-detail').dataset.examDetail=mode==='detail'?'ruang':'total';
        if(mode==='detail'&&roomId!==null)$('workspace-detail').dataset.detailRoomId=String(roomId);else delete $('workspace-detail').dataset.detailRoomId;
        $('room-grid').hidden=mode!=='overview';$('participant-table').hidden=mode!=='detail';$('room-controls').hidden=mode!=='detail';$('room-meta').hidden=mode!=='detail';
        if(mode==='overview'){
            text('workspace-title','Situasi ruang ujian');
            if(!rooms.length){empty('room-grid','Belum ada ruang pada jadwal ini.');text('room-page','0 ruang');return;}
            const p=pageItems('room-grid',rooms,innerHeight<900?4:6,roomPage,roomCard,true);roomPage=p.page;
            text('room-page',`${rooms.length} ruang${p.pages>1?` · ${p.page+1}/${p.pages}`:''}`);
            text('workspace-caption','Selesai mengerjakan bukan finalisasi hasil.');
        }else{
            options('room',rooms.map(r=>({id:r.id,label:r.nama})),'Pilih ruang');$('room').value=roomId===null?'':String(roomId);
            const room=rooms.find(r=>r.id===roomId);
            text('workspace-title',room?.nama || 'Detail ruang');text('room-meta',room?`${room.pengawas} · ${room.sesi}`:'Belum ada ruang.');
            const rows=snapshot.peserta.filter(p=>p.ruang_id===roomId);
            if(!rows.length){$('participants').replaceChildren();text('room-page','0 peserta');text('workspace-caption','Belum ada peserta pada ruang ini.');return;}
            const p=pageItems('participants',rows,innerHeight<900?6:10,participantPage,participant);participantPage=p.page;
            text('room-page',`${rows.length} peserta · ${p.page+1}/${p.pages}`);
            text('workspace-caption',`Progres jawaban tersimpan di server · ${room.ringkasan.selesai} selesai · ${room.ringkasan.perlu_perhatian} perlu perhatian`);
            updateTimers();
        }
    }
    function renderSide() {
        const roomNames=new Map(snapshot.ruang.map(r=>[r.id,r.nama]));
        const attention=[...snapshot.perhatian].sort((a,b)=>Number(attentionPriority.has(b.id))-Number(attentionPriority.has(a.id)) || Number(b.status==='terblokir')-Number(a.status==='terblokir'));
        if(!attention.length)empty('attention-list','Tidak ada indikator yang memerlukan pemeriksaan.');
        else {const p=pageItems('attention-list',attention,innerHeight<900?1:2,attentionPage,row=>{const e=node('article',undefined,'event-row'),name=node('strong');name.append(participantLink(row));e.append(name,node('p',`${roomNames.get(row.ruang_id) || '-'} · ${row.kelas}`),node('small',row.peringatan.join(' · ')));return e;});attentionPage=p.page;}
        if(!snapshot.aktivitas.length)empty('activity-list','Belum ada aktivitas tercatat pada jadwal ini.');
        else {const p=pageItems('activity-list',snapshot.aktivitas,innerHeight<900?2:3,activityPage,row=>{const e=node('article',undefined,'event-row');if(newEvents.has(row.id))e.classList.add('new');const name=node('strong'),button=node('button',row.nama,'exam-name-button');button.type='button';button.dataset.examDetail='aktivitas';button.dataset.detailQuery=row.nama;button.title='Rincian aktivitas peserta';button.setAttribute('aria-haspopup','dialog');button.setAttribute('aria-controls','exam-detail-dialog');name.append(button);e.append(name);const meta=node('p');meta.append(node('time',time(row.waktu)),document.createTextNode(row.ruang));e.append(meta,node('small',row.label));return e;});activityPage=p.page;text('activity-page',`Aktivitas server${p.pages>1?` · ${p.page+1}/${p.pages}`:''}`);}
    }
    function graph(existing,id,type,labels,datasets,stacked=false){
        if(!window.Chart)return existing;
        if(existing){existing.data={labels,datasets};existing.update('none');return existing;}
        return new Chart($(id),{type,data:{labels,datasets},options:{responsive:true,maintainAspectRatio:false,animation:false,
            plugins:{legend:{display:stacked,position:'bottom',labels:{boxWidth:8,boxHeight:8,padding:7,font:{size:innerHeight<900?9:11}}}},
            scales:{x:{stacked,grid:{display:false},ticks:{maxRotation:0,maxTicksLimit:6}},y:{stacked,beginAtZero:true,ticks:{precision:0,maxTicksLimit:3},grid:{color:'#e5edf2'},border:{display:false}}},elements:{point:{radius:0},line:{borderWidth:2,tension:0},bar:{borderRadius:2}}}});
    }
    function renderCharts(){
        const size=6,pages=Math.max(1,Math.ceil(snapshot.ruang.length/size)),page=chartPage%pages,start=page*size,rooms=snapshot.ruang.slice(start,start+size);
        text('chart-room-page',`${snapshot.ruang.length} ruang${pages>1?` · ${page+1}/${pages}`:''}`);
        roomChart=graph(roomChart,'room-chart','bar',rooms.map(r=>r.kode),[
            {label:'Mengerjakan',data:rooms.map(r=>r.ringkasan.mengerjakan),backgroundColor:'#15477a'},
            {label:'Selesai',data:rooms.map(r=>r.ringkasan.selesai),backgroundColor:'#0f766e'},
            {label:'Belum mulai',data:rooms.map(r=>r.ringkasan.belum_hadir+r.ringkasan.hadir_belum_mulai),backgroundColor:'#d7b22d'}
        ],true);
        finishChart=graph(finishChart,'finish-chart','line',snapshot.grafik_selesai.map(p=>p.jam),[{label:'Peserta selesai',data:snapshot.grafik_selesai.map(p=>p.selesai),borderColor:'#0f766e',backgroundColor:'#0f766e'}]);
    }
    function updateTimers(){
        if(!snapshot)return;
        window.NusaExamDetails?.tick(clock());
        text('server-clock',time(clock()));
        document.querySelectorAll('[data-deadline]').forEach(e=>{
            const seconds=Math.max(0,Math.ceil((Date.parse(e.dataset.deadline)-clock())/1000));
            let label=e.querySelector('b');if(!label){label=node('b');e.prepend(label);}
            label.textContent=seconds?`${String(Math.floor(seconds/60)).padStart(2,'0')}:${String(seconds%60).padStart(2,'0')}`:'Waktu habis';label.className=seconds<300?'warning-time':'';
        });
    }
    function render(next){
        const changed=snapshot?.jadwal?.id!==next.jadwal?.id || snapshot?.sesi_id!==next.sesi_id;
        if(changed){roomPage=0;chartPage=0;participantPage=0;activityPage=0;attentionPage=0;roomId=null;knownEvents=new Set();knownAttention=new Set();attentionPriority=new Set();}
        snapshot=next;serverTime=Date.parse(next.diperbarui_pada);receivedTime=performance.now();
        window.NusaExamDetails?.update(next);
        newEvents=new Set(next.aktivitas.map(e=>e.id).filter(id=>!knownEvents.has(id)));knownEvents=new Set(next.aktivitas.map(e=>e.id));if(newEvents.size)activityPage=0;
        const attentionKey=p=>`${p.id}|${p.peringatan.join('|')}`;
        const incoming=next.perhatian.filter(p=>!knownAttention.has(attentionKey(p)));
        if(incoming.length){attentionPage=0;attentionPriority=new Set(incoming.map(p=>p.id));}
        else attentionPriority=new Set([...attentionPriority].filter(id=>next.perhatian.some(p=>p.id===id)));
        knownAttention=new Set(next.perhatian.map(attentionKey));
        text('exam-name',next.kegiatan);text('exam-schedule',next.jadwal?`${next.jadwal.nama} · Kelas ${next.jadwal.tingkat} · ${next.jadwal.tanggal} · ${next.jadwal.jam}`:'Tidak ada jadwal hari ini');
        options('jadwal',next.pilihan_jadwal,'Otomatis: jadwal hari ini');options('sesi',next.pilihan_sesi,'Semua sesi paket ini');
        const r=next.ringkasan;
        for(const key of ['total','mengerjakan','selesai','tidak_hadir','perlu_perhatian'])text(`stat-${key}`,fmt(r[key]));
        text('stat-menunggu',fmt(r.belum_hadir+r.hadir_belum_mulai));
        text('hint-menunggu',`${r.hadir_belum_mulai} hadir · ${r.belum_hadir} belum absen`);
        text('hint-selesai',`${r.persen_selesai}% dari peserta terdaftar`);
        text('hint-perlu_perhatian',`${r.terblokir} terblokir · perlu diperiksa`);
        text('footer-count',`${next.ruang.length} ruang · ${r.nonaktif} peserta nonaktif`);text('footer-name','Ruang guru · Ujian terpusat');text('attention-count',fmt(r.perlu_perhatian));
        text('updated',`Data ${time(next.diperbarui_pada)} · 5 detik`);
        notice(next.pesan);renderWorkspace();renderSide();updateTimers();
        const signature=JSON.stringify([next.ruang,next.grafik_selesai]);if(signature!==graphSignature){renderCharts();graphSignature=signature;}
        if(!window.Chart)notice('Grafik belum dapat dimuat. Ringkasan tetap tersedia.',true);
    }
    function clearPrivateData(){
        window.NusaExamDetails?.revoke();
        snapshot=null;roomChart?.destroy();finishChart?.destroy();
        for(const id of ['room-grid','attention-list','activity-list'])empty(id,'Monitoring tidak tersedia.');$('participants').replaceChildren();
        for(const key of ['total','mengerjakan','selesai','menunggu','tidak_hadir','perlu_perhatian'])text(`stat-${key}`,'-');
        for(const id of ['exam-name','exam-schedule','room-meta','updated','workspace-title','footer-name','footer-count','room-page','attention-count'])text(id,'-');
        for(const id of ['jadwal','sesi','room']){$(id).replaceChildren();$(id).disabled=true;}
        document.querySelectorAll('[data-mode],#auto-room').forEach(e=>e.disabled=true);
    }
    async function refresh(){
        if(busy||blocked)return;busy=true;const requested=filters(),controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),4500);
        try{
            const response=await fetch(`${document.body.dataset.endpoint}${requested?'?'+requested:''}`,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store',signal:controller.signal});
            if([401,403,404].includes(response.status)){blocked=true;clearPrivateData();connection('Akses tidak aktif','offline');notice('Akses TV ujian tidak aktif atau kegiatan tidak tersedia. Hubungi administrator, lalu muat ulang halaman.',true);return;}
            if(response.status===422){window.NusaExamDetails?.reset();$('jadwal').value='';$('sesi').value='';notice('Pilihan jadwal berubah. Mengembalikan ke jadwal otomatis.',true);return;}
            if(!response.ok)throw new Error('Gagal memperbarui');
            const next=await response.json();if(requested!==filters())return;
            if(!next.ringkasan || !Array.isArray(next.peserta))throw new Error('Data tidak valid');
            render(next);lastSuccess=Date.now();connection('Terhubung','');
        }catch(_){window.NusaExamDetails?.unavailable('Pembaruan terputus. Data terakhir tetap ditampilkan; mencoba lagi setiap 5 detik.');connection(navigator.onLine?'Pembaruan terputus':'Offline','offline');notice(snapshot?'Data terakhir tetap ditampilkan. Pembaruan dicoba kembali setiap 5 detik.':'Belum dapat memuat data. Mencoba kembali setiap 5 detik.',true);renderSideIfAvailable();}
        finally{clearTimeout(timeout);busy=false;if(!blocked&&requested!==filters())refresh();}
    }
    function renderSideIfAvailable(){if(snapshot)renderSide();}
    function setMode(value){mode=value;document.querySelectorAll('[data-mode]').forEach(e=>e.setAttribute('aria-pressed',String(e.dataset.mode===mode)));if(snapshot)renderWorkspace();}
    $('jadwal').addEventListener('change',()=>{window.NusaExamDetails?.reset();$('sesi').value='';refresh();});$('sesi').addEventListener('change',()=>{window.NusaExamDetails?.reset();refresh();});
    $('room').addEventListener('change',()=>{roomId=Number($('room').value);participantPage=0;$('auto-room').checked=false;if(snapshot)renderWorkspace();});
    document.querySelectorAll('[data-mode]').forEach(e=>e.addEventListener('click',()=>setMode(e.dataset.mode)));
    $('fullscreen').addEventListener('click',async()=>{try{if(document.fullscreenElement)await document.exitFullscreen();else await document.documentElement.requestFullscreen();}catch(_){$('fullscreen').title='Gunakan F11 untuk layar penuh';}});
    document.addEventListener('fullscreenchange',()=>{$('fullscreen').title=document.fullscreenElement?'Keluar layar penuh':'Layar penuh';$('fullscreen').setAttribute('aria-label',$('fullscreen').title);});
    window.addEventListener('resize',()=>{if(snapshot){renderWorkspace();renderSide();}});
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});window.addEventListener('online',refresh);
    window.addEventListener('offline',()=>{window.NusaExamDetails?.unavailable('Koneksi terputus. Data terakhir masih ditampilkan.');connection('Offline','offline');notice('Koneksi terputus. Data terakhir masih ditampilkan.',true);});
    setInterval(()=>{if(lastSuccess&&Date.now()-lastSuccess>20000&&!blocked)connection('Data belum diperbarui','offline');refresh();},REFRESH_MS);
    setInterval(updateTimers,1000);
    setInterval(()=>{
        if(!snapshot||blocked||window.NusaExamDetails?.isOpen())return;attentionPage++;activityPage++;chartPage++;
        if(mode==='overview')roomPage++;
        else{
            const before=participantPage;participantPage++;renderWorkspace();
            if(participantPage<=before&&$('auto-room').checked&&snapshot.ruang.length){const index=snapshot.ruang.findIndex(r=>r.id===roomId);roomId=snapshot.ruang[(index+1)%snapshot.ruang.length].id;participantPage=0;}
        }
        renderWorkspace();renderSide();renderCharts();
    },15000);
    refresh();
})();
