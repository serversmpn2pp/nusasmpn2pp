import assert from 'node:assert/strict';
import {readFile,mkdir,writeFile} from 'node:fs/promises';
import {resolve,sep,extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium}=await import(process.env.PLAYWRIGHT_MODULE?pathToFileURL(process.env.PLAYWRIGHT_MODULE).href:'playwright');
const fixtures=resolve('storage/framework/testing/faq-kinerja-humas'),output=resolve('storage/logs/faq-kinerja-humas-audit'),root=resolve('public');
const states=['faq-empty','faq','faq-form','faq-detail','faq-parent','report-empty','report-list','report-create','report-form','report','report-final','print'];
await mkdir(output,{recursive:true});
const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||undefined});
const errors=[],checks=[],writes=[];
try{
    const page=await browser.newPage({reducedMotion:'reduce',serviceWorkers:'block'});
    page.on('pageerror',e=>errors.push(e.message));
    await page.route('**/*',async route=>{
        const req=route.request(),u=new URL(req.url());
        if(!['localhost','127.0.0.1'].includes(u.hostname))return route.abort();
        if(req.method()!=='GET'){writes.push(u.pathname);return route.fulfill({status:405,body:''});}
        const state=u.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if(state&&states.includes(state))return route.fulfill({contentType:'text/html',body:await readFile(resolve(fixtures,state+'.html'),'utf8')});
        if(u.pathname==='/notifikasi/ringkasan')return route.fulfill({json:{jumlah_belum_dibaca:0,notifikasi:[]}});
        const path=resolve(root,'.'+decodeURIComponent(u.pathname));
        if(!path.startsWith(root+sep))return route.abort();
        try{return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.svg':'image/svg+xml','.woff2':'font/woff2'})[extname(path)]||'application/octet-stream'});}
        catch{return route.fulfill({status:404,body:''});}
    });
    for(const [width,height] of [[320,740],[390,844],[768,1024],[1024,768],[1440,960],[1920,1080]]){
        await page.setViewportSize({width,height});
        for(const state of states){
            await page.goto('http://localhost/audit/'+state);
            if(state!=='print')await page.waitForFunction(()=>!!document.querySelector('[data-admin-sidebar-toggle] svg.lucide'));
            assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Page overflow ${state}@${width}`);
            const clipped=await page.locator('.page-title,.ho-band h2,.fk-faq summary strong,.fk-stat,.ho-filter,.ho-grid,.fk-search,.fk-action,.ho-band .button').evaluateAll(els=>els.filter(el=>el.clientWidth&&el.scrollWidth>el.clientWidth+2).map(el=>({text:el.textContent.slice(0,90),width:el.clientWidth,scroll:el.scrollWidth})));
            assert.deepEqual(clipped,[],`Clipped content ${state}@${width}`);
            if(state==='faq'||state==='faq-parent'){
                await page.locator('.fk-faq summary').first().click();
                assert.ok(await page.locator('.fk-faq[open] .fk-answer').first().isVisible());
                if(state==='faq-parent')assert.equal(await page.getByRole('link',{name:'Edit FAQ',exact:true}).count(),0);
            }
            if(state==='report-final'){
                assert.equal(await page.getByRole('button',{name:'Finalisasi laporan',exact:true}).count(),0);
                assert.ok(await page.getByRole('button',{name:'Buka revisi',exact:true}).isVisible());
            }
            if(state==='report'){
                const finalize=page.locator('form').filter({has:page.getByRole('button',{name:'Finalisasi laporan',exact:true})});
                assert.equal(await finalize.evaluate(el=>el.checkValidity()),false);
                await finalize.locator('input[name="alasan"]').fill('Evaluasi semester sudah diperiksa');
                assert.equal(await finalize.evaluate(el=>el.checkValidity()),false);
                await finalize.locator('input[type="checkbox"]').check();
                assert.equal(await finalize.evaluate(el=>el.checkValidity()),true);
            }
            if(state==='print')assert.ok(await page.locator('.print-header img').evaluateAll(els=>els.every(el=>el.complete&&el.naturalWidth>0)));
            assert.equal(await page.evaluate(()=>window.injected),undefined);
            if((width===390||width===1440)&&['faq','faq-parent','report','report-form','print'].includes(state)){
                await page.evaluate(()=>{document.querySelectorAll('*').forEach(el=>{if(el.scrollTop)el.scrollTop=0;});window.scrollTo(0,0);});
                await page.screenshot({path:resolve(output,`${state}-${width}.png`),fullPage:true});
            }
            checks.push({state,width,height});
        }
    }
    await page.setViewportSize({width:794,height:1123});await page.goto('http://localhost/audit/print');
    await page.emulateMedia({media:'print'});
    assert.ok(!(await page.locator('.print-toolbar').isVisible()));
    await page.pdf({path:resolve(output,'laporan-semester.pdf'),format:'A4',printBackground:true});
    assert.deepEqual(errors,[]);assert.deepEqual(writes,[]);
    await writeFile(resolve(output,'audit.json'),JSON.stringify({checks,errors,writes},null,2));
    console.log(JSON.stringify({result:'passed',states:checks.length,errors,writes}));
}finally{await browser.close();}
