import {readFile,mkdir,writeFile} from 'node:fs/promises';
import path from 'node:path';
import {pathToFileURL} from 'node:url';
import nodeAssert from 'node:assert/strict';

let checks=0;
const assert=new Proxy(nodeAssert,{get(target,key){return (...args)=>{checks++;return target[key](...args);};}});
const {chromium}=await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE).href);
const root=process.cwd(),folder=path.join(root,'storage/framework/testing/exam-tv'),out=path.join(root,'storage/logs/exam-tv-rincian');
await mkdir(out,{recursive:true});
const html=await readFile(path.join(folder,'index.html'),'utf8'),fixture=JSON.parse(await readFile(path.join(folder,'data.json'),'utf8'));
const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||undefined}),results=[];
function summarize(rows){
    const r={total:rows.length};
    for(const s of ['mengerjakan','selesai','belum_hadir','hadir_belum_mulai','tidak_hadir','terblokir','nonaktif'])r[s]=rows.filter(p=>p.status===s).length;
    r.perlu_perhatian=rows.filter(p=>p.peringatan.length).length;r.persen_selesai=rows.length?Math.round(100*r.selesai/rows.length):0;return r;
}
async function showFilters(page){
    const toggle=page.locator('#exam-detail-filters-toggle');
    if(await toggle.isVisible()&&await toggle.getAttribute('aria-expanded')==='false')await toggle.click();
}
try{
    for(const [width,height] of [[1920,1080],[1366,768],[1280,720],[1024,768],[390,844],[320,568]]){
        const before=checks,context=await browser.newContext({viewport:{width,height}}),page=await context.newPage();
        await page.clock.install({time:new Date(fixture.diperbarui_pada)});
        let payload=structuredClone(fixture),status=200,calls=0,inflight=0,peak=0;
        payload.peserta=payload.peserta.map((p,i)=>({...p,kelas:i>=24?'VIII.B':p.kelas}));
        payload.aktivitas=payload.aktivitas.map(e=>({...e,kelas:payload.peserta.find(p=>p.id===e.peserta_id)?.kelas || e.kelas}));
        const errors=[],methods=[];page.on('pageerror',e=>errors.push(e.message));
        await page.route('http://localhost/**',async route=>{
            const url=new URL(route.request().url());methods.push(route.request().method());
            if(url.pathname==='/monitoring-ujian-tv')return route.fulfill({contentType:'text/html',body:html});
            if(url.pathname==='/monitoring-ujian-tv/data'){
                calls++;inflight++;peak=Math.max(peak,inflight);payload.diperbarui_pada=await page.evaluate(()=>new Date().toISOString());
                await route.fulfill({status,contentType:'application/json',body:status===200?JSON.stringify(payload):'{}'});inflight--;return;
            }
            const file=path.resolve(root,'public','.'+url.pathname);
            if(!file.startsWith(path.join(root,'public')+path.sep))return route.abort();
            try{const types={'.js':'application/javascript','.css':'text/css','.png':'image/png','.woff2':'font/woff2'};return route.fulfill({body:await readFile(file),contentType:types[path.extname(file)]||'application/octet-stream'});}catch{return route.fulfill({status:404,body:''});}
        });
        await page.goto('http://localhost/monitoring-ujian-tv');
        await page.waitForFunction(()=>document.getElementById('connection').textContent==='Terhubung');
        const url=page.url(),initialCalls=calls;
        for(const kind of ['total','mengerjakan','selesai','menunggu','tidak_hadir','perlu_perhatian']){
            await page.locator(`.kpis [data-exam-detail="${kind}"]`).click();
            const expected=kind==='menunggu'?payload.ringkasan.belum_hadir+payload.ringkasan.hadir_belum_mulai:payload.ringkasan[kind];
            assert.equal(await page.locator('#exam-detail-dialog').evaluate(e=>e.open),true);
            assert.ok((await page.locator('#exam-detail-count').textContent()).endsWith(`dari ${expected} peserta`));
            assert.equal(await page.locator('#exam-detail-rows tr').count(),Math.min(20,expected));
            assert.ok(!(await page.locator('#exam-detail-head').textContent()).includes('Jawaban'));
            if(kind==='mengerjakan'){
                const timer=page.locator('[data-exam-deadline]').first(),label=await timer.textContent();
                await page.clock.fastForward(1000);assert.notEqual(await timer.textContent(),label);
            }
            await page.keyboard.press('Escape');assert.equal(await page.locator('#exam-detail-dialog').evaluate(e=>e.open),false);
        }
        assert.equal(calls,initialCalls,'Opening/filtering modals does not add requests');
        const roomId=payload.ruang[0].id;
        await page.locator(`.room-card[data-room-id="${roomId}"]`).click();
        assert.equal(await page.locator('#exam-detail-room-filter').inputValue(),String(roomId));
        assert.ok((await page.locator('#exam-detail-room').textContent()).includes('Pengawas Uji'));
        assert.equal(await page.locator('[data-mode="overview"]').getAttribute('aria-pressed'),'true');
        assert.equal(await page.locator('#participant-table').isVisible(),false);
        const background=await page.locator('#room-grid').textContent();
        await page.clock.fastForward(15000);
        assert.equal(await page.locator('#room-grid').textContent(),background,'Rotation is paused while the modal is open');
        await page.keyboard.press('Escape');
        await page.clock.runFor(50);
        const focusState=await page.evaluate(id=>({focused:document.querySelector(`.room-card[data-room-id="${id}"]`)===document.activeElement,active:document.activeElement?.outerHTML?.slice(0,700),open:document.getElementById('exam-detail-dialog').open}),roomId);
        assert.equal(focusState.focused,true,JSON.stringify(focusState));
        await page.locator('.activity-section .section-heading [data-exam-detail="aktivitas"]').click();
        assert.equal(await page.locator('#exam-detail-status-label').isVisible(),false);
        assert.ok((await page.locator('#exam-detail-context').textContent()).includes('20 aktivitas terbaru'));
        await showFilters(page);
        await page.locator('#exam-detail-room-filter').selectOption(String(roomId));
        assert.equal(await page.locator('#exam-detail-rows tr').count(),payload.aktivitas.filter(e=>e.ruang_id===roomId).length);
        await page.locator('#exam-detail-close').click();
        payload.peserta=payload.peserta.map(p=>({...p,status:'mengerjakan',peringatan:[],cara_selesai:null}));
        payload.ringkasan=summarize(payload.peserta);payload.perhatian=[];
        payload.ruang=payload.ruang.map(r=>({...r,ringkasan:summarize(payload.peserta.filter(p=>p.ruang_id===r.id))}));
        await page.clock.fastForward(5000);
        await page.waitForFunction(()=>document.getElementById('stat-mengerjakan').textContent==='48');
        await page.locator('.kpis [data-exam-detail="mengerjakan"]').click();
        assert.equal(await page.locator('#exam-detail-page').textContent(),'1 / 3');
        await page.screenshot({path:path.join(out,`${width}x${height}.png`)});
        const geometry=await page.locator('#exam-detail-dialog').evaluate(e=>{const r=e.getBoundingClientRect();return {left:r.left,top:r.top,right:r.right,bottom:r.bottom,sw:e.scrollWidth,cw:e.clientWidth};});
        assert.ok(geometry.left>=0&&geometry.top>=0&&geometry.right<=width&&geometry.bottom<=height);
        assert.ok(geometry.sw<=geometry.cw+1,'Modal has no horizontal overflow');
        await page.locator('#exam-detail-scroll').evaluate(e=>{e.scrollTop=200;});
        const scroll=await page.locator('#exam-detail-scroll').evaluate(e=>e.scrollTop);
        await page.locator('#exam-detail-search').focus();
        const beforePoll=calls,beforeUpdate=await page.locator('#exam-detail-updated').textContent();await page.clock.fastForward(5000);
        await page.waitForFunction(before=>document.getElementById('exam-detail-updated').textContent!==before,beforeUpdate);
        assert.ok(calls>beforePoll);
        assert.equal(await page.locator('#exam-detail-dialog').evaluate(e=>e.open),true);
        assert.equal(await page.locator('#exam-detail-scroll').evaluate(e=>e.scrollTop),scroll);
        assert.equal(await page.locator('#exam-detail-search').evaluate(e=>e===document.activeElement),true);
        await page.locator('#exam-detail-next').click();assert.equal(await page.locator('#exam-detail-page').textContent(),'2 / 3');
        await page.clock.fastForward(5000);assert.equal(await page.locator('#exam-detail-page').textContent(),'2 / 3');
        await page.locator('#exam-detail-search').fill('Siswa Uji Monitoring 1');
        assert.equal(await page.locator('#exam-detail-rows tr').count(),11);
        await page.locator('#exam-detail-search').fill('TIDAK ADA DATA');assert.equal(await page.locator('#exam-detail-empty').isVisible(),true);
        await page.locator('#exam-detail-reset').click();assert.equal(await page.locator('#exam-detail-rows tr').count(),20);
        await showFilters(page);
        await page.locator('#exam-detail-class').selectOption('VIII.B');
        assert.ok((await page.locator('#exam-detail-count').textContent()).endsWith('24 peserta'));
        assert.equal(await page.locator('#exam-detail-page').textContent(),'1 / 2');
        await page.locator('#exam-detail-reset').click();
        await page.locator('#exam-detail-status').selectOption('mengerjakan');
        payload.peserta[0].status='selesai';payload.ringkasan=summarize(payload.peserta);
        await page.clock.fastForward(5000);
        await page.waitForFunction(()=>document.getElementById('exam-detail-count').textContent.endsWith('47 peserta'));
        assert.equal(await page.locator('#exam-detail-status').inputValue(),'mengerjakan');
        status=500;await page.clock.fastForward(5000);
        await page.waitForFunction(()=>!document.getElementById('exam-detail-message').hidden);
        assert.equal(await page.locator('#exam-detail-rows tr').count(),20);
        status=200;await page.clock.fastForward(5000);
        await page.waitForFunction(()=>document.getElementById('exam-detail-message').hidden);
        payload.peserta[1].nama='<img src=x onerror=alert(1)>';
        await page.clock.fastForward(5000);
        await page.locator('#exam-detail-search').fill('<img');
        assert.equal(await page.locator('#exam-detail-rows img').count(),0);
        assert.ok((await page.locator('#exam-detail-rows').textContent()).includes('<img src=x'));
        await page.locator('#exam-detail-close').click();
        await page.locator('[data-mode="detail"]').click();
        await page.locator('#participants [data-exam-detail="peserta"]').first().click();
        assert.equal(await page.locator('#exam-detail-rows tr').count(),1);
        assert.equal(await page.locator('#participant-table').isVisible(),true);
        await page.keyboard.press('Escape');
        await page.locator('.kpis [data-exam-detail="total"]').click();
        payload.jadwal.id+=1000;payload.pilihan_jadwal=[...payload.pilihan_jadwal,{id:payload.jadwal.id,label:'Jadwal berikutnya'}];
        await page.clock.fastForward(5000);
        await page.waitForFunction(()=>!document.getElementById('exam-detail-dialog').open);
        assert.equal(await page.locator('#exam-detail-rows tr').count(),0,'Schedule change clears the modal');
        await page.locator('.kpis [data-exam-detail="total"]').click();
        assert.equal(await page.locator('#exam-detail-rows tr').count(),20);
        payload.peserta[2].ruang_id=0;
        payload.ruang.push({id:0,nama:'Belum ditempatkan',kode:'-',pengawas:'-',sesi:'-',ringkasan:summarize([payload.peserta[2]])});
        await page.clock.fastForward(5000);
        await showFilters(page);
        await page.locator('#exam-detail-room-filter').selectOption('0');
        assert.equal(await page.locator('#exam-detail-rows tr').count(),1);
        assert.ok((await page.locator('#exam-detail-room').textContent()).includes('Belum ditempatkan'));
        payload.jadwal=null;payload.ruang=[];payload.peserta=[];payload.perhatian=[];payload.aktivitas=[];payload.ringkasan=summarize([]);payload.grafik_selesai=[];
        await page.clock.fastForward(5000);
        await page.waitForFunction(()=>document.getElementById('stat-total').textContent==='0');
        await page.locator('.kpis [data-exam-detail="total"]').click();
        assert.equal(await page.locator('#exam-detail-empty').isVisible(),true);
        assert.equal(await page.locator('#exam-detail-rows tr').count(),0);
        status=403;await page.clock.fastForward(5000);
        await page.waitForFunction(()=>document.getElementById('connection').textContent==='Akses tidak aktif');
        assert.equal(await page.locator('#exam-detail-dialog').evaluate(e=>e.open),false);
        assert.equal(await page.locator('#exam-detail-rows tr').count(),0);
        assert.equal(await page.locator('#exam-detail-class option').count(),0);
        assert.equal(await page.locator('#stat-total').textContent(),'-');
        const stopped=calls;await page.clock.fastForward(10000);assert.equal(calls,stopped);
        assert.equal(page.url(),url);assert.equal(peak,1);assert.ok(methods.every(m=>m==='GET'));assert.deepEqual(errors,[]);
        results.push({width,height,checks:checks-before,geometry,calls});await context.close();
    }
}finally{await browser.close();}
await writeFile(path.join(out,'results.json'),JSON.stringify(results,null,2));
console.log(JSON.stringify({checks,viewports:results.length,errors:0,results}));
