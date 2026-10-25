import {readFile,mkdir,writeFile} from 'node:fs/promises';
import path from 'node:path';
import {pathToFileURL} from 'node:url';
import nodeAssert from 'node:assert/strict';

let checks=0;
const assert=new Proxy(nodeAssert,{get(target,key){return (...args)=>{checks++;return target[key](...args);};}});
const {chromium}=await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE).href);
const root=process.cwd(),folder=path.join(root,'storage/framework/testing/exam-tv'),out=path.join(root,'storage/logs/exam-tv');
await mkdir(out,{recursive:true});
const html=await readFile(path.join(folder,'index.html'),'utf8'),fixture=JSON.parse(await readFile(path.join(folder,'data.json'),'utf8'));
const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||undefined});
const results=[];
async function contained(page,ids){return page.evaluate(ids=>ids.flatMap(id=>{const box=document.getElementById(id).getBoundingClientRect();return [...document.getElementById(id).children].filter(e=>e.getBoundingClientRect().bottom>box.bottom+2).map(e=>`${id}: ${e.textContent}`);}),ids);}
try{
    for(const [width,height] of [[1920,1080],[1366,768],[1280,720],[1024,768],[390,844],[320,568]]){
        const before=checks,context=await browser.newContext({viewport:{width,height}}),page=await context.newPage();
        await page.clock.install({time:new Date(fixture.diperbarui_pada)});
        let payload=structuredClone(fixture),status=200,calls=0,inflight=0,peak=0;
        const errors=[],methods=[],queries=[];
        page.on('pageerror',e=>errors.push(e.message));
        await page.route('http://localhost/**',async route=>{
            const url=new URL(route.request().url());methods.push(route.request().method());
            if(url.pathname==='/monitoring-ujian-tv')return route.fulfill({contentType:'text/html',body:html});
            if(url.pathname==='/monitoring-ujian-tv/data'){
                calls++;queries.push(url.search);inflight++;peak=Math.max(peak,inflight);
                await route.fulfill({status,contentType:'application/json',body:status===200?JSON.stringify(payload):'{}'});inflight--;return;
            }
            const file=path.resolve(root,'public','.'+url.pathname);
            if(!file.startsWith(path.join(root,'public')+path.sep))return route.abort();
            try{const types={'.js':'application/javascript','.css':'text/css','.png':'image/png','.woff2':'font/woff2'};return route.fulfill({body:await readFile(file),contentType:types[path.extname(file)]||'application/octet-stream'});}catch{return route.fulfill({status:404,body:''});}
        });
        await page.goto('http://localhost/monitoring-ujian-tv');
        await page.waitForFunction(()=>document.getElementById('connection').textContent==='Terhubung');
        assert.equal(await page.locator('.app-sidebar').count(),0);
        assert.equal(await page.locator('#stat-total').textContent(),'48');
        assert.ok((await page.locator('#exam-name').textContent()).includes('Sumatif Tengah Semester'));
        const geometry=await page.evaluate(()=>({sw:document.documentElement.scrollWidth,sh:document.documentElement.scrollHeight,w:innerWidth,h:innerHeight}));
        await page.screenshot({path:path.join(out,`${width}x${height}-overview.png`),fullPage:true});
        assert.ok(geometry.sw<=width+1,`Horizontal overflow ${JSON.stringify(geometry)}`);
        if(width>=1280){assert.ok(geometry.sh<=height+1,`TV height overflow ${JSON.stringify(geometry)}`);assert.deepEqual(await contained(page,['room-grid','attention-list','activity-list']),[],`Panel overlap ${width}`);}
        for(const id of ['room-chart','finish-chart'])assert.ok(await page.locator('#'+id).evaluate(c=>{const d=c.getContext('2d').getImageData(0,0,c.width,c.height).data;let n=0;for(let i=3;i<d.length;i+=4)if(d[i])n++;return n>100;}),`Blank ${id}`);
        await page.locator('[data-mode="detail"]').click();
        assert.ok(await page.locator('#participant-table').isVisible());
        assert.ok((await page.locator('#participants').textContent()).includes('Siswa Uji Monitoring'));
        const detailGeometry=await page.evaluate(()=>({sw:document.documentElement.scrollWidth,sh:document.documentElement.scrollHeight}));
        if(width>=1280){assert.ok(detailGeometry.sh<=height+1,`Detail height overflow ${width}`);assert.ok(await page.evaluate(()=>document.getElementById('participants').getBoundingClientRect().bottom<=document.getElementById('participant-table').getBoundingClientRect().bottom+2),'Detail rows fit');}
        await page.screenshot({path:path.join(out,`${width}x${height}-detail.png`),fullPage:true});
        const initialRows=await page.locator('#participants').textContent();
        await page.clock.fastForward(15000);
        await page.waitForFunction(before=>document.getElementById('participants').textContent!==before,initialRows);
        assert.ok((await page.locator('#participants').textContent())!==initialRows,'Automatically rotate participant pages/rooms');
        const timer=page.locator('[data-deadline] b').first();
        if(await timer.count()){
            const beforeTimer=await timer.textContent();await page.clock.fastForward(1000);
            assert.notEqual(await timer.textContent(),beforeTimer,'Countdown moves without reloading');
        }
        const selectedRoom=await page.locator('#room').inputValue();
        await page.locator('#auto-room').uncheck();
        await page.clock.fastForward(30000);
        assert.equal(await page.locator('#room').inputValue(),selectedRoom,'Manual room selection retained');
        await page.locator('#room').selectOption(String(payload.ruang[0].id));
        const first=payload.peserta.find(p=>p.ruang_id===payload.ruang[0].id);first.nama='<img src=x onerror=alert(1)>';
        await page.clock.fastForward(5000);
        await page.waitForFunction(()=>document.getElementById('participants').textContent.includes('<img src=x'));
        assert.equal(await page.locator('#participants img').count(),0,'Student names are text, not HTML');
        const beforePoll=calls;payload.ringkasan.selesai=25;
        await page.clock.fastForward(5000);await page.waitForFunction(()=>document.getElementById('stat-selesai').textContent==='25');
        assert.ok(calls>beforePoll,'Poll every five seconds');assert.equal(peak,1,'Single flight requests');
        await page.locator('#jadwal').selectOption(String(payload.jadwal.id));
        await page.waitForFunction(()=>document.getElementById('connection').textContent==='Terhubung');
        await page.clock.fastForward(5000);
        assert.ok(queries.some(q=>q.includes('jadwal_id=')),'Schedule filter sent');
        status=500;await page.clock.fastForward(5000);
        await page.waitForFunction(()=>document.getElementById('connection').classList.contains('offline'));
        assert.equal(await page.locator('#stat-selesai').textContent(),'25','Retain last snapshot offline');
        assert.ok((await page.locator('#notice').textContent()).includes('terakhir'));
        status=200;await page.clock.fastForward(5000);await page.waitForFunction(()=>document.getElementById('connection').textContent==='Terhubung');
        payload.aktivitas.unshift({id:'new-activity',nama:'AKTIVITAS BARU UJI',ruang:'Ruang 1',label:'Mulai mengerjakan',waktu:payload.diperbarui_pada});
        payload.perhatian.unshift({...payload.peserta[0],nama:'PERHATIAN BARU UJI',peringatan:['Komunikasi belum diperbarui > 2 menit']});
        await page.clock.fastForward(5000);
        try { await page.waitForFunction(()=>document.getElementById('activity-list').textContent.includes('AKTIVITAS BARU UJI'),null,{timeout:3000}); }
        catch(error){await writeFile(path.join(out,'debug.json'),JSON.stringify({width,height,errors,calls,queries,state:await page.evaluate(()=>({connection:document.getElementById('connection').textContent,notice:document.getElementById('notice').textContent,activity:document.getElementById('activity-list').textContent,attention:document.getElementById('attention-list').textContent}))},null,2));throw error;}
        assert.ok((await page.locator('#attention-list').textContent()).includes('PERHATIAN BARU UJI'),'New attention appears immediately, not at the end of pagination');
        status=403;await page.clock.fastForward(5000);await page.waitForFunction(()=>document.getElementById('connection').textContent==='Akses tidak aktif');
        assert.ok(!(await page.locator('body').textContent()).includes('AKTIVITAS BARU UJI'));
        assert.ok(!(await page.locator('body').textContent()).includes('Siswa Uji Monitoring'));
        const stopped=calls;await page.clock.fastForward(10000);assert.equal(calls,stopped,'Revocation stops polling');
        assert.ok(methods.every(m=>m==='GET'),'Read-only endpoints');assert.deepEqual(errors,[]);
        results.push({width,height,checks:checks-before,geometry,calls});await context.close();
    }
}finally{await browser.close();}
await writeFile(path.join(out,'results.json'),JSON.stringify(results,null,2));
console.log(JSON.stringify({checks,viewports:results.length,errors:0,results}));
