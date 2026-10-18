import assert from 'node:assert/strict';
import {readFile, mkdir, writeFile} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output=resolve('storage/logs/dashboard-admin-qa');
const fixtures=resolve('storage/framework/testing/dashboard-admin');
const publicRoot=resolve('public');
await mkdir(output,{recursive:true});
const errors=[],missing=[],writes=[],checks=[];

try {
  const context=await browser.newContext({serviceWorkers:'block',reducedMotion:'reduce'});
  const page=await context.newPage();
  page.on('pageerror',error=>errors.push(error.message));
  await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    assert.ok(['localhost','127.0.0.1'].includes(url.hostname),`Unexpected external origin: ${url.hostname}`);
    if(request.method()!=='GET'){writes.push(url.pathname);return route.fulfill({status:405,body:''});}
    const fixture=url.pathname.match(/^\/_dashboard-qa\/([a-z-]+)$/)?.[1];
    if(fixture)return route.fulfill({contentType:'text/html',body:await readFile(resolve(fixtures,`${fixture}.html`),'utf8')});
    if(url.pathname==='/notifikasi/ringkasan')return route.fulfill({json:{jumlah_belum_dibaca:0,notifikasi:[]}});
    const path=resolve(publicRoot,'.'+decodeURIComponent(url.pathname));
    assert.ok(path.startsWith(publicRoot+sep),'Asset path escaped public directory');
    try{return route.fulfill({body:await readFile(path),contentType:({'.css':'text/css','.js':'text/javascript','.png':'image/png','.webp':'image/webp','.svg':'image/svg+xml','.webmanifest':'application/manifest+json','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'});}
    catch{missing.push(url.pathname);return route.fulfill({status:404,body:''});}
  });
  for(const [width,height] of [[320,740],[360,740],[390,844],[768,1024],[1024,768],[1280,900],[1440,960],[1920,1080],[844,390]]){
    await page.setViewportSize({width,height});
    for(const fixture of ['populated','empty','holiday','exceptions']){
      await page.goto(`http://localhost/_dashboard-qa/${fixture}`);
      await page.waitForFunction(()=>!!window.Chart?.getChart('attendance-chart'));
      await page.evaluate(()=>Promise.all([...document.images].map(image=>image.decode())));
      const readability=await page.evaluate(()=>{
        const sizes={'.page-heading h1':32,'.section-heading h2':20,'.secondary-heading h2':20,'.greeting':16,'.stat-copy p':16,'.stat-copy strong':36,'.stat-copy small':13,'.period-switch button':14,'.chart-caption p':13,'.legend-button':13,'.attendance-breakdown':14,'.secondary-metrics dt':14,'.secondary-metrics dd':32,'.secondary-note':13};
        const tooSmall=Object.entries(sizes).flatMap(([selector,min])=>[...document.querySelectorAll(selector)].filter(e=>parseFloat(getComputedStyle(e).fontSize)<min).map(e=>({selector,actual:getComputedStyle(e).fontSize,min})));
        const rgb=value=>(value.match(/[\d.]+/g)||[]).map(Number);
        const luminance=values=>values.slice(0,3).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;}).reduce((sum,v,i)=>sum+v*[.2126,.7152,.0722][i],0);
        const contrast=[...document.querySelectorAll('.stat-copy small,.heading-date,.section-kicker,.legend-button,.secondary-note,.attendance-breakdown dt')].filter(e=>e.getClientRects().length).map(e=>{
          let parent=e,bg=[255,255,255];
          while(parent){const value=rgb(getComputedStyle(parent).backgroundColor);if(value.length===3||value[3]===1){bg=value;break;}parent=parent.parentElement;}
          const a=luminance(rgb(getComputedStyle(e).color)),b=luminance(bg);
          return {text:e.textContent.trim(),ratio:(Math.max(a,b)+.05)/(Math.min(a,b)+.05)};
        }).filter(item=>item.ratio<4.5);
        return {tooSmall,contrast,chartFont:Chart.getChart('attendance-chart').options.scales.x.ticks.font.size};
      });
      assert.deepEqual(readability.tooSmall,[],`Small text ${width} ${fixture}`);
      assert.deepEqual(readability.contrast,[],`Low contrast ${width} ${fixture}`);
      assert.ok(readability.chartFont>=13,'Graph labels must remain readable');
      for(const period of ['day','week','month']){
        await page.locator(`[data-period="${period}"]`).click();
        for(const type of ['bar','line']){
          await page.locator(`[data-chart-type="${type}"]`).click();
          const layout=await page.evaluate(({period})=>{
            const canvas=document.querySelector('#attendance-chart'),chart=Chart.getChart(canvas);
            const pixels=canvas.getContext('2d').getImageData(0,0,canvas.width,canvas.height).data;
            let marks=0;
            for(let i=0;i<pixels.length;i+=4)if(pixels[i+3]>150&&pixels[i]<70&&pixels[i+1]>75&&pixels[i+2]>60)marks++;
            const rect=canvas.getBoundingClientRect();
            return {scroll:document.documentElement.scrollWidth,canvas:{left:rect.left,right:rect.right,width:rect.width,height:rect.height,marks},
              clipped:[...document.querySelectorAll('.page-heading,.school-stats,.stat,.chart-toolbar,.chart-caption,.attendance-summary,.attention-item,.activity-row,.staff-section,.page-footer')].filter(e=>e.scrollWidth>e.clientWidth+1).map(e=>e.className),
              images:[...document.images].every(image=>image.naturalWidth>0),type:chart.config.type,
              totals:JSON.parse(document.querySelector('#admin-attendance-data').textContent)[period].summary,
              empty:!document.querySelector('#chart-empty').hidden};
          },{period});
          assert.ok(layout.scroll<=width+1,`Overflow ${width} ${fixture} ${period}: ${layout.scroll}`);
          if(width>760)assert.ok(await page.evaluate(()=>document.documentElement.scrollHeight<=innerHeight+1),'Desktop must not have an outer vertical scrollbar');
          assert.deepEqual(layout.clipped,[],`Clipping ${width} ${fixture} ${period}`);
          assert.ok(layout.images,'Missing image');
          assert.equal(layout.type,type);
          assert.ok(layout.canvas.left>=0&&layout.canvas.right<=width+1&&layout.canvas.width>150,'Bad canvas framing');
          const t=layout.totals;
          assert.equal(t.present+t.permit+t.sick+t.absent+t.pending,t.opportunities);
          assert.ok(t.late<=t.present,'Late arrivals counted twice');
          if(!layout.empty&&t.present>0)assert.ok(layout.canvas.marks>20,'Blank attendance series');
          if(t.opportunities===0)assert.equal(await page.locator('#attendance-rate').textContent(),'-');
          checks.push({width,height,fixture,period,type,passed:true});
        }
      }
      const primary=await page.locator('#count-present').textContent();
      for(const kind of ['pegawai','laporan']){
        const section=page.locator(`[data-stat-chart="${kind}"]`);
        for(const period of ['day','week','month']){
          await section.locator(`[data-stat-period="${period}"]`).click();
          for(const type of ['bar','line']){
            await section.locator(`[data-stat-type="${type}"]`).click();
            const result=await section.evaluate((root,{period})=>{
              const config=JSON.parse(root.querySelector('[data-stat-data]').textContent),p=config.periods[period];
              const canvas=root.querySelector('canvas'),chart=Chart.getChart(canvas),box=canvas.getBoundingClientRect();
              const pixels=canvas.getContext('2d').getImageData(0,0,canvas.width,canvas.height).data;let marks=0;
              for(let i=0;i<pixels.length;i+=4)if(pixels[i+3]>150&&pixels[i]<80&&pixels[i+1]>50&&pixels[i+2]>70)marks++;
              return {type:chart.config.type,summary:p.summary,actual:chart.data.datasets.map(d=>d.data),expected:config.series.map(s=>p.points.map(point=>point[s.key])),
                marks,empty:!!p.empty,left:box.left,right:box.right,width:box.width,height:box.height,
                clipped:[...root.querySelectorAll('.chart-toolbar,.secondary-metrics,.secondary-heading,.chart-caption')].filter(e=>e.scrollWidth>e.clientWidth+1).map(e=>e.className)};
            },{period});
            assert.equal(result.type,type);assert.deepEqual(result.actual,result.expected);
            assert.deepEqual(result.clipped,[],`Clipping ${kind} ${width}`);
            assert.ok(result.left>=0&&result.right<=width+1&&result.width>150&&result.height>150,`Bad ${kind} canvas`);
            if(!result.empty&&result.expected.some(values=>values.some(v=>v>0)))assert.ok(result.marks>20,`Blank ${kind} canvas`);
            if(kind==='pegawai'){
              const t=result.summary;assert.equal(t.present+t.permit+t.absent+t.pending,t.opportunities);assert.ok(t.late<=t.present);
            }else assert.equal(result.summary.pending+result.summary.finished,result.summary.total);
            assert.equal(await page.locator('#count-present').textContent(),primary,'Secondary filters must not change student attendance');
            checks.push({width,height,fixture,kind,period,type,passed:true});
          }
        }
        await section.locator('[data-stat-period="day"]').click();
      }
      if([390,1440].includes(width)){
        await page.locator('[data-period="day"]').click();
        await page.screenshot({path:resolve(output,`${fixture}-${width}.png`),fullPage:true});
      }
      assert.equal(await page.locator('.dashboard-actions').count(),0,'Quick access must be removed for admin');
    }
  }
  await page.setViewportSize({width:1440,height:960});
  await page.goto('http://localhost/_dashboard-qa/populated');
  await page.waitForFunction(()=>!!window.Chart?.getChart('attendance-chart'));
  await page.locator('[data-period="week"]').click();
  assert.equal(await page.locator('#count-present').textContent(),'2');
  assert.equal(await page.locator('#count-late').textContent(),'1');
  await page.locator('#chart-table').click();
  assert.equal(await page.locator('#admin-chart-dialog tbody tr').count(),5);
  await page.screenshot({path:resolve(output,'table-desktop.png')});
  await page.keyboard.press('Escape');
  assert.ok(await page.locator('#chart-table').evaluate(e=>e===document.activeElement));
  await page.locator('[data-series="1"]').click();
  assert.equal(await page.locator('[data-series="1"]').getAttribute('aria-pressed'),'false');
  await page.locator('[data-series="0"]').click();
  assert.equal(await page.locator('[data-series="0"]').getAttribute('aria-pressed'),'true');
  await page.locator('[data-series="1"]').click();
  const staff=page.locator('[data-stat-chart="pegawai"]'),reports=page.locator('[data-stat-chart="laporan"]');
  await staff.locator('[data-stat-period="week"]').click();
  assert.equal(await staff.locator('[data-stat-count="present"]').textContent(),'2');
  await staff.locator('[data-stat-table]').click();
  assert.match(await page.locator('#admin-chart-dialog-title').textContent(),/pegawai/);
  assert.equal(await page.locator('#admin-chart-dialog tbody tr').count(),5);
  await page.keyboard.press('Escape');
  assert.ok(await staff.locator('[data-stat-table]').evaluate(e=>e===document.activeElement));
  await reports.locator('[data-stat-period="day"]').click();
  assert.equal(await reports.locator('[data-stat-count="total"]').textContent(),'3');
  await reports.locator('[data-stat-table]').click();
  assert.match(await page.locator('#admin-chart-dialog-title').textContent(),/laporan siswa/);
  assert.equal(await page.locator('#admin-chart-dialog thead th').count(),4);
  await page.keyboard.press('Escape');
  await reports.locator('[data-stat-series="1"]').click();await reports.locator('[data-stat-series="2"]').click();await reports.locator('[data-stat-series="0"]').click();
  assert.equal(await reports.locator('[data-stat-series="0"]').getAttribute('aria-pressed'),'true');
  await reports.locator('[data-stat-series="1"]').click();await reports.locator('[data-stat-series="2"]').click();
  await staff.scrollIntoViewIfNeeded();
  await page.screenshot({path:resolve(output,'lower-desktop.png')});
  await page.locator('.page-footer').scrollIntoViewIfNeeded();
  assert.ok(await page.evaluate(()=>scrollY===0&&document.documentElement.scrollHeight<=innerHeight+1),'Root must stay at viewport height');
  assert.ok(await page.locator('.page-footer').evaluate(e=>e.getBoundingClientRect().bottom<=innerHeight+1),'Footer must remain reachable');
  await page.screenshot({path:resolve(output,'footer-desktop.png')});
  await page.locator('.app-content').evaluate(element=>element.scrollTop=0);
  assert.ok(await page.locator('.admin-school-sketch').evaluate(e=>{
    const box=e.getBoundingClientRect(),image=e.querySelector('img').getBoundingClientRect(),text=e.querySelector('p').getBoundingClientRect();
    return Math.abs((image.left+image.right)/2-(box.left+box.right)/2)<1&&Math.abs((text.left+text.right)/2-(box.left+box.right)/2)<1&&getComputedStyle(e.querySelector('p')).textAlign==='center';
  }),'Sketch and slogan must be centered');
  const originalChartWidth=await page.locator('#attendance-chart').evaluate(e=>e.getBoundingClientRect().width);
  const originalChartData=await page.locator('#attendance-chart').evaluate(e=>Chart.getChart(e).data.datasets.map(d=>d.data));
  await page.locator('[data-admin-sidebar-toggle]').click();
  assert.ok(await page.locator('body').evaluate(e=>e.classList.contains('admin-sidebar-hidden')));
  assert.ok(await page.locator('.app-sidebar').isHidden());
  await page.waitForFunction(width=>document.querySelector('#attendance-chart').getBoundingClientRect().width>width+50,originalChartWidth);
  assert.deepEqual(await page.locator('#attendance-chart').evaluate(e=>Chart.getChart(e).data.datasets.map(d=>d.data)),originalChartData,'Hiding the sidebar must not change graph data');
  await page.screenshot({path:resolve(output,'sidebar-hidden-desktop.png')});
  await page.locator('[data-admin-sidebar-toggle]').click();
  await page.locator('#sidebar-menu-search').fill('rapor sts');
  assert.ok(await page.locator('[data-sidebar-item]').filter({hasText:'Rapor STS'}).isVisible());
  await page.locator('#sidebar-menu-search').fill('');
  await page.locator('[data-notification-menu]>summary').click();
  assert.ok(await page.locator('[data-notification-menu]').evaluate(e=>e.open));
  await page.keyboard.press('Escape');
  await page.locator('.account-trigger').click();
  assert.ok(await page.locator('.account-menu-dropdown').evaluate(e=>e.open));
  await page.keyboard.press('Escape');
  assert.ok((await page.locator('#attendance-detail').getAttribute('href')).includes('periode=rentang'));

  await page.setViewportSize({width:390,height:844});
  await page.goto('http://localhost/_dashboard-qa/populated');
  await page.waitForFunction(()=>!!window.Chart?.getChart('attendance-chart'));
  await page.locator('[data-admin-sidebar-toggle]').click();
  assert.ok(await page.locator('.app-content').evaluate(e=>e.inert));
  await page.screenshot({path:resolve(output,'menu-mobile.png')});
  await page.keyboard.press('Shift+Tab');
  assert.ok(await page.evaluate(()=>document.querySelector('.app-sidebar').contains(document.activeElement)));
  await page.keyboard.press('Escape');
  assert.ok(await page.locator('.app-sidebar').evaluate(e=>e.inert));
  assert.ok(await page.locator('.app-content').evaluate(e=>!e.inert));
  await page.locator('#chart-table').click();
  await page.screenshot({path:resolve(output,'table-mobile.png')});
  await page.keyboard.press('Escape');
  await reports.locator('[data-stat-table]').click();
  await page.screenshot({path:resolve(output,'reports-table-mobile.png')});
  await page.keyboard.press('Escape');
  await page.locator('.page-footer').scrollIntoViewIfNeeded();
  assert.ok(await page.locator('.page-footer').evaluate(e=>document.documentElement.scrollHeight-(e.getBoundingClientRect().bottom+scrollY)<=2),'No blank area after mobile footer');
  await page.setViewportSize({width:320,height:740});
  await page.goto('http://localhost/_dashboard-qa/populated');
  await page.waitForFunction(()=>!!window.Chart?.getChart('attendance-chart'));
  await page.locator('.stat-copy strong').evaluateAll(elements=>elements.forEach(e=>e.textContent='12.000'));
  assert.ok(await page.locator('.school-stats').evaluate(element=>element.scrollWidth<=element.clientWidth+1),'Large counts must fit');
  assert.ok(await page.locator('.stat-copy strong').evaluateAll(elements=>elements.every(e=>e.scrollWidth<=e.clientWidth+1)),'Large counts must fit inside each tile');
  assert.deepEqual(errors,[]);assert.deepEqual(missing,[]);assert.deepEqual(writes,[]);
  await writeFile(resolve(output,'audit.json'),JSON.stringify({checks,errors,missing,writes},null,2));
  console.log(JSON.stringify({states:checks.length,viewports:9,fixtures:4,interactions:'passed',errors,missing,writes}));
}finally{await browser.close();}
