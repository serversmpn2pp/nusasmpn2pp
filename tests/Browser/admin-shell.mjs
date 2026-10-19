import assert from 'node:assert/strict';
import {readFile, mkdir, writeFile} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const fixtures = resolve('storage/framework/testing/admin-shell');
const output = resolve('storage/logs/admin-shell-qa');
const publicRoot = resolve('public');
const manifest = JSON.parse(await readFile(resolve(fixtures,'manifest.json'),'utf8'));
const browser = await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const errors=[], missing=[], writes=[], checks=[];
await mkdir(output,{recursive:true});
try {
  const context=await browser.newContext({serviceWorkers:'block',reducedMotion:'reduce'});
  const page=await context.newPage();
  page.on('pageerror',error=>errors.push(error.message));
  await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    assert.ok(['localhost','127.0.0.1'].includes(url.hostname),`Unexpected external origin ${url.hostname}`);
    if(request.method()!=='GET'){writes.push(url.pathname);return route.fulfill({status:405,body:''});}
    if(manifest[url.pathname])return route.fulfill({contentType:'text/html',body:await readFile(resolve(fixtures,manifest[url.pathname]+'.html'),'utf8')});
    if(url.pathname==='/notifikasi/ringkasan')return route.fulfill({json:{jumlah_belum_dibaca:0,notifikasi:[]}});
    const path=resolve(publicRoot,'.'+decodeURIComponent(url.pathname));
    assert.ok(path.startsWith(publicRoot+sep),'Asset escaped public root');
    try{return route.fulfill({body:await readFile(path),contentType:({'.css':'text/css','.js':'text/javascript','.png':'image/png','.webp':'image/webp','.svg':'image/svg+xml','.webmanifest':'application/manifest+json','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'});}
    catch{missing.push(url.pathname);return route.fulfill({status:404,body:''});}
  });
  async function ready(path){
    await page.goto('http://localhost'+path);
    await page.waitForFunction(()=>!!document.querySelector('[data-admin-sidebar-toggle] svg.lucide'));
  }
  async function selectMode(mode){
    const options=page.locator('[data-admin-sidebar-options]');
    if(!await options.evaluate(e=>e.open))await options.locator('summary').click();
    const input=options.locator(`[data-admin-sidebar-mode][value="${mode}"]`);
    if(await input.isChecked())await options.locator('summary').click();
    else await input.check();
  }
  for(const [width,height] of [[320,740],[360,740],[390,844],[768,1024],[1024,768],[1280,900],[1440,960],[1920,1080],[844,390]]){
    await page.setViewportSize({width,height});
    if(page.url()!=='about:blank')await page.evaluate(()=>localStorage.clear());
    for(const [path,name] of Object.entries(manifest)){
      await ready(path);
      const result=await page.evaluate(()=>{
        const sidebar=document.querySelector('.app-sidebar'),header=document.querySelector('.app-topbar');
        const css=getComputedStyle(sidebar),crumb=header.querySelector('.admin-breadcrumb');
        const bounds=header.getBoundingClientRect();
        return {background:css.backgroundColor,border:css.borderRightColor,font:css.fontFamily,
          width:sidebar.getBoundingClientRect().width,rootWidth:document.documentElement.scrollWidth,rootHeight:document.documentElement.scrollHeight,
          header:{left:bounds.left,right:bounds.right},title:crumb.querySelector('strong').textContent,
          titleWidth:crumb.clientWidth,actualTitleWidth:crumb.scrollWidth,
          icons:[...sidebar.querySelectorAll('[data-lucide]')].length,images:[...sidebar.querySelectorAll('img')].every(e=>e.complete&&e.naturalWidth>0),
          smallText:Object.entries({'.sidebar-section-title':15,'.sidebar-link':14,'.sidebar-subgroup-title':13,'.admin-breadcrumb':14}).flatMap(([selector,min])=>[...document.querySelectorAll(selector)].filter(e=>parseFloat(getComputedStyle(e).fontSize)<min).map(e=>({selector,size:getComputedStyle(e).fontSize}))),
          chartAsset:[...document.scripts].some(e=>e.src.includes('chart.umd')),contentInert:document.querySelector('.app-content').inert};
      });
      assert.equal(result.background,'rgb(255, 255, 255)',`Old sidebar on ${path}`);
      assert.equal(result.border,'rgb(220, 229, 235)');
      assert.ok(result.font.includes('Segoe UI'));
      assert.deepEqual(result.smallText,[],`Small navigation text ${path} ${width}`);
      assert.ok(result.rootWidth<=width+1,`Root overflow ${width} ${path}: ${result.rootWidth}`);
      assert.ok(result.header.left>=0&&result.header.right<=width+1,`Header clipped ${path}`);
      assert.ok(result.title.trim().length>0&&result.titleWidth>=0,'Missing breadcrumb');
      assert.ok(result.images,`Missing sidebar asset ${path}`);
      assert.ok(result.icons>0,`Missing sidebar icons ${path}`);
      assert.equal(await page.locator('#admin-sidebar .sidebar-foot').count(),0);
      assert.equal(await page.locator('.app-topbar .account-menu-dropdown').count(),1);
      assert.equal(result.chartAsset,path==='/beranda',`Chart library loaded outside dashboard: ${path}`);
      assert.equal(result.contentInert,false);
      if(width>760){
        assert.ok(result.rootHeight<=height+1,`Outer scrollbar ${path}`);
        const previousWidth=result.width;
        await page.locator('[data-admin-sidebar-toggle]').click();
        assert.ok(await page.locator('.app-sidebar').isHidden(),`Sidebar must hide completely ${path}`);
        assert.ok(await page.locator('.app-sidebar').evaluate(e=>e.inert));
        assert.equal(await page.locator('[data-admin-sidebar-toggle]').getAttribute('aria-label'),'Tampilkan sidebar');
        assert.equal(await page.locator('[data-admin-sidebar-toggle] svg:visible').count(),1);
        assert.ok(await page.locator('[data-admin-sidebar-show-icon]').isVisible());
        assert.ok(await page.locator('.app-content').evaluate(e=>Math.abs(e.getBoundingClientRect().left)<1&&e.clientWidth>=innerWidth-1),'Hidden sidebar must release its full width');
        if(width===1440&&name==='tahun-pelajaran-index')await page.screenshot({path:resolve(output,'sidebar-hidden-desktop.png')});
        await page.locator('[data-admin-sidebar-toggle]').click();
        assert.ok(await page.locator('.app-sidebar').isVisible());
        assert.equal(Math.round((await page.locator('.app-sidebar').boundingBox()).width),Math.round(previousWidth),'Show must restore the previous mode');
        checks.push({width,height,path,mode:'hidden',passed:true});
      }
      if(width<=760){
        await page.locator('[data-admin-sidebar-toggle]').click();
        await page.waitForFunction(()=>Math.abs(document.querySelector('.app-sidebar').getBoundingClientRect().left)<1);
        assert.equal(await page.locator('.app-sidebar').getAttribute('aria-hidden'),'false');
        assert.ok(await page.locator('.app-content').evaluate(e=>e.inert));
        assert.ok(await page.locator('.sidebar-nav').evaluate(e=>{
          const nav=e.getBoundingClientRect(),side=e.closest('.app-sidebar').getBoundingClientRect();
          return nav.height>=innerHeight-190&&Math.abs(nav.bottom-side.bottom)<2;
        }),'Mobile navigation should use the space down to the bottom edge');
        await page.keyboard.press('Shift+Tab');
        assert.ok(await page.evaluate(()=>document.querySelector('.app-sidebar').contains(document.activeElement)),'Focus escaped mobile drawer');
        await page.keyboard.press('Escape');
        assert.ok(await page.locator('.app-sidebar').evaluate(e=>e.inert));
        assert.ok(await page.locator('.app-content').evaluate(e=>!e.inert));
      }
      checks.push({width,height,path,passed:true});
      if([390,1440].includes(width)&&['tahun-pelajaran-index','pegawai-create','persuratan-index','input-nilai-index'].includes(name)){
        await page.screenshot({path:resolve(output,`${name}-${width}.png`),fullPage:width<=760});
      }
    }
  }
  await page.setViewportSize({width:1440,height:960});
  await ready('/beranda');
  await selectMode('collapsed');
  assert.ok(await page.locator('body').evaluate(e=>e.classList.contains('admin-sidebar-collapsed')));
  await page.locator('#admin-sidebar .admin-home-link').click();
  await page.waitForLoadState('load');
  await page.waitForFunction(()=>document.body.classList.contains('admin-sidebar-collapsed'));
  await page.keyboard.press('Control+k');
  await page.locator('#sidebar-menu-search').fill('tahun pelajaran');
  assert.ok(await page.locator('#sidebar-menu-search').evaluate(e=>e===document.activeElement));
  await page.locator('[data-sidebar-item]').filter({hasText:'Tahun Pelajaran'}).click();
  await page.waitForURL('**/tahun-pelajaran');
  assert.equal(await page.locator('.admin-breadcrumb strong').textContent(),'Tahun Pelajaran');
  assert.equal(await page.locator('#admin-sidebar .admin-home-link').getAttribute('aria-current'),null);
  assert.equal(await page.locator('#admin-sidebar [aria-current="page"]').count(),1);
  assert.ok(await page.locator('#admin-sidebar [aria-current="page"]').getAttribute('href').then(v=>v.endsWith('/tahun-pelajaran')));
  const drawerState=await page.locator('body').evaluate(e=>e.classList.contains('admin-sidebar-expanded'));
  assert.ok(drawerState,'Expanded preference must persist after navigation');
  await page.locator('#sidebar-menu-search').fill('surat');
  await page.locator('[data-sidebar-item]').filter({hasText:'Arsip Surat',exact:true}).click();
  await page.waitForURL('**/persuratan');
  assert.match(await page.locator('.admin-breadcrumb strong').textContent(),/surat/i);
  await page.locator('[data-admin-sidebar-toggle]').click();
  await page.reload();
  await page.waitForFunction(()=>document.body.classList.contains('admin-sidebar-hidden'));
  await page.locator('[data-admin-sidebar-toggle]').click();
  await page.locator('#sidebar-menu-search').fill('tidak-ada-menu-ini');
  assert.ok(await page.locator('[data-sidebar-search-empty]').isVisible());
  await page.locator('[data-sidebar-search-clear]').click();
  assert.ok(await page.locator('[data-sidebar-section-id="persuratan"]').evaluate(e=>e.open));
  await page.locator('[data-notification-menu]>summary').click();
  assert.ok(await page.locator('[data-notification-menu]').evaluate(e=>e.open));
  await page.keyboard.press('Escape');
  await page.locator('.account-trigger').click();
  assert.ok(await page.locator('.account-menu-dropdown').evaluate(e=>e.open));
  await page.keyboard.press('Escape');
  await selectMode('collapsed');
  await page.locator('[data-admin-sidebar-toggle]').click();
  await page.reload();
  await page.waitForFunction(()=>document.body.classList.contains('admin-sidebar-hidden'));
  await page.locator('[data-admin-sidebar-toggle]').click();
  assert.ok(await page.locator('body').evaluate(e=>e.classList.contains('admin-sidebar-collapsed')),'Hidden preference must remember compact mode across reloads');
  assert.equal(Math.round((await page.locator('.app-sidebar').boundingBox()).width),76);
  await selectMode('expanded');
  await page.locator('[data-admin-sidebar-options]>summary').click();
  assert.ok(await page.locator('#admin-tooltip').isHidden(),'Tooltip must not cover the display options');
  await page.screenshot({path:resolve(output,'sidebar-options-desktop.png')});
  await page.keyboard.press('Escape');
  assert.ok(await page.locator('[data-admin-sidebar-options]').evaluate(e=>!e.open));
  await selectMode('hidden');
  assert.ok(await page.locator('.app-sidebar').isHidden());
  await page.setViewportSize({width:390,height:844});
  await page.locator('[data-admin-sidebar-toggle]').click();
  await page.waitForFunction(()=>Math.abs(document.querySelector('.app-sidebar').getBoundingClientRect().left)<1);
  assert.ok(await page.locator('.app-content').evaluate(e=>e.inert),'Mobile drawer must still work when the saved desktop mode is hidden');
  await page.keyboard.press('Escape');
  await page.setViewportSize({width:1440,height:960});
  assert.ok(await page.locator('.app-sidebar').isHidden(),'Desktop hidden preference must survive a mobile viewport');
  await page.keyboard.press('Control+k');
  assert.ok(await page.locator('#sidebar-menu-search').evaluate(e=>e===document.activeElement),'Search must restore a fully hidden sidebar');
  await page.setViewportSize({width:390,height:844});
  await ready('/tahun-pelajaran');
  await page.keyboard.press('Control+k');
  assert.ok(await page.locator('.app-content').evaluate(e=>e.inert));
  assert.ok(await page.locator('#sidebar-menu-search').evaluate(e=>e===document.activeElement));
  await page.keyboard.press('Escape');
  await page.locator('[data-admin-sidebar-toggle]').click();
  await page.waitForFunction(()=>Math.abs(document.querySelector('.app-sidebar').getBoundingClientRect().left)<1);
  await page.screenshot({path:resolve(output,'drawer-mobile.png')});
  const lastSection=page.locator('#admin-sidebar details[data-sidebar-section]').last();
  if(!await lastSection.evaluate(e=>e.open))await lastSection.locator(':scope > summary').click();
  const lastMenu=lastSection.locator('.sidebar-link').last();
  await lastMenu.waitFor({state:'visible'});
  await lastMenu.scrollIntoViewIfNeeded();
  assert.ok(await lastMenu.evaluate(e=>{
    const item=e.getBoundingClientRect(),nav=e.closest('.sidebar-nav').getBoundingClientRect();
    return item.top>=nav.top-1&&item.bottom<=nav.bottom+1;
  }),'The final menu item must remain reachable on a phone');
  await page.screenshot({path:resolve(output,'drawer-mobile-bottom.png')});
  await page.locator('.sidebar-backdrop').click({position:{x:350,y:500}});
  assert.ok(await page.locator('.app-sidebar').evaluate(e=>e.inert));
  await page.locator('.content-shell').evaluate(e=>{
    const spacer=document.createElement('div');spacer.style.height='1600px';spacer.dataset.qaSpacer='';e.append(spacer);
  });
  await page.evaluate(()=>scrollTo(0,document.documentElement.scrollHeight));
  assert.ok(await page.locator('.content-shell').evaluate(e=>document.documentElement.scrollHeight-(e.getBoundingClientRect().bottom+scrollY)<2),'Blank space after content');
  await page.addInitScript(()=>Object.defineProperty(window,'localStorage',{get(){throw new Error('Storage disabled for QA');}}));
  await page.setViewportSize({width:1440,height:960});
  await ready('/tahun-pelajaran');
  await page.locator('[data-admin-sidebar-toggle]').click();
  assert.ok(await page.locator('body').evaluate(e=>e.classList.contains('admin-sidebar-hidden')),'Sidebar must work without storage');
  await page.keyboard.press('Control+k');
  await page.locator('#sidebar-menu-search').fill('surat');
  assert.ok(await page.locator('[data-sidebar-item]').filter({hasText:'Arsip Surat'}).isVisible());
  assert.deepEqual(errors,[]);assert.deepEqual(missing,[]);assert.deepEqual(writes,[]);
  await writeFile(resolve(output,'audit.json'),JSON.stringify({checks,errors,missing,writes},null,2));
  console.log(JSON.stringify({pages:Object.keys(manifest).length,viewports:9,states:checks.length,interactions:'passed',errors,missing,writes}));
} finally { await browser.close(); }
