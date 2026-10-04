// Fixtures: NUSA_CAPTURE_BUNDEL_UI=1 php artisan test --filter=BundelPertemuanHumasTest
import assert from 'node:assert/strict';
import {readFile,mkdir} from 'node:fs/promises';
import {resolve,sep,extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const pw=await import(process.env.PLAYWRIGHT_MODULE?pathToFileURL(process.env.PLAYWRIGHT_MODULE).href:'playwright');
const browser=await (pw.chromium||pw.default.chromium).launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||undefined});
const output=resolve('storage/logs/bundel-pertemuan-humas-audit');await mkdir(output,{recursive:true});
try {
    const context=await browser.newContext(),page=await context.newPage(),errors=[];
    let release, fail=false, payload='';
    page.on('pageerror',error=>errors.push(error.message));
    await context.route('**/*',async route=>{
        const url=new URL(route.request().url()),name=url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if(name)return route.fulfill({contentType:'text/html',body:await readFile(`storage/framework/testing/bundel-pertemuan-humas/${name}.html`,'utf8')});
        if(url.pathname.endsWith('/bundel/unduh')&&route.request().method()==='POST'){
            payload=route.request().postData()||'';
            if(fail)return route.fulfill({status:422,contentType:'application/json',body:JSON.stringify({errors:{bundel:['Lampiran tidak tersedia. Periksa berkas yang dipilih.']}})});
            await new Promise(resolve=>release=resolve);
            return route.fulfill({contentType:'application/zip',headers:{'Content-Disposition':'attachment; filename="bundel-pertemuan-uji.zip"'},body:Buffer.from('PK\x03\x04')});
        }
        if(url.pathname.endsWith('/bundel/cetak')&&route.request().method()==='POST')return route.fulfill({contentType:'text/html',body:await readFile('storage/framework/testing/bundel-pertemuan-humas/cetak.html','utf8')});
        if(/\/dokumen-humas\/\d+\/riwayat\/\d+\/unduh$/.test(url.pathname)||url.pathname.endsWith('assets/logo-sekolah.png'))return route.fulfill({contentType:'image/png',body:await readFile('public/images/kartu-pelajar/logo-smpn2pp.png')});
        if(url.pathname.endsWith('assets/logo-kota.png'))return route.fulfill({contentType:'image/png',body:await readFile('public/images/logo-padang-panjang.png')});
        const root=resolve('public'),path=resolve(root,'.'+decodeURIComponent(url.pathname));
        if(!path.startsWith(root+sep))return route.abort();
        try{return route.fulfill({body:await readFile(path),contentType:({'.png':'image/png','.jpg':'image/jpeg','.css':'text/css','.js':'text/javascript','.woff2':'font/woff2','.woff':'font/woff'})[extname(path)]||'application/octet-stream'});}
        catch{return route.fulfill({status:404,body:''});}
    });
    for(const width of [320,390,600,768,981,1366,1920]){
        await page.setViewportSize({width,height:900});
        for(const name of ['siap','draf','riwayat','terbatas']){
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`${name}@${width}: overflow`);
            const clipped=await page.locator('.bp-head,.bp-page h1,.bp-page .button,.bp-check,.agenda-metric,.bp-ready li').evaluateAll(els=>els.filter(el=>el.scrollWidth>el.clientWidth+2||el.scrollHeight>el.clientHeight+2).map(el=>el.textContent.trim().slice(0,80)));
            assert.deepEqual(clipped,[],`${name}@${width}: clipped text`);
            assert.equal(await page.evaluate(()=>window.injected),undefined);
            if(name==='terbatas'){assert.equal(await page.locator('input[name="dokumen_ids[]"]').count(),0);assert.equal(await page.locator('[data-download]').count(),0);}
            if(name==='draf')assert.ok(await page.locator('[data-download]').isDisabled());
            if([390,1366].includes(width)&&['siap','riwayat'].includes(name))await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
        }
    }
    await page.setViewportSize({width:1366,height:900});await page.goto('http://localhost/audit/siap');
    const docs=page.locator('input[name="dokumen_ids[]"]'),all=page.locator('[data-all="dokumen_ids[]"]');
    await docs.first().check();assert.ok(await all.evaluate(el=>el.indeterminate));
    await all.check();assert.equal(await docs.evaluateAll(els=>els.filter(el=>el.checked).length),2);
    await page.locator('[data-all="formulir_ids[]"]').check();
    assert.ok((await page.locator('#bp-selection').innerText()).includes('2 lampiran'));
    const popupEvent=page.waitForEvent('popup');await page.getByRole('button',{name:'Cetak gabungan',exact:true}).click();
    const popup=await popupEvent;await popup.waitForLoadState();assert.ok((await popup.content()).includes('NOTULEN PERTEMUAN'));await popup.close();
    assert.ok(await page.locator('[data-download]').isEnabled());
    const request=page.waitForRequest(r=>r.url().endsWith('/bundel/unduh'));
    await page.locator('[data-download]').click();await request;
    assert.ok(await page.locator('#bp-processing').isVisible());assert.ok(await page.locator('[data-download]').isDisabled());
    assert.ok(await page.locator('[data-print]').isDisabled());assert.ok(await all.isDisabled());
    assert.equal(await page.locator('#bp-form').getAttribute('aria-busy'),'true');
    assert.ok(payload.includes('dokumen_ids[]'));assert.ok(payload.includes('formulir_ids[]'));assert.ok(payload.includes('sidik'));
    assert.equal(await page.evaluate(()=>{const event=new Event('beforeunload',{cancelable:true});window.dispatchEvent(event);return event.defaultPrevented;}),true);
    const downloadEvent=page.waitForEvent('download');release();const download=await downloadEvent;
    assert.equal(download.suggestedFilename(),'bundel-pertemuan-uji.zip');
    await page.waitForFunction(()=>!document.querySelector('#bp-status').hidden);
    assert.ok((await page.locator('#bp-status').innerText()).includes('Bundel siap'));
    assert.ok(await page.locator('[data-download]').isEnabled());assert.ok(await all.isEnabled());
    fail=true;await page.locator('[data-download]').click();
    await page.waitForFunction(()=>document.querySelector('#bp-status').classList.contains('bp-status--error'));
    assert.ok((await page.locator('#bp-status').innerText()).includes('Lampiran tidak tersedia'));
    assert.ok(await page.locator('[data-download]').isEnabled());assert.ok(await docs.first().isChecked());
    assert.ok(!(await page.locator('#bp-processing').isVisible()));
    await page.goto('http://localhost/audit/cetak');
    assert.ok(await page.locator('.header img,.bundle-gallery img').evaluateAll(els=>els.every(el=>el.complete&&el.naturalWidth>0)));
    await page.emulateMedia({media:'print'});
    await page.evaluate(()=>window.paginateHumasAttendance());
    assert.ok(!(await page.locator('.toolbar').isVisible()));
    const counts=await page.locator('.sheet--attendance .records tbody').evaluateAll(els=>els.map(el=>el.children.length));
    assert.equal(counts.reduce((a,b)=>a+b,0),51);assert.ok(counts.every(n=>n<=24));
    const heights=await page.locator('.sheet--attendance').evaluateAll(els=>els.map(el=>el.getBoundingClientRect().height));
    assert.ok(heights.every(h=>h<=273*96/25.4),`Attendance sheets too tall: ${heights}`);
    assert.ok(!(await page.content()).includes('MASUKAN PRIVAT'));
    await page.pdf({path:`${output}/bundel-pertemuan.pdf`,preferCSSPageSize:true,printBackground:true});
    await page.goto('http://localhost/audit/cetak-draf');await page.evaluate(()=>window.paginateHumasAttendance());
    await page.pdf({path:`${output}/bundel-draf.pdf`,preferCSSPageSize:true,printBackground:true});
    await page.goto('http://localhost/audit/cetak-galeri');
    assert.deepEqual(await page.locator('.bundle-gallery').evaluateAll(els=>els.map(el=>el.children.length)),[4,1]);
    assert.ok(await page.locator('.bundle-gallery img').evaluateAll(els=>els.every(el=>el.complete&&el.naturalWidth>0)));
    const galleryHeights=await page.locator('.bundle-gallery').evaluateAll(els=>els.map(el=>el.closest('.sheet').getBoundingClientRect().height));
    assert.ok(galleryHeights.every(h=>h<=273*96/25.4),`Gallery sheets too tall: ${galleryHeights}`);
    await page.pdf({path:`${output}/bundel-galeri.pdf`,preferCSSPageSize:true,printBackground:true});
    await page.goto('http://localhost/audit/offline');
    assert.ok(await page.locator('.header img').evaluateAll(els=>els.every(el=>el.complete&&el.naturalWidth>0)));
    assert.equal(await page.evaluate(()=>window.injected),undefined);
    await page.goto('http://localhost/audit/indeks');
    assert.ok(await page.locator('.print-header img').evaluateAll(els=>els.every(el=>el.complete&&el.naturalWidth>0)));
    assert.ok((await page.locator('a').first().getAttribute('href')).endsWith('.html'));
    assert.deepEqual(errors,[]);
    console.log(JSON.stringify({result:'passed',pages:9,widths:[320,390,600,768,981,1366,1920],checks:['responsive','selection','print-popup','fetch-download','progress','navigation-guard','error-recovery','permission-visibility','offline-assets','print-pagination-24','gallery-4-per-page','privacy','A4']}));
}finally{await browser.close();}
