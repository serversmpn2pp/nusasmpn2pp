import assert from 'node:assert/strict';
import {readFile, mkdir, writeFile} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const fixtures = resolve('storage/framework/testing/role-theme');
const output = resolve('storage/logs/role-theme-qa');
const publicRoot = resolve('public');
const key = value => { const u = new URL(value, 'http://localhost'); u.searchParams.sort(); return u.pathname+u.search; };
const manifests = {...JSON.parse(await readFile(resolve(fixtures,'manifest.json'),'utf8')), ...JSON.parse(await readFile(resolve(fixtures,'manifest-scan.json'),'utf8'))};
const pages = Object.fromEntries(Object.entries(manifests).map(([p,e])=>[key(p),e]));
const browser = await chromium.launch({headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || undefined, args:['--use-fake-device-for-media-stream','--use-fake-ui-for-media-stream']});
const issues=[], errors=[], missing=[], writes=[], checks=[];
let scanMode='success';
await mkdir(output,{recursive:true});
try {
    const context=await browser.newContext({serviceWorkers:'block',reducedMotion:'reduce',permissions:['camera']});
    const page=await context.newPage();
    page.on('pageerror',e=>errors.push({path:new URL(page.url()).pathname,message:e.message}));
    await page.route('**/*',async route=>{
        const req=route.request(),u=new URL(req.url());
        if(!['localhost','127.0.0.1'].includes(u.hostname))return route.abort();
        if(req.method()!=='GET'){
            writes.push(u.pathname);
            assert.ok(/^\/scan-(absensi(?:-pegawai)?|kegiatan-ibadah|berhalangan-ibadah)$/.test(u.pathname),'Unexpected write '+u.pathname);
            if(scanMode==='offline')return route.abort('internetdisconnected');
            const person={nama_lengkap:'MUHAMMAD ABDURRAHMAN HARIANJA DENGAN NAMA LENGKAP PANJANG',nisn:'0130000001',nip:'199211032019021001',kelas:'VIII.A Bilingual',inisial:'MA'};
            return route.fulfill({json:{berhasil:scanMode==='success',baru:scanMode==='success',status:scanMode==='duplicate'?'sudah_scan_masuk':scanMode==='error'?'tidak_ditemukan':'berhasil',pesan:scanMode==='success'?'Presensi berhasil dicatat.':scanMode==='duplicate'?'Presensi sudah tercatat.':'Kartu tidak ditemukan.',jenis_scan:'masuk',siswa:person,pegawai:person,absensi:{jam_masuk:'08:05:00'},waktu_server:'08:05:00',jumlah_hari_ini:1}});
        }
        if(pages[key(u.href)])return route.fulfill({contentType:'text/html',body:await readFile(resolve(fixtures,pages[key(u.href)].file+'.html'),'utf8')});
        if(u.pathname==='/notifikasi/ringkasan')return route.fulfill({json:{jumlah_belum_dibaca:0,notifikasi:[]}});
        const p=resolve(publicRoot,'.'+decodeURIComponent(u.pathname));
        if(!p.startsWith(publicRoot+sep))return route.abort();
        try{return route.fulfill({body:await readFile(p),contentType:({'.css':'text/css','.js':'text/javascript','.png':'image/png','.jpg':'image/jpeg','.jpeg':'image/jpeg','.webp':'image/webp','.svg':'image/svg+xml','.woff2':'font/woff2','.webmanifest':'application/manifest+json'})[extname(p)]||'application/octet-stream'});}
        catch{missing.push(u.pathname);return route.fulfill({status:404,body:''});}
    });
    async function ready(path){
        await page.goto('http://localhost'+path);
        await page.evaluate(()=>document.fonts.ready);
        if(!path.startsWith('/scan-audit/'))await page.waitForFunction(()=>!!document.querySelector('[data-admin-sidebar-toggle] svg.lucide'));
    }
    const shots=new Set();
    for(const [width,height] of [[320,740],[390,844],[768,1024],[1024,768],[1440,960],[1920,1080]]){
        await page.setViewportSize({width,height});
        for(const [path,entry] of Object.entries(pages)){
            await ready(path);
            const result=await page.evaluate(()=>{
                const root=document.querySelector('.content-shell')||document.body;
                const visible=e=>!!e.getClientRects().length&&getComputedStyle(e).visibility!=='hidden';
                const clipped=[...root.querySelectorAll('.page-title,.panel-title,.student-name,.parent-name,.dashboard-title,.result-name,.clock,.brand-title,.button,.back-link,.schedule-time,.schedule-name')].filter(visible).filter(e=>e.clientWidth>0&&e.scrollWidth>e.clientWidth+2).map(e=>({text:e.textContent.trim().slice(0,90),class:e.className,width:e.clientWidth,scroll:e.scrollWidth}));
                return{pageWidth:document.documentElement.scrollWidth,rootWidth:root.clientWidth,rootScroll:root.scrollWidth,clipped,smallControls:[...root.querySelectorAll('.input,.select,.scan-capture,.schedule-select,.manual-form input')].filter(visible).filter(e=>parseFloat(getComputedStyle(e).fontSize)<16).map(e=>e.id),images:[...document.querySelectorAll('img')].filter(visible).filter(e=>!e.complete||!e.naturalWidth).map(e=>e.src)};
            });
            if(result.pageWidth>width+1||result.rootScroll>result.rootWidth+2||result.clipped.length||result.smallControls.length||result.images.length)issues.push({path,width,...result});
            const shotKey=entry.role+'-'+width;
            if([390,1440].includes(width)&&(!shots.has(shotKey)||path.startsWith('/scan-audit/')||path.includes('komponen_nilai_id'))){shots.add(shotKey);await page.screenshot({path:resolve(output,entry.file+'-'+width+'.png')});}
            checks.push({path,width,role:entry.role});
        }
    }
    await writeFile(resolve(output,'audit.json'),JSON.stringify({checks,issues,errors,missing,writes},null,2));
    // Same sidebar controls for each role, including hidden mode and mobile focus trap.
    for(const role of ['guru','tu','pimpinan','siswa','orang_tua']){
        const path=Object.keys(pages).find(p=>p===`/role-audit/${role}/beranda`);
        await page.setViewportSize({width:1440,height:960});await ready(path);
        const section=page.locator('#admin-sidebar details[data-sidebar-section]').first();
        if(!await section.evaluate(e=>e.open))await section.locator(':scope > summary').click();
        assert.equal(await section.locator('.sidebar-section-content').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(242, 246, 250)',`Submenu background for ${role}`);
        assert.equal(await section.locator(':scope > summary').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(234, 241, 247)',`Expanded category heading for ${role}`);
        await section.scrollIntoViewIfNeeded();
        await page.screenshot({path:resolve(output,`submenu-${role}-1440.png`)});
        await page.locator('[data-admin-sidebar-toggle]').click();
        assert.ok(await page.locator('body').evaluate(e=>e.classList.contains('admin-sidebar-hidden')));
        await page.locator('[data-admin-sidebar-toggle]').click();
        assert.ok(!await page.locator('#admin-sidebar').evaluate(e=>e.inert));
        await page.setViewportSize({width:390,height:844});
        await page.locator('[data-admin-sidebar-toggle]').click();
        assert.ok(await page.locator('.app-content').evaluate(e=>e.inert));
        assert.ok(await page.locator('#admin-sidebar').evaluate(e=>e.contains(document.activeElement)));
        assert.ok(await section.locator('.sidebar-section-content').isVisible(),`Expanded submenu on mobile for ${role}`);
        assert.equal(await section.locator('.sidebar-section-content').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(242, 246, 250)');
        await section.scrollIntoViewIfNeeded();
        await page.screenshot({path:resolve(output,`submenu-${role}-390.png`)});
        await page.keyboard.press('Escape');
        assert.ok(!await page.locator('.app-content').evaluate(e=>e.inert));
    }
    for(const kind of ['siswa','pegawai']){
        await page.setViewportSize({width:1440,height:960});await ready(`/scan-audit/${kind}/aktif`);
        assert.ok(await page.locator('#scanInput').evaluate(e=>e===document.activeElement));
        for(const [index,mode] of ['success','duplicate','error','offline'].entries()){
            scanMode=mode;
            await page.locator('#scanInput').fill('013000000'+index);await page.locator('#scanInput').press('Enter');
            const state=mode==='success'?'is-success':mode==='duplicate'?'is-recorded':'is-error';
            await page.waitForFunction(state=>document.querySelector('#resultCard').classList.contains(state),state);
            assert.equal(await page.locator('#scanInput').inputValue(),'');
            assert.ok(await page.locator('#scanInput').evaluate(e=>e===document.activeElement));
            await page.screenshot({path:resolve(output,`scan-${kind}-${mode}.png`)});
            await page.waitForTimeout(700);
        }
    }
    for(const kind of ['ibadah','privat']){
        await page.setViewportSize({width:390,height:844});await ready(`/scan-audit/${kind}/aktif`);
        assert.ok(await page.locator('#start-camera').isEnabled());
        await page.evaluate(()=>{window.originalCamera=navigator.mediaDevices.getUserMedia.bind(navigator.mediaDevices);navigator.mediaDevices.getUserMedia=()=>Promise.reject(new DOMException('Denied','NotAllowedError'));});
        await page.locator('#start-camera').click();
        await page.waitForFunction(()=>document.querySelector('#camera-warning').textContent.includes('Izin kamera ditolak'));
        assert.ok(await page.locator('#start-camera').isEnabled());
        await page.evaluate(()=>{
            const canvas=document.createElement('canvas');canvas.width=320;canvas.height=240;
            const paint=canvas.getContext('2d');
            const drawFrame=()=>{paint.fillStyle='#0f766e';paint.fillRect(0,0,320,240);paint.fillStyle='#fff';paint.fillRect(90,50,140,140);paint.fillStyle='#15477a';paint.fillRect(120,80,80,80);};
            window.cameraSourceCanvas=canvas;
            // Canvas streams need new frames after capture starts, like a live camera.
            navigator.mediaDevices.getUserMedia=async constraints=>{window.cameraConstraints=constraints;window.virtualCameraStream=canvas.captureStream(15);window.cameraFrameTimer=setInterval(drawFrame,66);drawFrame();return window.virtualCameraStream;};
            navigator.mediaDevices.enumerateDevices=async()=>[{kind:'videoinput',deviceId:'virtual-test-camera',label:'Kamera pengujian'}];
        });
        await page.locator('#start-camera').click();
        try{
            await page.waitForFunction(()=>document.querySelector('#camera-wrap').classList.contains('camera-on')||!document.querySelector('#camera-warning').hidden);
        }catch(error){
            const camera=await page.locator('#camera-video').evaluate(e=>({readyState:e.readyState,paused:e.paused,width:e.videoWidth,height:e.videoHeight,stream:e.srcObject?.active,tracks:e.srcObject?.getTracks().map(t=>({state:t.readyState,enabled:t.enabled,muted:t.muted})),warning:document.querySelector('#camera-warning').textContent}));
            await writeFile(resolve(output,`camera-${kind}-failure.json`),JSON.stringify(camera,null,2));
            await page.screenshot({path:resolve(output,`camera-${kind}-failure.png`)});
            throw error;
        }
        assert.ok(await page.locator('#camera-wrap').evaluate(e=>e.classList.contains('camera-on')),await page.locator('#camera-warning').textContent());
        assert.ok(await page.locator('#stop-camera').isEnabled());
        const video=await page.locator('#camera-video').evaluate(e=>({width:e.videoWidth,height:e.videoHeight,state:e.readyState}));
        assert.ok(video.width>0&&video.height>0&&video.state>=2,'Camera preview blank');
        assert.equal(await page.evaluate(()=>window.cameraConstraints.video.facingMode.ideal),'environment');
        const pixels=await page.locator('#camera-video').evaluate(video=>{const c=document.createElement('canvas');c.width=320;c.height=240;const ctx=c.getContext('2d');ctx.drawImage(video,0,0,320,240);const p=ctx.getImageData(0,0,320,240).data;return new Set(Array.from({length:100},(_,i)=>{const x=16+(i%10)*32,y=12+Math.floor(i/10)*24,offset=(y*320+x)*4;return p[offset]+','+p[offset+1]+','+p[offset+2];})).size;});
        assert.ok(pixels>1,'Camera frame must contain rendered pixels');
        await page.screenshot({path:resolve(output,`camera-${kind}.png`)});
        await page.locator('#stop-camera').click();
        assert.ok(await page.locator('#stop-camera').isDisabled());
        assert.ok(!await page.locator('#camera-wrap').evaluate(e=>e.classList.contains('camera-on')));
        assert.ok(await page.evaluate(()=>window.virtualCameraStream.getTracks().every(track=>track.readyState==='ended')));
        await page.evaluate(()=>clearInterval(window.cameraFrameTimer));
        scanMode='success';
        await page.locator('.manual > summary').click();
        await page.locator('#manual-nisn').fill('0130000001');
        await page.locator('#manual-form button').click();
        await page.waitForFunction(()=>document.querySelector('#scan-result').classList.contains('success'));
        assert.equal(await page.locator('#manual-nisn').inputValue(),'');
        if(kind==='privat')assert.equal(await page.locator('#recent-list').count(),0);
        await ready(`/scan-audit/${kind}/tutup`);
        assert.ok(await page.locator('#start-camera').isDisabled());
        assert.ok(await page.locator('#manual-nisn').isDisabled());
    }
    await writeFile(resolve(output,'audit.json'),JSON.stringify({checks,issues,errors,missing,writes},null,2));
    console.log(JSON.stringify({pages:Object.keys(pages).length,states:checks.length,issues:issues.length,errors,missing,simulatedScans:writes.length,output}));
    assert.deepEqual(issues,[],'Layout issues in audit.json');assert.deepEqual(errors,[]);assert.deepEqual(missing,[]);
}finally{await browser.close();}
