import assert from 'node:assert/strict';
import {readFile,mkdir,writeFile} from 'node:fs/promises';
import {resolve,sep,extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium}=await import(process.env.PLAYWRIGHT_MODULE?pathToFileURL(process.env.PLAYWRIGHT_MODULE).href:'playwright');
const fixtures=resolve('storage/framework/testing/humas-theme'),output=resolve('storage/logs/humas-theme-audit'),root=resolve('public');
const manifest=JSON.parse(await readFile(resolve(fixtures,'manifest.json'),'utf8'));
await mkdir(output,{recursive:true});
const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||undefined});
const errors=[],checks=[],writes=[],filters=[];
let currentState;
try {
    const page=await browser.newPage({reducedMotion:'reduce',serviceWorkers:'block'});
    page.on('pageerror',e=>errors.push(e.message));
    await page.route('**/*',async route=>{
        const request=route.request(),url=new URL(request.url());
        if(!['localhost','127.0.0.1'].includes(url.hostname))return route.abort();
        if(request.method()!=='GET'){writes.push(url.pathname);return route.fulfill({status:405,body:''});}
        const state=url.pathname.match(/^\/audit\/([a-z_-]+)$/)?.[1];
        if(state&&manifest[state]){
            if(url.searchParams.size)filters.push({state,path:url.pathname,query:Object.fromEntries(url.searchParams)});
            return route.fulfill({contentType:'text/html',body:await readFile(resolve(fixtures,state+'.html'),'utf8')});
        }
        if(url.pathname==='/notifikasi/ringkasan')return route.fulfill({json:{jumlah_belum_dibaca:0,notifikasi:[]}});
        if(request.isNavigationRequest()&&currentState){
            filters.push({state:currentState,path:url.pathname,query:Object.fromEntries(url.searchParams)});
            return route.fulfill({contentType:'text/html',body:await readFile(resolve(fixtures,currentState+'.html'),'utf8')});
        }
        const path=resolve(root,'.'+decodeURIComponent(url.pathname));
        if(!path.startsWith(root+sep))return route.abort();
        try{return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.webp':'image/webp','.svg':'image/svg+xml','.woff2':'font/woff2'})[extname(path)]||'application/octet-stream'});}
        catch{return route.fulfill({status:404,body:''});}
    });
    for(const [width,height] of (process.env.NUSA_HUMAS_FILTERS_ONLY?[]:[[320,740],[390,844],[768,1024],[1024,768],[1440,960],[1920,1080]])){
        await page.setViewportSize({width,height});
        for(const [state,meta] of Object.entries(manifest)){
            currentState=state;
            await page.goto('http://localhost/audit/'+state);
            await page.waitForFunction(()=>!!document.querySelector('[data-admin-sidebar-toggle] svg.lucide'));
            assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Page overflow ${state}@${width}`);
            const clipped=await page.locator('.content-shell .page-title,.content-shell .button,.content-shell .feature-filter,.content-shell .agenda-metric,.content-shell .tamu-metric,.content-shell .humas-records,.content-shell .agenda-table-shell').evaluateAll(els=>els.filter(el=>el.clientWidth&&el.scrollWidth>el.clientWidth+2).map(el=>({tag:el.tagName,class:el.className,text:el.textContent.trim().slice(0,80),width:el.clientWidth,scroll:el.scrollWidth})));
            assert.deepEqual(clipped,[],`Clipped workspace ${state}@${width}`);
            const metrics=await page.locator('.agenda-metric,.tamu-metric').evaluateAll(els=>els.map(el=>{
                const s=getComputedStyle(el),r=el.getBoundingClientRect();
                return {radius:s.borderRadius,border:s.borderTopWidth,bg:s.backgroundColor,x:r.x,right:r.right,y:r.y};
            }));
            for(const metric of metrics){assert.equal(metric.radius,'8px');assert.equal(metric.border,'1px');assert.notEqual(metric.bg,'rgba(0, 0, 0, 0)');}
            if(metrics.length>1&&metrics[0].y===metrics[1].y)assert.ok(metrics[1].x-metrics[0].right>=11,`Joined metrics ${state}`);
            if(meta.kind==='list'){
                const panel=await page.locator('.feature-filter').evaluate(el=>({bg:getComputedStyle(el).backgroundColor,padding:getComputedStyle(el).paddingLeft}));
                assert.equal(panel.bg,'rgb(237, 244, 252)',`Filter colour ${state}`);
                assert.equal(panel.padding,width<=600?'16px':'20px');
                const contentWidth=await page.locator('.content-shell').evaluate(el=>el.clientWidth-parseFloat(getComputedStyle(el).paddingLeft)-parseFloat(getComputedStyle(el).paddingRight));
                const metricWidth=await page.locator('.agenda-metrics,.tamu-metrics').first().evaluate(el=>el.getBoundingClientRect().width);
                assert.ok(Math.abs(contentWidth-metricWidth)<=2,`Narrow legacy page ${state}@${width}`);
            }
            if(state==='program-error')assert.ok(await page.getByText('Nama program wajib diisi.',{exact:true}).isVisible());
            if((width===390||width===1440)&&!state.endsWith('-empty'))await page.screenshot({path:resolve(output,`${state}-${width}.png`)});
            if(width===390&&['program-kerja-humas','agenda-humas','publikasi-humas','kemitraan-humas','buku-tamu'].includes(state)){
                await page.locator('.humas-records,.agenda-table-shell').first().screenshot({path:resolve(output,`${state}-records-${width}.png`)});
            }
            if(width===1920&&['program-kerja-humas','dokumen-humas'].includes(state))await page.screenshot({path:resolve(output,`${state}-${width}.png`)});
            checks.push({state,width,height});
        }
    }
    await page.setViewportSize({width:1440,height:960});
    for(const [state,field,value,submit] of [
        ['program-kerja-humas','status','berjalan',false],
        ['agenda-humas','jenis','orang_tua',true],
        ['buku-tamu','status','berkunjung',false],
    ]){
        currentState=state;
        await page.goto('http://localhost/audit/'+state);
        await page.waitForFunction(()=>!!document.querySelector('[data-admin-sidebar-toggle] svg.lucide'));
        await page.locator('.feature-filter select[name="'+field+'"]').selectOption(value);
        if(submit)await page.locator('.feature-filter button[type="submit"]').click();
        await page.waitForURL(url=>url.searchParams.get(field)===value);
        await page.waitForLoadState('load');
        await page.waitForFunction(()=>!!document.querySelector('[data-admin-sidebar-toggle] svg.lucide'));
        assert.ok(filters.some(f=>f.state===state&&f.query[field]===value),`Filter navigation missing: ${state}, received ${JSON.stringify(filters)}`);
    }
    assert.deepEqual(errors,[]);
    assert.deepEqual(writes,[]);
    assert.equal(filters.length,3);
    await writeFile(resolve(output,'audit.json'),JSON.stringify({checks,errors,writes,filters},null,2));
    console.log(JSON.stringify({result:'passed',states:checks.length,filters:filters.length,errors,writes}));
} finally {await browser.close();}
