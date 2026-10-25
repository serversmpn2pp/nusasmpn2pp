import {readFile,mkdir,writeFile} from 'node:fs/promises';
import path from 'node:path';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';

const {chromium}=await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE).href);
const root=process.cwd(),fixtures=path.join(root,'storage/framework/testing/persentase-presensi'),out=path.join(root,'storage/logs/persentase-presensi');
await mkdir(out,{recursive:true});
const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||undefined});
const results=[];
try{
  for(const fixture of ['index','detail','all-excluded']){
    const html=await readFile(path.join(fixtures,fixture+'.html'),'utf8');
    for(const [width,height] of [[1920,1080],[1280,720],[390,844],[320,640]]){
      const context=await browser.newContext({viewport:{width,height}}),page=await context.newPage();
      const errors=[];page.on('pageerror',e=>errors.push(e.message));
      await page.route('http://localhost/**',async route=>{
        const url=new URL(route.request().url());
        if(url.pathname==='/test-report')return route.fulfill({contentType:'text/html',body:html});
        if(url.pathname==='/notifikasi/ringkasan')return route.fulfill({contentType:'application/json',body:'{"belum_dibaca":0,"items":[]}'});
        const file=path.resolve(root,'public','.'+url.pathname);
        if(!file.startsWith(path.join(root,'public')+path.sep))return route.abort();
        try{const types={'.js':'application/javascript','.css':'text/css','.png':'image/png','.svg':'image/svg+xml','.woff2':'font/woff2'};return route.fulfill({contentType:types[path.extname(file)]||'application/octet-stream',body:await readFile(file)});}catch{return route.fulfill({status:404,body:''});}
      });
      await page.goto('http://localhost/test-report');await page.locator('h1').waitFor();
      const text=await page.locator('body').innerText();
      if(fixture==='index'){assert.ok(text.includes('17 hari wajib'));assert.ok(text.includes('5 hari pengecualian'));assert.ok(text.includes('100%'));}
      if(fixture==='detail'){assert.ok(text.includes('17 hari wajib'));assert.ok(text.includes('Kehadiran 100%'));}
      if(fixture==='all-excluded'){assert.ok(text.includes('0 hari wajib'));assert.ok(!/(^|[^\d])0%/.test(text));}
      const geometry=await page.evaluate(()=>({width:innerWidth,scrollWidth:document.documentElement.scrollWidth}));
      assert.ok(geometry.scrollWidth<=width+1,`${fixture}: horizontal page overflow ${JSON.stringify(geometry)}`);
      assert.deepEqual(errors,[]);
      await page.screenshot({path:path.join(out,`${fixture}-${width}.png`),fullPage:true});
      results.push({fixture,width,height,errors:0});await context.close();
    }
  }
}finally{await browser.close();}
await writeFile(path.join(out,'results.json'),JSON.stringify(results,null,2));
console.log(JSON.stringify({screens:results.length,errors:0}));
