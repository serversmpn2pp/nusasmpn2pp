import {readFile, mkdir, writeFile} from 'node:fs/promises';
import path from 'node:path';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';

const {chromium} = await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE).href);
const root = process.cwd(), folder = path.join(root, 'storage/framework/testing/monitor-tv');
const output = path.join(root, 'storage/logs/monitor-tv-rincian');
await mkdir(output, {recursive:true});
const html = await readFile(path.join(folder,'index.html'),'utf8');
const fixture = JSON.parse(await readFile(path.join(folder,'data.json'),'utf8'));
const details = JSON.parse(await readFile(path.join(folder,'rincian.json'),'utf8'));
const browser = await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const results = [];
try {
    for (const [width,height] of [[1920,1080],[1280,720],[1024,768],[390,844],[320,640]]) {
        const context = await browser.newContext({viewport:{width,height}}), page = await context.newPage();
        await page.clock.install({time:new Date(fixture.diperbarui_pada)});
        const errors = []; page.on('pageerror',error => errors.push(error.message));
        let detailCalls = 0, summaryCalls = 0, detailStatus = 200, summaryStatus = 200, active = 0, peak = 0;
        let pupils = Array.from({length:45},(_,i) => ({id:`presensi-${i}`,nama:`Siswa Contoh ${String(i + 1).padStart(2,'0')} Nama Panjang`,kelas:i % 2 ? 'VIII.B' : 'VIII.A',kelas_id:details.belum.kelas[i % 2].id,status:'Belum tercatat',waktu:null,kategori:'belum',menit:0,terlambat:false,foto_url:i === 0 ? 'http://localhost/monitoring-siswa/foto/999' : null}));
        await page.route('http://localhost/**',async route => {
            const url = new URL(route.request().url());
            if (url.pathname === '/monitoring-siswa') return route.fulfill({contentType:'text/html',body:html});
            if (url.pathname === '/monitoring-siswa/data') {
                summaryCalls++;
                return route.fulfill({status:summaryStatus,contentType:'application/json',body:JSON.stringify({...fixture,diperbarui_pada:await page.evaluate(() => new Date().toISOString())})});
            }
            if (url.pathname === '/monitoring-siswa/rincian') {
                detailCalls++; active++; peak = Math.max(peak,active);
                const jenis = url.searchParams.get('jenis');
                const base = structuredClone(details[jenis]);
                let rows = jenis === 'belum' || jenis === 'dipantau' ? structuredClone(pupils) : base.items;
                if (!rows.length) rows = [{...pupils[0],status:jenis.startsWith('poin') ? 'Saldo poin resmi' : 'Hadir',poin:jenis.startsWith('poin') ? 15 : undefined}];
                if (jenis === 'laporan') rows = [...rows,{id:'privat',nama:'Pembinaan siswa',kelas:'Identitas dibatasi',status:'Belum diverifikasi',waktu:'09:00:01',jenis:'Pembinaan'}];
                const kelas = url.searchParams.get('kelas_id'), q = url.searchParams.get('q') || '';
                rows = rows.filter(row => (!kelas || String(row.kelas_id) === kelas) && row.nama.toLowerCase().includes(q.toLowerCase()));
                if (jenis === 'ibadah') {
                    const status = url.searchParams.get('status_ibadah'), activity = url.searchParams.get('kegiatan_id');
                    rows = rows.filter(row => (!status || row.status_ibadah === status) && (!activity || String(row.kegiatan_id) === activity));
                }
                const last = Math.max(1,Math.ceil(rows.length / 20)), current = Math.min(Number(url.searchParams.get('page') || 1),last);
                const body = {...base,diperbarui_pada:await page.evaluate(() => new Date().toISOString()),items:rows.slice((current-1)*20,current*20),pagination:{page:current,last_page:last,total:rows.length,per_page:20}};
                await route.fulfill({status:detailStatus,contentType:'application/json',body:JSON.stringify(body)}); active--; return;
            }
            if (url.pathname === '/monitoring-siswa/foto/999') return route.fulfill({contentType:'image/png',body:await readFile(path.join(root,'public/images/logo-nusa.png'))});
            const file = path.resolve(root,'public','.' + url.pathname);
            if (!file.startsWith(path.join(root,'public') + path.sep)) return route.abort();
            try {
                const types = {'.js':'application/javascript','.css':'text/css','.png':'image/png','.woff2':'font/woff2'};
                return route.fulfill({body:await readFile(file),contentType:types[path.extname(file)] || 'application/octet-stream'});
            } catch { return route.fulfill({status:404,body:''}); }
        });
        await page.goto('http://localhost/monitoring-siswa');
        await page.waitForFunction(() => document.getElementById('connection').textContent === 'Terhubung');
        const initialUrl = page.url();
        await page.locator('[data-detail="belum"]').click();
        await page.waitForFunction(() => document.getElementById('detail-count').textContent.includes('45 siswa'));
        assert.equal(await page.locator('#detail-dialog').evaluate(el => el.open),true);
        assert.equal(await page.locator('#detail-rows tr').count(),20);
        assert.equal(await page.locator('#detail-page').textContent(),'1 / 3');
        assert.equal(await page.locator('#detail-rows img').evaluate(img => img.complete && img.naturalWidth > 0),true);
        await page.screenshot({path:path.join(output,`${width}x${height}-belum.png`)});
        const geometry = await page.locator('#detail-dialog').evaluate(el => {
            const r = el.getBoundingClientRect(); return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,sw:el.scrollWidth,cw:el.clientWidth};
        });
        assert.ok(geometry.left >= 0 && geometry.top >= 0 && geometry.right <= width && geometry.bottom <= height);
        assert.ok(geometry.sw <= geometry.cw + 1,`Dialog overflow ${width}`);
        assert.ok(await page.locator('#detail-next').isVisible());
        await page.locator('#detail-scroll').evaluate(el => { el.scrollTop = 250; });
        const scroll = await page.locator('#detail-scroll').evaluate(el => el.scrollTop);
        await page.locator('#detail-search').focus();
        const beforeSummary = summaryCalls, beforeDetails = detailCalls;
        await page.clock.fastForward(5000);
        await page.waitForFunction(() => document.getElementById('detail-scroll').getAttribute('aria-busy') === 'false');
        assert.ok(summaryCalls > beforeSummary && detailCalls > beforeDetails);
        assert.equal(await page.locator('#detail-scroll').evaluate(el => el.scrollTop),scroll);
        assert.equal(await page.locator('#detail-search').evaluate(el => el === document.activeElement),true);
        await page.locator('#detail-next').click();
        await page.waitForFunction(() => document.getElementById('detail-page').textContent === '2 / 3');
        await page.clock.fastForward(5000);
        await page.waitForFunction(() => document.getElementById('detail-scroll').getAttribute('aria-busy') === 'false');
        assert.equal(await page.locator('#detail-page').textContent(),'2 / 3');
        await page.locator('#detail-class').selectOption(String(details.belum.kelas[0].id));
        await page.waitForFunction(() => document.getElementById('detail-count').textContent.includes('23 siswa'));
        assert.equal(await page.locator('#detail-page').textContent(),'1 / 2');
        await page.locator('#detail-search').fill('Siswa Contoh 01');
        await page.clock.fastForward(300);
        await page.waitForFunction(() => document.getElementById('detail-rows').children.length === 1);
        assert.ok((await page.locator('#detail-count').textContent()).includes('1 siswa'));
        await page.locator('#detail-search').fill('<script>');
        await page.clock.fastForward(300);
        await page.waitForFunction(() => !document.getElementById('detail-empty').hidden);
        assert.equal(await page.locator('#detail-rows tr').count(),0);
        await page.locator('#detail-reset').click();
        await page.waitForFunction(() => document.getElementById('detail-count').textContent.includes('45 siswa'));
        detailStatus = 500;
        await page.clock.fastForward(5000);
        await page.waitForFunction(() => !document.getElementById('detail-message').hidden);
        assert.equal(await page.locator('#detail-rows tr').count(),20);
        detailStatus = 200;
        pupils.shift();
        await page.clock.fastForward(5000);
        await page.waitForFunction(() => document.getElementById('detail-count').textContent.includes('44 siswa'));
        assert.equal(await page.locator('#detail-dialog').evaluate(el => el.open),true);
        assert.equal(page.url(),initialUrl);
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#detail-dialog').evaluate(el => el.open),false);
        assert.equal(await page.locator('[data-detail="belum"]').evaluate(el => el === document.activeElement),true);
        for (const jenis of ['hadir','izin','sakit','alfa','terlambat','laporan','ibadah','poin','poin_tambah','poin_kurang','dipantau']) {
            await page.locator(`[data-detail="${jenis}"]`).first().click();
            await page.waitForFunction(() => document.getElementById('detail-scroll').getAttribute('aria-busy') === 'false');
            assert.equal(await page.locator('#detail-dialog').evaluate(el => el.open),true);
            assert.ok((await page.locator('#detail-title').textContent()).length > 5);
            if (jenis === 'ibadah') {
                assert.equal(await page.locator('#detail-worship-filter').isVisible(),true);
                await page.locator('#detail-worship').selectOption('belum');
                await page.waitForFunction(() => document.getElementById('detail-rows').textContent.includes('Belum tercatat'));
            }
            if (jenis === 'laporan') assert.ok((await page.locator('#detail-rows').textContent()).includes('Identitas dibatasi'));
            await page.locator('#detail-close').click();
        }
        await page.locator('[data-detail="belum"]').click();
        await page.waitForFunction(() => document.getElementById('detail-count').textContent.includes('44 siswa'));
        detailStatus = 403;
        await page.clock.fastForward(5000);
        await page.waitForFunction(() => document.getElementById('connection').textContent === 'Akses dicabut');
        assert.equal(await page.locator('#detail-dialog').evaluate(el => el.open),false);
        assert.equal(await page.locator('#detail-rows tr').count(),0);
        assert.equal(await page.locator('#kpi-hadir').textContent(),'-');
        const calls = [detailCalls,summaryCalls];
        await page.clock.fastForward(15000);
        assert.deepEqual([detailCalls,summaryCalls],calls);
        assert.equal(peak,1);
        assert.deepEqual(errors,[]);
        results.push({width,height,detailCalls,summaryCalls,geometry,errors});
        await context.close();
    }
} finally { await browser.close(); }
await writeFile(path.join(output,'results.json'),JSON.stringify(results,null,2));
console.log(JSON.stringify({viewports:results.length,errors:0,results}));
