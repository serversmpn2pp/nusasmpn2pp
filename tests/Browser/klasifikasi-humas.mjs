import assert from 'node:assert/strict';
import {readFile, mkdir, writeFile} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium}=await import(process.env.PLAYWRIGHT_MODULE?pathToFileURL(process.env.PLAYWRIGHT_MODULE).href:'playwright');
const fixtures=resolve('storage/framework/testing/klasifikasi-humas');
const output=resolve('storage/logs/klasifikasi-humas-audit'),root=resolve('public');
await mkdir(output,{recursive:true});
const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||undefined});
const errors=[],writes=[],checks=[];
try{
    const page=await browser.newPage({reducedMotion:'reduce',serviceWorkers:'block'});
    page.on('pageerror',e=>errors.push(e.message));
    await page.route('**/*',async route=>{
        const req=route.request(),u=new URL(req.url());
        if(!['localhost','127.0.0.1'].includes(u.hostname))return route.abort();
        if(req.method()!=='GET'){
            assert.equal(u.pathname,'/dokumen-humas/klasifikasi-kolektif');
            writes.push(Object.fromEntries(new URLSearchParams(req.postData())));
            const values=new URLSearchParams(req.postData());
            assert.equal(values.get('_method'),'PATCH');
            assert.equal(values.getAll('ids[]').length,2);
            assert.equal(values.get('urusan'),'komite');
            assert.equal(values.get('jenis_dokumen'),'notulen');
            assert.ok([...values.keys()].filter(k=>k.startsWith('sidik[')).length===2);
            return route.fulfill({contentType:'text/html',body:'<!doctype html><html lang="id"><body><p role="status">Klasifikasi dikonfirmasi</p></body></html>'});
        }
        const state=u.pathname.match(/^\/audit\/(index|create|edit|show|program|program-komite|surat)$/)?.[1];
        if(state)return route.fulfill({contentType:'text/html',body:await readFile(resolve(fixtures,state+'.html'),'utf8')});
        if(u.pathname==='/notifikasi/ringkasan')return route.fulfill({json:{jumlah_belum_dibaca:0,notifikasi:[]}});
        const path=resolve(root,'.'+decodeURIComponent(u.pathname));
        if(!path.startsWith(root+sep))return route.abort();
        try{return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.webp':'image/webp','.svg':'image/svg+xml','.woff2':'font/woff2'})[extname(path)]||'application/octet-stream'});}
        catch{return route.fulfill({status:404,body:''});}
    });
    for(const [width,height] of [[320,740],[390,844],[768,1024],[1024,768],[1440,960],[1920,1080]]){
        await page.setViewportSize({width,height});
        for(const state of ['index','create','edit','show','program','program-komite','surat']){
            await page.goto('http://localhost/audit/'+state);
            await page.waitForFunction(()=>!!document.querySelector('[data-admin-sidebar-toggle] svg.lucide'));
            assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Page overflow ${state}@${width}`);
            const clipped=await page.locator('.page-title,.dh-classification,.dh-classification h2,.dh-bulk,.dh-relations,.dh-link-form,.dh-button,.form-actions .button').evaluateAll(els=>els.filter(el=>el.clientWidth&&el.scrollWidth>el.clientWidth+2).map(el=>({text:el.textContent.slice(0,80),width:el.clientWidth,scroll:el.scrollWidth})));
            assert.deepEqual(clipped,[],`Clipped content ${state}@${width}`);
            if(state==='index'){
                assert.ok(await page.locator('[data-document-bulk]').isHidden());
                const rows=page.locator('[data-document-select]');
                assert.equal(await rows.count(),2);
                await rows.first().check();
                assert.equal(await page.locator('[data-document-count]').textContent(),'1 dokumen dipilih');
                assert.ok(await page.locator('[data-documents-all]').evaluate(e=>e.indeterminate));
                await page.locator('[data-documents-all]').check();
                assert.equal(await page.locator('[data-document-count]').textContent(),'2 dokumen dipilih');
                await page.locator('#bulk-urusan').selectOption('komite');
                await page.locator('#bulk-jenis').selectOption('notulen');
                await page.locator('#bulk-alasan').fill('Pemeriksaan arsip rapat komite');
                if([390,1440].includes(width))await page.screenshot({path:resolve(output,`bulk-${width}.png`)});
                await page.getByRole('button',{name:'Konfirmasi klasifikasi',exact:true}).click();
                await page.getByRole('status').waitFor();
            }else if(state==='create'||state==='edit'){
                assert.ok(await page.locator('#urusan').evaluate(e=>e.required));
                assert.ok(await page.locator('#jenis_dokumen').evaluate(e=>e.required));
                if(state==='create')assert.equal(await page.locator('#urusan').inputValue(),'');
                if(state==='edit')assert.equal(await page.locator('input[name="sidik"]').count(),1);
                await page.locator('#urusan').selectOption('komite');
                await page.locator('#jenis_dokumen').selectOption('notulen');
                if([390,1440].includes(width))await page.screenshot({path:resolve(output,`${state}-${width}.png`)});
            }else if(state==='show'){
                assert.ok(await page.getByRole('heading',{name:'Rapat, program, dan surat terkait'}).isVisible());
                assert.equal(await page.locator('[data-document-link] input[name="sidik"]').count(),1);
                if([390,1440].includes(width)){
                    await page.locator('.dh-relations').first().scrollIntoViewIfNeeded();
                    await page.screenshot({path:resolve(output,`relations-${width}.png`)});
                }
            }else{
                const backlinks=page.locator('[data-document-backlinks]');
                assert.equal(await backlinks.count(),1);
                assert.equal(await backlinks.evaluate(e=>!!e.closest('.page-header,.actions')),false,'Backlinks must be in page content, not header actions');
                assert.ok(await backlinks.locator('a').count()>0);
                assert.ok(await backlinks.locator('svg.lucide').count()>0);
                assert.deepEqual(await backlinks.evaluate(e=>[e,...e.querySelectorAll('a,li,h2')].filter(x=>x.scrollWidth>x.clientWidth+2).map(x=>x.textContent)),[],`Clipped backlinks ${state}@${width}`);
                if([390,1440].includes(width)){
                    await backlinks.scrollIntoViewIfNeeded();
                    await page.screenshot({path:resolve(output,`${state}-${width}.png`)});
                }
            }
            checks.push({state,width,height});
        }
    }
    assert.deepEqual(errors,[]);
    assert.equal(writes.length,6);
    await writeFile(resolve(output,'audit.json'),JSON.stringify({checks,errors,submissions:writes.length},null,2));
    console.log(JSON.stringify({result:'passed',states:checks.length,submissions:writes.length,errors}));
}finally{await browser.close();}
