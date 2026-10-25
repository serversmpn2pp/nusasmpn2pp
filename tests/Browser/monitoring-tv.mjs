import {readFile, mkdir, writeFile} from 'node:fs/promises';
import path from 'node:path';
import {pathToFileURL} from 'node:url';
import nodeAssert from 'node:assert/strict';

let checkCount = 0;
const assert = new Proxy(nodeAssert, {get(target, key) {
    return (...args) => { checkCount++; return target[key](...args); };
}});

const {chromium} = await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE).href);
const root = process.cwd();
const folder = path.join(root, 'storage/framework/testing/monitor-tv');
const output = path.join(root, 'storage/logs/monitor-tv');
await mkdir(output, {recursive: true});
const html = await readFile(path.join(folder, 'index.html'), 'utf8');
const fixture = JSON.parse(await readFile(path.join(folder, 'data.json'), 'utf8'));
const browser = await chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const results = [];
try {
    for (const [width, height] of [[1920,1080],[1280,720],[1366,768],[1024,768],[390,844],[320,568]]) {
        const checksBefore = checkCount;
        const context = await browser.newContext({viewport: {width, height}});
        const page = await context.newPage();
        await page.clock.install({time: new Date(fixture.diperbarui_pada)});
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        let status = 200, calls = 0, inflight = 0, peak = 0;
        let payload = structuredClone(fixture);
        payload.terlambat = Array.from({length:8},(_,i)=>({nama:`Siswa Keterlambatan Uji Nama Panjang ${i}`,kelas:'IX.A Bilingual',menit:i}));
        payload.ibadah = [fixture.ibadah[0], {...fixture.ibadah[0],nama:'Salat Asar'}];
        await page.route('http://localhost/**', async route => {
            const url = new URL(route.request().url());
            if (url.pathname === '/monitoring-siswa') return route.fulfill({contentType: 'text/html', body: html});
            if (url.pathname === '/monitoring-siswa/data') {
                calls++; inflight++; peak = Math.max(peak, inflight);
                const body = status === 200 ? JSON.stringify(payload) : '{}';
                await route.fulfill({status, contentType: 'application/json', body});
                inflight--;
                return;
            }
            const file = path.resolve(root, 'public', '.' + url.pathname);
            if (!file.startsWith(path.join(root, 'public') + path.sep)) return route.abort();
            try {
                const ext = path.extname(file), types = {'.js':'application/javascript','.css':'text/css','.png':'image/png','.woff2':'font/woff2'};
                return route.fulfill({body: await readFile(file), contentType: types[ext] || 'application/octet-stream'});
            } catch { return route.fulfill({status:404, body:''}); }
        });
        await page.goto('http://localhost/monitoring-siswa');
        await page.waitForFunction(() => document.getElementById('connection').textContent === 'Terhubung');
        assert.equal(await page.locator('.app-sidebar').count(), 0);
        assert.equal(await page.locator('#kpi-hadir').textContent(), String(payload.ringkasan.hadir));
        assert.ok((await page.locator('#feed').textContent()).includes('Belum diverifikasi'));
        const geometry = await page.evaluate(() => ({width: innerWidth, height: innerHeight, sw: document.documentElement.scrollWidth, sh: document.documentElement.scrollHeight, boxes: [...document.querySelectorAll('.tv-screen,.tv-header,.kpis,.tv-grid,.chart-section,.feed-section,.lower-section,.chart-area')].map(e=>({cls:e.className,height:e.getBoundingClientRect().height}))}));
        await page.screenshot({path: path.join(output, `${width}x${height}.png`), fullPage: true});
        assert.ok(geometry.sw <= width + 1, `Horizontal overflow ${width}: ${JSON.stringify(geometry)}`);
        if (width >= 1280) assert.ok(geometry.sh <= height + 1, `TV vertical overflow ${width}: ${JSON.stringify(geometry)}`);
        const overlaps = await page.evaluate(() => ['feed','worship','late-list'].flatMap(id => {
            const box = document.getElementById(id).getBoundingClientRect();
            return [...document.getElementById(id).children].filter(e=>e.getBoundingClientRect().bottom > box.bottom + 1).map(e=>`${id}: ${e.textContent}`);
        }));
        assert.deepEqual(overlaps, [], `Overlapping panels ${width}`);
        for (const id of ['attendance-chart','reports-chart']) {
            assert.ok(await page.locator(`#${id}`).evaluate(c => {
                const data = c.getContext('2d').getImageData(0,0,c.width,c.height).data;
                let colored = 0;
                for (let i = 3; i < data.length; i += 4) if (data[i] > 0) colored++;
                return colored > 100;
            }), `Blank chart ${id}`);
        }
        await page.screenshot({path: path.join(output, `${width}x${height}.png`), fullPage: true});
        await page.locator('[data-period="month"]').click();
        assert.equal(await page.locator('#report-period').textContent(), 'Bulan ini');
        await page.locator('[data-period="week"]').click();
        assert.equal(await page.locator('#report-period').textContent(), 'Minggu ini');
        await page.locator('[data-metric="kelas"]').click();
        assert.equal(await page.locator('#secondary-title').textContent(), 'Presensi per kelas');
        assert.equal(await page.locator('#report-period').textContent(), 'Hari ini');
        await page.locator('[data-metric="laporan"]').click();
        assert.equal(await page.locator('#secondary-title').textContent(), 'Laporan masuk');
        const before = calls;
        payload.ringkasan.hadir = 5;
        await page.clock.fastForward(5000);
        await page.waitForFunction(() => document.getElementById('kpi-hadir').textContent === '5');
        assert.ok(calls > before, 'Must poll after five seconds');
        assert.equal(peak, 1, 'No overlapping requests');
        status = 500;
        await page.clock.fastForward(5000);
        await page.waitForFunction(() => document.getElementById('connection').classList.contains('offline'));
        assert.equal(await page.locator('#kpi-hadir').textContent(), '5', 'Last valid data is retained offline');
        assert.ok((await page.locator('#notice').textContent()).includes('terakhir'));
        status = 200;
        payload.diperbarui_pada = await page.evaluate(() => new Date().toISOString());
        await page.clock.fastForward(5000);
        await page.waitForFunction(() => document.getElementById('connection').textContent === 'Terhubung');
        payload.laporan_terbaru.unshift({id:'new-report',nama:'LAPORAN BARU UJI',kelas:'IX.A',jenis:'Laporan Kejadian',status:'Belum diverifikasi',resmi:false,waktu:'09:31:01'});
        await page.clock.fastForward(5000);
        await page.waitForFunction(() => document.getElementById('feed').textContent.includes('LAPORAN BARU UJI'));
        status = 401;
        await page.clock.fastForward(5000);
        await page.waitForFunction(() => document.getElementById('connection').textContent === 'Akses dicabut');
        assert.ok(!(await page.locator('#feed').textContent()).includes('LAPORAN BARU UJI'));
        assert.equal(await page.locator('#kpi-hadir').textContent(), '-');
        const afterRevoke = calls;
        await page.clock.fastForward(10000);
        assert.equal(calls, afterRevoke, 'Stop polling after revoked access');
        assert.deepEqual(errors, []);
        results.push({width,height,checks:checkCount - checksBefore,geometry,calls});
        await context.close();
    }
} finally { await browser.close(); }
await writeFile(path.join(output, 'results.json'), JSON.stringify(results,null,2));
console.log(JSON.stringify({checks:results.reduce((s,r)=>s+r.checks,0),viewports:results.length,errors:0,results}));
