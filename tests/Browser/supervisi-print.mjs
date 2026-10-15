// Generate fixtures with NUSA_CAPTURE_SUPERVISI=1 php artisan test --filter=Supervisi.
import assert from 'node:assert/strict';
import {readFile,mkdir} from 'node:fs/promises';
import {resolve,sep,extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium}=await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const {PDFDocument}=await import(process.env.PDF_LIB_MODULE ? pathToFileURL(process.env.PDF_LIB_MODULE).href : 'pdf-lib');
const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output=resolve('storage/logs/supervisi-qa');
await mkdir(output,{recursive:true});
try {
    const page=await browser.newPage(),errors=[],pages={};
    page.on('pageerror',e=>errors.push(e.message));
    await page.route('**/*',async route=>{
        const url=new URL(route.request().url()),name=url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if(name)return route.fulfill({contentType:'text/html',body:await readFile(`storage/framework/testing/supervisi/${name}.html`,'utf8')});
        if(route.request().method()==='POST')return route.fulfill({contentType:'text/html',body:'<!doctype html><html><body>Captured</body></html>'});
        const root=resolve('public'),path=resolve(root,'.'+decodeURIComponent(url.pathname));
        if(!path.startsWith(root+sep))return route.abort();
        try{return route.fulfill({body:await readFile(path),contentType:({'.css':'text/css','.js':'text/javascript','.png':'image/png','.woff2':'font/woff2'})[extname(path)]||'application/octet-stream'});}
        catch{return route.fulfill({status:404,body:''});}
    });
    for(const width of [320,390,768,1366,1920]) {
        await page.setViewportSize({width,height:1000});
        for(const name of ['rekap','rekap-guru','rekap-cakupan','rekap-kosong','cetak-awal','cetak-laporan','cetak-rekap','cetak-cakupan']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(!await page.locator('body').evaluate(e=>/@(if|endif|else|endforeach|endunless)\b/.test(e.innerText)),`Uncompiled template ${name}`);
            assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Page overflow ${name}@${width}`);
            const clipped=await page.locator('.sp-stat,.sp-head,.sp-nav,.sp-stage-nav,.sp-filters,.print-toolbar,.facts,.kop').evaluateAll(els=>els.filter(e=>e.scrollWidth>e.clientWidth+1).map(e=>e.textContent.trim()));
            assert.deepEqual(clipped,[],`Clipped controls ${name}@${width}`);
            if(name==='rekap')assert.ok(await page.locator('.sp-recap-table td:nth-child(2)').first().evaluate(e=>e.getBoundingClientRect().width>=200),'Stable teacher column');
            if([390,1366].includes(width))await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:!name.startsWith('cetak-')});
        }
    }
    await page.goto('http://localhost/audit/rekap');
    assert.ok(await page.locator('#sp-print-selected').isDisabled());
    await page.goto('http://localhost/audit/rekap-banyak');
    await page.locator('#sp-select-page').click();
    assert.equal(await page.locator('[data-report-choice]:checked').count(),10);
    assert.equal(await page.locator('[data-report-choice]:disabled').count(),await page.locator('[data-report-choice]').count()-10);
    await page.locator('#sp-select-page').click();
    assert.equal(await page.locator('[data-report-choice]:checked').count(),0);
    assert.ok(await page.locator('#sp-print-selected').isDisabled());
    await page.locator('[data-report-choice]').first().check();
    assert.ok(await page.locator('#sp-print-selected').isEnabled());
    assert.equal(await page.locator('#sp-selected').textContent(),'1 dari 10 sesi dipilih');
    await page.locator('#sp-select-page').click();
    assert.ok(await page.locator('#sp-print-selected').isDisabled());
    await page.goto('http://localhost/audit/rekap-guru');
    assert.equal(await page.getByRole('link',{name:'Guru belum terjadwal',exact:true}).count(),0);
    await page.goto('http://localhost/audit/rekap');
    await page.evaluate(()=>{for(const s of document.querySelectorAll('[data-dependent]'))if(s.options.length>1)s.selectedIndex=1;});
    const request=page.waitForRequest(r=>new URL(r.url()).pathname==='/supervisi/rekap');
    await page.locator('#semester').selectOption('genap');
    const submitted=new URL((await request).url());
    assert.equal(submitted.searchParams.get('semester'),'genap');
    for(const key of ['program_id','guru_id','supervisor_id'])assert.equal(submitted.searchParams.get(key),'',`Reset dependent ${key}`);
    await page.goto('http://localhost/audit/cetak-laporan');
    await page.evaluate(()=>{window.printCalls=0;window.print=()=>window.printCalls++;});
    await page.getByRole('button',{name:'Cetak / Simpan PDF'}).click();
    assert.equal(await page.evaluate(()=>window.printCalls),1);
    await page.setViewportSize({width:1366,height:1000});
    for(const name of ['cetak-awal','cetak-laporan','cetak-kolektif','cetak-panjang','cetak-rekap','cetak-cakupan','cetak-rekap-panjang']) {
        await page.goto(`http://localhost/audit/${name}`);
        await page.evaluate(()=>document.fonts.ready);
        assert.ok(await page.locator('img').evaluateAll(els=>els.every(e=>e.complete&&e.naturalWidth>0)),`Logos ${name}`);
        await page.emulateMedia({media:'print'});
        assert.ok(await page.locator('.print-toolbar').isHidden());
        const pdf=await page.pdf({path:`${output}/${name}.pdf`,preferCSSPageSize:true,printBackground:true});
        const document=await PDFDocument.load(pdf);
        pages[name]=document.getPageCount();
        const landscape=['cetak-rekap','cetak-cakupan','cetak-rekap-panjang'].includes(name);
        for(const sheet of document.getPages()) {
            assert.ok(Math.abs(sheet.getWidth()-(landscape?841.89:595.28))<1&&Math.abs(sheet.getHeight()-(landscape?595.28:841.89))<1,`A4 ${name}`);
        }
        await page.emulateMedia({media:'screen'});
    }
    assert.equal(pages['cetak-kolektif'],pages['cetak-laporan']+pages['cetak-awal'],'Each report starts on a new page');
    assert.ok(pages['cetak-panjang']>pages['cetak-laporan'],'Long notes flow across pages');
    assert.ok(pages['cetak-rekap-panjang']>1,'Recap flows across pages');
    assert.deepEqual(errors,[]);
    console.log(JSON.stringify({result:'passed',pages,checks:['responsive-five-widths','selection','role-controls','print-button','logos','a4','multi-page','collective-page-breaks']}));
} finally {await browser.close();}
