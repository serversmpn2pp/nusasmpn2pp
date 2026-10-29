import {readFile,mkdir,writeFile} from 'node:fs/promises';
import path from 'node:path';
import {pathToFileURL} from 'node:url';
import nodeAssert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE).href);
let checks=0;const assert=new Proxy(nodeAssert,{get(target,key){return (...args)=>{checks++;return target[key](...args);};}});
const root=process.cwd(),folder=path.join(root,'storage/framework/testing/monitor-pameran'),out=path.join(root,'storage/logs/monitor-pameran');
await mkdir(out,{recursive:true});
const html=await readFile(path.join(folder,'index.html'),'utf8');
const original=JSON.parse(await readFile(path.join(folder,'data.json'),'utf8'));
const earlier=JSON.parse(await readFile(path.join(folder,'data-29.json'),'utf8'));
const details=JSON.parse(await readFile(path.join(folder,'rincian.json'),'utf8'));
const earlierDetails=JSON.parse(await readFile(path.join(folder,'rincian-29.json'),'utf8'));
const guests=JSON.parse(await readFile(path.join(folder,'tamu.json'),'utf8'));
const guestDetails=JSON.parse(await readFile(path.join(folder,'tamu-rincian.json'),'utf8'));
const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||undefined});
const results=[];
try{
    for(const [width,height] of [[1920,1080],[1366,768],[1280,720],[1024,768],[390,844],[320,640]]){
        const context=await browser.newContext({viewport:{width,height}}),page=await context.newPage(),errors=[];
        page.on('pageerror',error=>errors.push(error.message));
        let selected='2026-10-30',status=200,now=Date.parse(original.diperbarui_pada),saves=0,bookCalls=0,calls=0,inflight=0,peak=0,currentGuests=structuredClone(guests),guestDetailCalls=0,lastGuestQuery='';
        await page.clock.install({time:new Date(now)});
        const advance=async ms=>{now+=ms;await page.clock.fastForward(ms);};
        await page.route('http://localhost/**',async route=>{
            const url=new URL(route.request().url());
            if(url.pathname==='/monitoring-pameran')return route.fulfill({contentType:'text/html',body:html});
            const stamp=value=>JSON.stringify({...structuredClone(value),diperbarui_pada:new Date(now).toISOString()});
            if(url.pathname==='/monitoring-pameran/tanggal'){
                saves++;assert.ok(route.request().headers()['x-monitor-csrf']);selected=route.request().postDataJSON().tanggal;
                return route.fulfill({contentType:'application/json',body:JSON.stringify({tanggal_data:selected})});
            }
            if(url.pathname==='/monitoring-pameran/data'){
                calls++;inflight++;peak=Math.max(peak,inflight);
                try{return await route.fulfill({status,contentType:'application/json',body:status===200?stamp(selected.endsWith('29')?earlier:original):'{}'});}finally{inflight--;}
            }
            if(url.pathname==='/monitoring-pameran/rincian')return route.fulfill({status,contentType:'application/json',body:status===200?stamp((selected.endsWith('29')?earlierDetails:details)[url.searchParams.get('jenis')]):'{}'});
            if(url.pathname==='/monitoring-pameran/pengunjung'){bookCalls++;return route.fulfill({status,contentType:'application/json',body:status===200?stamp(currentGuests):'{}'});}
            if(url.pathname==='/monitoring-pameran/pengunjung/rincian'){
                guestDetailCalls++;lastGuestQuery=url.search;
                const type=url.searchParams.get('jenis'),suffix=url.searchParams.get('tanggal')||url.searchParams.get('nilai')||url.searchParams.get('minat');
                const result=structuredClone(guestDetails[type+'-'+suffix]||guestDetails[type]);
                if(url.searchParams.get('kategori'))result.items=result.items.filter(item=>item.kategori===url.searchParams.get('kategori'));
                if(url.searchParams.get('q'))result.items=result.items.filter(item=>item.nama.includes(url.searchParams.get('q')));
                result.pagination.total=result.items.length;
                return route.fulfill({status,contentType:'application/json',body:status===200?stamp(result):'{}'});
            }
            const file=path.resolve(root,'public','.'+url.pathname);if(!file.startsWith(path.join(root,'public')+path.sep))return route.abort();
            try{const types={'.js':'application/javascript','.css':'text/css','.png':'image/png','.webp':'image/webp','.woff2':'font/woff2'};return route.fulfill({body:await readFile(file),contentType:types[path.extname(file)]||'application/octet-stream'});}catch{return route.fulfill({status:404,body:''});}
        });
        await page.goto('http://localhost/monitoring-pameran');
        await page.waitForFunction(()=>document.getElementById('kpi-hadir').textContent==='2');
        assert.equal(await page.locator('#tab-siswa').getAttribute('aria-selected'),'true');
        assert.equal(await page.locator('.tv-heading h1').textContent(),'Monitoring TV');
        if(width>=1280)assert.ok(await page.locator('.tv-heading').evaluate(e=>Math.abs(e.getBoundingClientRect().x+e.getBoundingClientRect().width/2-innerWidth/2)<1),'Header is not centered');
        assert.equal(await page.locator('#pameran-date').inputValue(),'2026-10-30');
        assert.ok((await page.locator('#tanggal').textContent()).includes('30 Oktober'));
        assert.ok(!(await page.locator('body').textContent()).includes('Nama Rahasia'));
        assert.ok((await page.locator('#updated').textContent()).includes('31/10/2026'));
        assert.match(await page.locator('#updated').textContent(),/\b\d{2}:\d{2}:\d{2}\b/);
        assert.doesNotMatch(await page.locator('#updated').textContent(),/\b\d{2}\.\d{2}\.\d{2}\b/);
        const geometry=await page.evaluate(()=>({sw:document.documentElement.scrollWidth,sh:document.documentElement.scrollHeight}));
        assert.ok(geometry.sw<=width+1,`Student horizontal overflow ${width}`);
        if(width>=1280)assert.ok(geometry.sh<=height+1,`Student vertical overflow ${width}: ${geometry.sh}`);
        await page.screenshot({path:path.join(out,`siswa-${width}.png`),fullPage:true});
        await page.locator('[data-detail="hadir"]').first().click();
        await page.waitForFunction(()=>document.getElementById('detail-rows').children.length===2);
        assert.ok((await page.locator('#detail-subtitle').textContent()).includes('30 Oktober'));
        assert.match(await page.locator('#detail-updated').textContent(),/\b\d{2}:\d{2}:\d{2}\b/);
        await page.keyboard.press('Escape');
        const before=calls;await advance(3000);await page.waitForFunction(()=>document.getElementById('connection').textContent==='Terhubung');assert.ok(calls>before);
        await page.locator('#pameran-date').fill('2026-10-29');await page.locator('#pameran-date-save').click();
        await page.waitForFunction(()=>document.getElementById('kpi-hadir').textContent==='0'&&document.body.dataset.tanggal==='2026-10-29');
        assert.equal(saves,1);assert.equal(await page.locator('#point-balance').textContent(),'15');
        await page.reload();await page.waitForFunction(()=>document.getElementById('pameran-date').value==='2026-10-29');
        assert.equal(await page.locator('#tab-siswa').getAttribute('aria-selected'),'true');
        await page.locator('#tab-tamu').click();await page.waitForFunction(()=>document.getElementById('guest-total').textContent==='12');
        assert.ok(await page.locator('#panel-siswa').isHidden());
        assert.equal(await page.locator('#pameran-date').inputValue(),'2026-10-29');
        assert.equal(await page.locator('#guest-today').textContent(),'4');
        assert.equal(await page.locator('.pameran-guest-heading').count(),0);
        assert.ok((await page.locator('#guest-recent').textContent()).includes('Pengunjung sintetis'));
        assert.ok(await page.locator('#guest-recent time').evaluateAll(items=>items.every(item=>/^\d{2}:\d{2}:\d{2}$/.test(item.textContent))));
        assert.match(await page.locator('#guest-recent time').first().getAttribute('title'),/\b\d{2}:\d{2}:\d{2}\b/);
        assert.match(await page.locator('#updated').textContent(),/\b\d{2}:\d{2}:\d{2}\b/);
        for(const id of ['guest-daily-chart','guest-rating-chart'])assert.ok(await page.locator('#'+id).evaluate(c=>{const d=c.getContext('2d').getImageData(0,0,c.width,c.height).data;let n=0;for(let i=3;i<d.length;i+=4)if(d[i])n++;return n>100;}),`Blank guest chart ${id}`);
        await page.locator('[data-guest-chart="line"]').click();assert.equal(await page.locator('#guest-daily-chart').evaluate(c=>Chart.getChart(c).config.type),'line');
        const overlap=await page.evaluate(()=>['guest-interests','guest-recent'].flatMap(id=>{const box=document.getElementById(id).getBoundingClientRect();return [...document.getElementById(id).children].filter(e=>e.getBoundingClientRect().bottom>box.bottom+1).map(e=>id+': '+e.textContent);}));
        assert.deepEqual(overlap,[],`Guest content overlaps caption at ${width}`);
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Guest horizontal overflow ${width}`);
        if(width>=1280){const g=await page.evaluate(()=>({sh:document.documentElement.scrollHeight,h:innerHeight,boxes:[...document.querySelectorAll('.tv-header,.pameran-nav,#panel-tamu,#panel-tamu .pameran-guest-heading,#panel-tamu .pameran-metrics,#panel-tamu .pameran-guest-grid,#panel-tamu .chart-section,#panel-tamu .lower-section')].map(e=>({name:e.className,height:e.getBoundingClientRect().height,bottom:e.getBoundingClientRect().bottom}))}));assert.ok(g.sh<=height+1,`Guest vertical overflow ${width}: ${JSON.stringify(g)}`);}
        await page.screenshot({path:path.join(out,`tamu-${width}.png`),fullPage:true});
        for(const type of ['total','hari_ini','puas','nusa','harian','kepuasan','minat','terbaru']){
            await page.locator(`[data-guest-detail="${type}"]`).first().click();
            await page.waitForFunction(()=>document.getElementById('guest-detail-count').textContent.includes('pengunjung'));
            assert.ok(await page.locator('#guest-dialog').isVisible());
            assert.ok((await page.locator('#guest-detail-updated').textContent()).includes('3 detik'));
            assert.match(await page.locator('#guest-detail-updated').textContent(),/^\d{2}:\d{2}:\d{2}/);
            assert.equal(new URLSearchParams(lastGuestQuery).get('jenis'),type);
            const first=guestDetails[type].items[0];
            if(['puas','kepuasan','nusa'].includes(type)){
                assert.deepEqual(await page.locator('#guest-detail-columns th').allTextContents(),['Pengunjung','Kategori',type==='nusa'?'Nilai NUSA':'Nilai stand','Predikat','Waktu (WIB)']);
                assert.equal(await page.locator('#guest-detail-rows tr').first().locator('td').nth(2).textContent(),`${type==='nusa'?first.nilai_nusa:first.kepuasan} / 5`);
                assert.equal(await page.locator('#guest-detail-rows tr').first().locator('td').nth(3).textContent(),type==='nusa'?first.label_nusa:first.label_kepuasan);
                assert.ok(await page.locator('.guest-visit-time time').evaluateAll(items=>items.every(item=>/^\d{2}:\d{2}:\d{2}$/.test(item.textContent))));
            }else if(type==='minat'){
                assert.deepEqual(await page.locator('#guest-detail-columns th').allTextContents(),['Pengunjung','Kategori','Pilihan minat','Waktu (WIB)']);
                assert.deepEqual(await page.locator('#guest-detail-rows tr').first().locator('.guest-interest-tags>span').allTextContents(),first.label_minat);
            }else{
                assert.deepEqual(await page.locator('#guest-detail-columns th').allTextContents(),['Pengunjung','Kategori','Tanggal','Waktu (WIB)']);
                assert.equal(await page.locator('#guest-detail-rows .guest-rating-label').count(),0);
            }
            assert.ok(await page.locator('#guest-dialog').evaluate(el=>el.scrollWidth<=el.clientWidth+1),`Modal horizontal overflow ${type} at ${width}`);
            if(['kepuasan','nusa','minat'].includes(type))await page.screenshot({path:path.join(out,`modal-${type}-${width}.png`)});
            await page.keyboard.press('Escape');
        }
        for(const [canvas,index,filter,expected] of [['guest-daily-chart',1,'tanggal','2026-10-30'],['guest-rating-chart',0,'nilai','5']]){
            const point=await page.locator('#'+canvas).evaluate((c,index)=>{const chart=Chart.getChart(c),point=chart.getDatasetMeta(0).data[index];return {x:point.x-(chart.options.indexAxis==='y'?5:0),y:point.y};},index);
            await page.locator('#'+canvas).click({position:point});
            await advance(40);await page.waitForFunction(()=>document.getElementById('guest-dialog').open);
            await page.waitForFunction(()=>document.getElementById('guest-detail-count').textContent.includes('pengunjung'));
            assert.equal(new URLSearchParams(lastGuestQuery).get(filter),expected);
            await page.keyboard.press('Escape');
        }
        const interest=page.locator('[data-guest-interest]').first(),interestCode=await interest.getAttribute('data-guest-interest');
        await interest.click();await page.waitForFunction(()=>document.getElementById('guest-detail-count').textContent.includes('pengunjung'));
        assert.equal(new URLSearchParams(lastGuestQuery).get('minat'),interestCode);
        await page.locator('#guest-detail-interest').selectOption('nusa');
        await page.waitForFunction(()=>document.getElementById('guest-detail-context').textContent==='Minat: NUSA');
        await page.keyboard.press('Escape');
        await page.locator('[data-guest-detail="total"]').click();
        await page.waitForFunction(()=>document.getElementById('guest-detail-count').textContent.includes('pengunjung'));
        await page.locator('#guest-detail-category').selectOption('instansi');
        await page.waitForFunction(()=>document.getElementById('guest-detail-rows').textContent.includes('Instansi'));
        assert.ok(await page.locator('#guest-detail-rows tr').evaluateAll(rows=>rows.every(row=>row.children[1].textContent==='Instansi')));
        await page.locator('#guest-detail-search').fill('Tidak Ada');await advance(300);
        await page.waitForFunction(()=>!document.getElementById('guest-detail-empty').hidden);
        assert.equal(await page.locator('#guest-detail-rows tr').count(),0);
        await page.locator('#guest-detail-reset').click();
        await page.waitForFunction(()=>document.getElementById('guest-detail-rows').children.length===12);
        await page.locator('#pameran-rotate').evaluate(el=>{el.checked=true;el.dispatchEvent(new Event('change'));});
        const detailPrior=guestDetailCalls;await advance(30000);
        assert.equal(await page.locator('#tab-tamu').getAttribute('aria-selected'),'true');
        assert.ok(guestDetailCalls>detailPrior);
        await page.screenshot({path:path.join(out,`modal-${width}.png`)});
        await page.keyboard.press('Escape');
        await page.locator('#pameran-rotate').uncheck();
        const prior=bookCalls;await advance(3000);await page.waitForFunction(()=>document.getElementById('connection').textContent==='Terhubung');assert.ok(bookCalls>prior);
        const newName='Tamu Baru <img src=x onerror=alert(1)>';
        currentGuests.terbaru.unshift({id:9999,nama:newName,kategori:'instansi',label_kategori:'Instansi',waktu:new Date(now).toISOString()});
        await advance(3000);await page.waitForFunction(()=>document.getElementById('guest-recent').textContent.includes('Tamu Baru'));
        assert.ok((await page.locator('#guest-recent').textContent()).includes(newName));
        assert.equal(await page.locator('#guest-recent img').count(),0);
        currentGuests=structuredClone(guests);await advance(3000);
        await page.waitForFunction(()=>!document.getElementById('guest-recent').textContent.includes('Tamu Baru'));
        await context.setOffline(true);await advance(3000);assert.equal(await page.locator('#guest-total').textContent(),'12');
        await context.setOffline(false);await advance(3000);await page.waitForFunction(()=>document.getElementById('connection').textContent==='Terhubung');
        await page.locator('#pameran-rotate').check();await advance(30000);await page.waitForFunction(()=>document.getElementById('tab-siswa').getAttribute('aria-selected')==='true');
        await page.locator('#pameran-rotate').uncheck();await page.locator('#tab-tamu').click();
        await page.locator('[data-guest-detail="total"]').click();await page.waitForFunction(()=>document.getElementById('guest-detail-rows').children.length>0);
        status=401;await advance(3000);await page.waitForFunction(()=>document.getElementById('guest-total').textContent==='-');
        assert.ok(await page.locator('#guest-dialog').isHidden());assert.equal(await page.locator('#guest-detail-rows tr').count(),0);
        assert.equal(await page.locator('#kpi-hadir').textContent(),'-');assert.ok(await page.locator('#pameran-date').isDisabled());
        assert.ok(await page.locator('#tab-siswa').isDisabled());assert.equal(peak,1);assert.deepEqual(errors,[]);
        results.push({width,height,errors});await context.close();
    }
}finally{await browser.close();}
await writeFile(path.join(out,'results.json'),JSON.stringify({checks,viewports:results.length,results},null,2));
console.log(JSON.stringify({checks,viewports:results.length,errors:results.flatMap(r=>r.errors)}));
