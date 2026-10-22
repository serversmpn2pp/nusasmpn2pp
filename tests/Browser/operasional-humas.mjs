import assert from 'node:assert/strict';
import {readFile,mkdir,writeFile} from 'node:fs/promises';
import {resolve,sep,extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium}=await import(process.env.PLAYWRIGHT_MODULE?pathToFileURL(process.env.PLAYWRIGHT_MODULE).href:'playwright');
const fixtures=resolve('storage/framework/testing/operasional-humas'),output=resolve('storage/logs/operasional-humas-audit'),root=resolve('public');
const states=['kontak','form','kontak-detail','kontak-terbatas','paket','pendukung','kontribusi','kontribusi-detail','kontribusi-form'];
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
        try{return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.webp':'image/webp','.svg':'image/svg+xml','.woff2':'font/woff2'})[extname(path)]||'application/octet-stream'});}
        catch{return route.fulfill({status:404,body:''});}
    });
    for(const [width,height] of [[320,740],[390,844],[768,1024],[1024,768],[1440,960],[1920,1080]]){
        await page.setViewportSize({width,height});
        for(const state of states){
            await page.goto('http://localhost/audit/'+state);
            await page.waitForFunction(()=>!!document.querySelector('[data-admin-sidebar-toggle] svg.lucide'));
            assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Page overflow ${state}@${width}`);
            const clipped=await page.locator('.page-title,.ho-band,.ho-band h2,.ho-metric,.ho-filter,.ho-grid,.ho-steps li,.ho-steps strong,.ho-files li,.ho-band .button').evaluateAll(els=>els.filter(el=>el.clientWidth&&el.scrollWidth>el.clientWidth+2).map(el=>({text:el.textContent.slice(0,90),width:el.clientWidth,scroll:el.scrollWidth})));
            assert.deepEqual(clipped,[],`Clipped content ${state}@${width}`);
            if(state==='kontak'){
                assert.ok(await page.locator('.ho-table .button').evaluateAll(els=>els.every(el=>el.getBoundingClientRect().height<=48)),'Table commands must stay on one line');
                assert.ok(await page.locator('.ho-table .badge').evaluateAll(els=>els.every(el=>el.getBoundingClientRect().height<=30)),'Status labels must stay on one line');
            }
            if(state==='kontak-terbatas'){
                const html=await page.content();
                for(const secret of ['081234567890','privat@example.test','SUMBER KONTAK PRIVAT'])assert.ok(!html.includes(secret));
            }
            if(state==='kontribusi-detail'){
                assert.ok(await page.getByRole('button',{name:'Ajukan ke pimpinan',exact:true}).isVisible());
                assert.equal(await page.getByRole('button',{name:'Setujui konten',exact:true}).count(),0);
                assert.equal(await page.getByRole('button',{name:'Simpan bukti tayang',exact:true}).count(),0);
            }
            if(width===390||width===1440)await page.screenshot({path:resolve(output,`${state}-${width}.png`)});
            checks.push({state,width,height});
        }
    }
    assert.deepEqual(errors,[]);assert.deepEqual(writes,[]);
    await writeFile(resolve(output,'audit.json'),JSON.stringify({checks,errors,writes},null,2));
    console.log(JSON.stringify({result:'passed',states:checks.length,errors,writes}));
}finally{await browser.close();}
