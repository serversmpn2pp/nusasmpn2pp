(() => {
    'use strict';
    const body = document.body;
    if (body.dataset.pameran !== 'true') return;
    const $ = id => document.getElementById(id);
    const fmt = n => Number(n || 0).toLocaleString('id-ID');
    let tab = 'siswa', blocked = false, saving = false, guestBusy = false, guestRequest = null, guestGeneration = 0;
    let guestData = null, dailyChart = null, ratingChart = null, chartType = 'bar', signature = '', interestPage = 0, recentPage = 0;
    let knownGuests = new Set(), newGuests = new Set();
    const date = $('pameran-date'), save = $('pameran-date-save'), rotate = $('pameran-rotate');
    try { rotate.checked = localStorage.getItem('nusa-pameran-rotasi') === 'true'; } catch (_) {}
    window.NusaPameran = {tab: () => tab};
    const set = (id, value) => { $(id).textContent = value; };
    function notice(text) { set('guest-notice', text || ''); $('guest-notice').hidden = !text; }
    function connected(text, offline = false) {
        set('connection', text); $('connection').className = 'connection' + (offline ? ' offline' : '');
    }
    function clearGuests(message) {
        guestData = null; signature = '';
        for (const id of ['guest-total','guest-today','guest-satisfied','guest-nusa']) set(id,'-');
        for (const id of ['guest-interests','guest-recent']) $(id).replaceChildren();
        for (const id of ['guest-average','guest-nusa-count','guest-recent-count']) set(id,'-');
        knownGuests.clear(); newGuests.clear(); window.NusaGuestRincian?.reset();
        dailyChart?.destroy(); ratingChart?.destroy(); dailyChart=null; ratingChart=null;
        notice(message);
    }
    function element(tag, text, className) {
        const el=document.createElement(tag); if(text!==undefined)el.textContent=text; if(className)el.className=className; return el;
    }
    function interests() {
        if(!guestData)return;
        const s=guestData.statistik, labels=Object.entries(s.label_minat), container=$('guest-interests');
        let size=Math.min(labels.length||1,innerHeight<900?3:6),pages;
        const row=([key,label])=>{
            const row=element('button',undefined,'pameran-interest'),title=element('div');
            row.type='button';row.dataset.guestDetail='minat';row.dataset.guestInterest=key;
            row.setAttribute('aria-haspopup','dialog');row.setAttribute('aria-controls','guest-dialog');
            row.title='Rincian minat '+label;
            title.append(element('span',label),element('strong',fmt(s.minat[key])));
            const progress=element('div',undefined,'progress'),bar=element('span');
            bar.style.width=Math.min(100,s.total?100*s.minat[key]/s.total:0)+'%';progress.append(bar);row.append(title,progress);return row;
        };
        do{
            pages=Math.max(1,Math.ceil(labels.length/size));interestPage%=pages;
            container.replaceChildren(...labels.slice(interestPage*size,interestPage*size+size).map(row));
            if(size===1||[...container.children].every(el=>el.getBoundingClientRect().bottom<=container.getBoundingClientRect().bottom+1))break;
            size--;
        }while(size>0);
        set('guest-interest-page','Hal. '+(interestPage+1)+'/'+pages);
    }
    function recent() {
        if(!guestData)return;
        const container=$('guest-recent'),items=guestData.terbaru||[],zone=guestData.zona_waktu;
        if(!items.length){container.replaceChildren(element('p','Belum ada kunjungan tercatat.','empty'));set('guest-recent-page','Pembaruan 3 detik');return;}
        let size=Math.min(items.length,innerHeight<900?3:5),pages;
        const render=item=>{
            const row=element('div',undefined,'pameran-visitor'+(newGuests.has(item.id)?' new':''));
            row.dataset.id=item.id;
            const initials=String(item.nama).trim().split(/\s+/).slice(0,2).map(word=>word[0]).join('').toUpperCase();
            const avatar=element('span',initials,'pameran-visitor-avatar');avatar.setAttribute('aria-hidden','true');
            const person=element('div');person.append(element('strong',item.nama),element('span',item.label_kategori));
            const time=element('time',new Date(item.waktu).toLocaleTimeString('en-GB',{timeZone:zone,hourCycle:'h23'}));
            time.dateTime=item.waktu;time.title=new Date(item.waktu).toLocaleDateString('id-ID',{timeZone:zone})+' '+time.textContent;
            row.append(avatar,person,time);return row;
        };
        do{
            pages=Math.max(1,Math.ceil(items.length/size));recentPage%=pages;
            container.replaceChildren(...items.slice(recentPage*size,recentPage*size+size).map(render));
            if(size===1||[...container.children].every(el=>el.getBoundingClientRect().bottom<=container.getBoundingClientRect().bottom+1))break;
            size--;
        }while(size>0);
        set('guest-recent-page',`Kunjungan terbaru · ${recentPage+1}/${pages} · pembaruan 3 detik`);
    }
    function chart(existing,id,type,labels,values,colors,horizontal=false) {
        if(!window.Chart)return existing;
        if(existing&&existing.config.type!==type){existing.destroy();existing=null;}
        const dataset={label:'Pengunjung',data:values,backgroundColor:colors,borderColor:typeof colors==='string'?colors:'#0f766e',borderWidth:type==='line'?3:0,pointRadius:type==='line'?3:0,tension:.2,maxBarThickness:35,borderRadius:3};
        if(existing){existing.data.labels=labels;existing.data.datasets=[dataset];existing.update('none');return existing;}
        return new Chart($(id),{type,data:{labels,datasets:[dataset]},options:{responsive:true,maintainAspectRatio:false,animation:false,indexAxis:horizontal?'y':'x',onClick:(_event,elements)=>{
            if(!elements.length||!guestData)return;
            const index=elements[0].index;
            if(id==='guest-daily-chart')window.NusaGuestRincian?.open('harian',{tanggal:Object.keys(guestData.statistik.harian)[index]});
            else window.NusaGuestRincian?.open('kepuasan',{nilai:[5,4,3,2,1][index]});
        },onHover:(event,elements)=>{event.native.target.style.cursor=elements.length?'pointer':'default';},plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,grid:{display:false},ticks:{precision:0,maxRotation:0,maxTicksLimit:8}},y:{beginAtZero:true,ticks:{precision:0},grid:{color:'#e6edf3'}}}}});
    }
    function renderGuests(next) {
        const incoming=next.terbaru||[],previous=guestData;
        newGuests=previous?new Set(incoming.filter(item=>!knownGuests.has(item.id)).map(item=>item.id)):new Set();
        if(newGuests.size)recentPage=0;
        knownGuests=new Set(incoming.map(item=>item.id));
        guestData=next;const s=next.statistik,zone=next.zona_waktu;
        set('pameran-event',next.acara.nama);
        set('guest-total',fmt(s.total));set('guest-today',fmt(next.pengunjung_hari_ini));
        set('guest-today-date',new Date(next.tanggal_hari_ini+'T00:00:00+07:00').toLocaleDateString('id-ID',{timeZone:zone,day:'numeric',month:'long',year:'numeric'}));
        set('guest-satisfied',s.persen_puas===null?'-':s.persen_puas.toLocaleString('id-ID')+'%');
        set('guest-average',s.rata_rata===null?'Belum ada penilaian':'Rata-rata '+s.rata_rata.toLocaleString('id-ID')+' / 5');
        set('guest-nusa',s.persen_puas_nusa===null?'-':s.persen_puas_nusa.toLocaleString('id-ID')+'%');
        set('guest-nusa-count',fmt(s.penilai_nusa)+' pengunjung menilai NUSA');
        set('guest-recent-count',fmt(s.total)+' kunjungan');
        const updated=new Date(next.diperbarui_pada);
        set('updated','Diperbarui '+updated.toLocaleDateString('id-ID',{timeZone:zone})+', '+updated.toLocaleTimeString('en-GB',{timeZone:zone,hourCycle:'h23'}));
        notice(s.total===0?'Belum ada pengunjung tercatat untuk acara ini.':null);
        window.NusaGuestRincian?.options(next);
        if(JSON.stringify(previous?.terbaru)!==JSON.stringify(incoming))recent();
        const nextSignature=JSON.stringify([s,chartType]);
        if(signature!==nextSignature){
            dailyChart=chart(dailyChart,'guest-daily-chart',chartType,Object.keys(s.harian).map(d=>new Date(d+'T00:00:00+07:00').toLocaleDateString('id-ID',{timeZone:zone,day:'numeric',month:'short'})),Object.values(s.harian),'#15477a');
            ratingChart=chart(ratingChart,'guest-rating-chart','bar',['Sangat puas','Puas','Netral','Kurang puas','Tidak puas'],[5,4,3,2,1].map(k=>s.kepuasan[k]),['#0f766e','#39988c','#d2af23','#c78169','#b35067'],true);
            interests();signature=nextSignature;
        }
    }
    async function guests() {
        if(tab!=='tamu'||guestBusy||blocked)return;
        guestBusy=true;const current=++guestGeneration,controller=new AbortController();guestRequest=controller;
        const timeout=setTimeout(()=>controller.abort(),4500);
        try{
            const response=await fetch(body.dataset.pameranTamuEndpoint,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
            if(current!==guestGeneration||blocked)return;
            if(response.status===401||response.status===403){window.dispatchEvent(new Event('monitor-access-revoked'));return;}
            if(response.status===410){clearGuests('Acara tidak lagi tersedia. Hubungi administrator untuk pengaturan acara.');connected('Acara tidak tersedia',true);return;}
            if(!response.ok)throw new Error('HTTP '+response.status);
            const data=await response.json();
            if(!data.statistik||!data.acara||!data.diperbarui_pada)throw new Error('Data tidak valid');
            if(current!==guestGeneration||blocked)return;
            renderGuests(data);connected('Terhubung');
            if(Date.now()-Date.parse(data.diperbarui_pada)>20000){notice('Data server tertunda; waktu pembaruan terakhir tertera di atas.');connected('Data tertunda',true);}
        }catch(_){if(current===guestGeneration&&!blocked){connected(navigator.onLine?'Koneksi terputus':'Offline',true);notice(guestData?'Pembaruan terhenti. Data terakhir tetap ditampilkan; mencoba kembali setiap 3 detik.':'Belum dapat memuat pengunjung; mencoba kembali setiap 3 detik.');}}
        finally{clearTimeout(timeout);if(current===guestGeneration){guestBusy=false;guestRequest=null;}}
    }
    function selectTab(next,focus=false) {
        if(blocked||!['siswa','tamu'].includes(next))return;
        tab=next;window.NusaMonitorRincian?.reset();window.NusaGuestRincian?.reset();
        for(const value of ['siswa','tamu']){
            const button=$('tab-'+value),active=value===tab;button.setAttribute('aria-selected',String(active));button.tabIndex=active?0:-1;$('panel-'+value).hidden=!active;
        }
        if(focus)$('tab-'+tab).focus();
        if(tab==='tamu'){window.NusaMonitoringTv?.pause();guests();requestAnimationFrame(()=>{dailyChart?.resize();ratingChart?.resize();interests();recent();});}
        else{guestGeneration++;guestRequest?.abort();guestRequest=null;guestBusy=false;window.NusaMonitoringTv?.refresh();}
    }
    for(const value of ['siswa','tamu']){
        $('tab-'+value).addEventListener('click',()=>selectTab(value));
        $('tab-'+value).addEventListener('keydown',event=>{if(['ArrowLeft','ArrowRight','Home','End'].includes(event.key)){event.preventDefault();selectTab(event.key==='Home'?'siswa':event.key==='End'?'tamu':tab==='siswa'?'tamu':'siswa',true);}});
    }
    date.addEventListener('input',()=>{date.dataset.dirty='true';});
    $('pameran-date-form').addEventListener('submit',async event=>{
        event.preventDefault();if(blocked||saving||!date.reportValidity())return;
        saving=true;date.disabled=true;save.disabled=true;window.NusaMonitoringTv?.pause();
        $('pameran-date-error').hidden=true;
        const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),8000);
        set('pameran-date-status','Menyimpan tanggal...');
        try{
            const response=await fetch(body.dataset.pameranTanggalEndpoint,{method:'PUT',credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json','Content-Type':'application/json','X-Monitor-CSRF':body.dataset.monitorCsrf},body:JSON.stringify({tanggal:date.value}),signal:controller.signal});
            if([401,403,419].includes(response.status)){window.dispatchEvent(new Event('monitor-access-revoked'));return;}
            const result=await response.json();
            if(!response.ok)throw new Error(Object.values(result.errors||{}).flat()[0]||result.message||'Tanggal belum dapat disimpan.');
            if(blocked)return;
            body.dataset.tanggal=result.tanggal_data;date.value=result.tanggal_data;delete date.dataset.dirty;
            window.NusaMonitoringTv?.clear();window.NusaMonitoringTv?.refresh();
        }catch(error){if(!blocked){set('pameran-date-error',error.name==='AbortError'?'Koneksi terputus. Periksa tanggal yang tampil sebelum mencoba lagi.':error.message);$('pameran-date-error').hidden=false;window.NusaMonitoringTv?.refresh();}}
        finally{clearTimeout(timeout);saving=false;date.disabled=blocked;save.disabled=blocked;}
    });
    document.querySelectorAll('[data-guest-chart]').forEach(button=>button.addEventListener('click',()=>{
        chartType=button.dataset.guestChart;document.querySelectorAll('[data-guest-chart]').forEach(el=>el.setAttribute('aria-pressed',String(el===button)));if(guestData)renderGuests(guestData);
    }));
    rotate.addEventListener('change',()=>{try{localStorage.setItem('nusa-pameran-rotasi',String(rotate.checked));}catch(_){}});
    setInterval(()=>{if(rotate.checked&&!blocked&&!saving&&!document.hidden&&!window.NusaMonitorRincian?.isOpen()&&!window.NusaGuestRincian?.isOpen()&&document.activeElement!==date)selectTab(tab==='siswa'?'tamu':'siswa');},30000);
    setInterval(()=>{if(tab==='tamu'&&!blocked){guests();window.NusaGuestRincian?.refresh();}},3000);
    setInterval(()=>{if(tab==='tamu'&&!blocked&&!window.NusaGuestRincian?.isOpen()){interestPage++;recentPage++;interests();recent();}},15000);
    window.addEventListener('online',guests);
    window.addEventListener('resize',()=>{if(tab==='tamu'){interests();recent();}});
    window.addEventListener('pameran-event-unavailable',()=>{clearGuests('Acara tidak lagi tersedia. Hubungi administrator untuk pengaturan acara.');connected('Acara tidak tersedia',true);});
    window.addEventListener('offline',()=>{if(tab==='tamu'){connected('Offline',true);notice('Koneksi terputus. Data terakhir tetap ditampilkan.');}});
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)guests();});
    window.addEventListener('monitor-access-revoked',()=>{
        if(blocked)return;blocked=true;guestGeneration++;guestRequest?.abort();guestRequest=null;clearGuests('Akses layar dicabut. Hubungi administrator.');
        date.disabled=true;save.disabled=true;rotate.disabled=true;for(const value of ['siswa','tamu'])$('tab-'+value).disabled=true;
    });
})();
