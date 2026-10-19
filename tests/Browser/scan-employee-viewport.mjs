import assert from 'node:assert/strict';
import {readFile, mkdir, writeFile} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const fixtures = resolve('storage/framework/testing/role-theme');
const publicRoot = resolve('public');
const output = resolve('storage/logs/scan-employee-viewport');
const pages = Object.fromEntries(Object.entries(JSON.parse(await readFile(resolve(fixtures, 'manifest-scan.json'), 'utf8'))).filter(([path]) => path.startsWith('/scan-audit/pegawai/')));
assert.equal(Object.keys(pages).length, 4, 'Capture TampilanLintasRoleTest with NUSA_CAPTURE_ROLE_THEME=1 first');
const browser = await chromium.launch({headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const issues = [], errors = [], checks = [];
let scanState = 'success';
await mkdir(output, {recursive:true});
try {
    // 1280x720 CSS pixels at DPR 1.5 represents 1920x1080 with Windows scaling at 150%.
    const context = await browser.newContext({serviceWorkers:'block', reducedMotion:'reduce', deviceScaleFactor:1.5});
    const page = await context.newPage();
    page.on('pageerror', e => errors.push(e.message));
    await page.route('**/*', async route => {
        const request = route.request(), url = new URL(request.url());
        if (!['localhost','127.0.0.1'].includes(url.hostname)) return route.abort();
        if (request.method() !== 'GET') {
            assert.equal(url.pathname, '/scan-absensi-pegawai');
            if (scanState === 'offline') return route.abort('internetdisconnected');
            return route.fulfill({json:{
                berhasil:scanState === 'success', status:scanState === 'duplicate' ? 'sudah_scan_masuk' : scanState === 'warning' ? 'di_luar_jadwal_masuk' : 'berhasil',
                pesan:'Presensi masuk berhasil dicatat. Selamat menjalankan tugas dan mendampingi pembelajaran.',
                jenis_scan:'masuk', waktu_server:'08:05:00', scanner_id:'SCANNER-KANTOR-UTAMA',
                pegawai:{nama_lengkap:'MUHAMMAD ABDURRAHMAN HARIANJA, S.Pd., M.Pd. DENGAN NAMA PANJANG', nip:'199211032019021001', inisial:'MA', foto_url:'/images/logo-nusa.png', jenis_pegawai:'Guru', jabatan:'Guru Mata Pelajaran'},
                absensi:{jam_masuk:'08:05:00', menit_terlambat:65},
            }});
        }
        if (pages[url.pathname]) return route.fulfill({contentType:'text/html', body:await readFile(resolve(fixtures, pages[url.pathname].file + '.html'), 'utf8')});
        const path = resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
        assert.ok(path.startsWith(publicRoot + sep));
        const type = {'.css':'text/css','.js':'text/javascript','.png':'image/png','.woff2':'font/woff2','.webp':'image/webp'}[extname(path)] || 'application/octet-stream';
        return route.fulfill({contentType:type, body:await readFile(path)});
    });
    async function inspect(label, width, height, fitSingle) {
        await page.evaluate(() => window.scrollTo(0, 0));
        const result = await page.evaluate(() => {
            const selectors = ['.brand','.office-side','.schedule-panel','#clockText','#scanInput','#resultCard','#employeePhoto','#resultName','#resultNip','#resultMessage','#resultMeta',...Array.from(document.querySelectorAll('.schedule-time-grid')).map((_, i) => `.schedule-item:nth-child(${i + 1}) .schedule-time-grid`)];
            const boxes = selectors.map(selector => {
                const e = document.querySelector(selector), r = e.getBoundingClientRect();
                let crop = null;
                for (let p = e.parentElement; p; p = p.parentElement) {
                    const style = getComputedStyle(p), bounds = p.getBoundingClientRect();
                    if (['hidden','clip'].includes(style.overflowY) && (r.bottom > bounds.bottom + 2 || r.top < bounds.top - 2)) crop = p.className || p.tagName;
                }
                return {selector,visible:!!e.getClientRects().length,x:r.x,width:r.width,height:r.height,right:r.right,bottom:r.bottom,crop,overflow:e.scrollWidth > e.clientWidth + 2};
            });
            return {width:document.documentElement.scrollWidth,height:document.documentElement.scrollHeight,scrollable:!['hidden','clip'].includes(getComputedStyle(document.documentElement).overflowY) && !['hidden','clip'].includes(getComputedStyle(document.body).overflowY),scheduleScroll:[...document.querySelectorAll('.office-side,.schedule-panel,.schedule-list')].filter(e => e.scrollHeight > e.clientHeight + 2 && ['auto','scroll','hidden','clip'].includes(getComputedStyle(e).overflowY)).map(e => e.className),boxes};
        });
        const invalid = result.boxes.filter(e => e.visible && (e.crop || e.overflow || e.x < -1 || e.right > width + 1));
        const core = result.boxes.filter(e => e.visible && ['.schedule-panel','#clockText','#scanInput','#resultCard'].includes(e.selector));
        const photo = result.boxes.find(e => e.selector === '#employeePhoto');
        const name = result.boxes.find(e => e.selector === '#resultName');
        const card = result.boxes.find(e => e.selector === '#resultCard');
        const photoInvalid = photo.width < (width <= 540 ? 158 : 134) || Math.abs(photo.width / photo.height - .75) > .02 || photo.bottom > card.bottom - 8 || (width > 540 && photo.right > name.x - 8) || (width >= 1920 && height > 800 && photo.width < 220);
        if (invalid.length || photoInvalid || result.width > width + 1 || result.scheduleScroll.length || (result.height > height + 2 && !result.scrollable) || (fitSingle && core.some(e => e.bottom > height + 1))) issues.push({label,width,height,invalid,photoInvalid,...result});
        checks.push({label,width,height,pageHeight:result.height});
    }
    const sizes = [[1280,720],[1280,640],[1280,600],[1024,768],[1024,680],[1366,658],[1440,900],[1920,1080],[2560,1440],[900,640],[820,614],[800,600],[768,1024],[640,480],[390,844],[320,568]];
    for (const [width,height] of sizes) {
        await page.setViewportSize({width,height});
        for (const [path,entry] of Object.entries(pages)) {
            await page.goto('http://localhost' + path);
            await page.evaluate(() => document.fonts.ready);
            const single = !path.endsWith('banyak-jadwal');
            await inspect(entry.file + '-idle', width, height, single && width >= 900 && height >= 640);
            if (path.endsWith('/aktif') && width === 1280 && height === 720) await page.screenshot({path:resolve(output,'idle-1280x720.png'),fullPage:true,scale:'css'});
            if (!single) {
                const details = page.locator('#scheduleList details');
                assert.equal(await details.count(), 3);
                assert.equal(await page.locator('#scheduleList details[open]').count(), 1);
                if (width === 1280 && height === 640) await page.screenshot({path:resolve(output,'schedules-collapsed-1280x640.png'),fullPage:true,scale:'css'});
                for (let i = 1; i < 3; i++) {
                    await details.nth(i).locator('summary').click();
                    assert.ok(await details.nth(i).evaluate(e => e.open));
                }
                await inspect(entry.file + '-expanded', width, height, false);
                if (width === 1280 && height === 640) await page.screenshot({path:resolve(output,'schedules-expanded-1280x640.png'),fullPage:true,scale:'css'});
            }
            scanState = 'success';
            await page.locator('#scanInput').fill('199211032019021001');
            await page.locator('#scanInput').press('Enter');
            await page.waitForFunction(() => document.querySelector('#resultCard').classList.contains('is-success'));
            await page.locator('#employeePhoto img').evaluate(e => e.decode());
            await inspect(entry.file + '-success', width, height, single && width >= 900 && height >= 640);
            assert.equal(await page.locator('#scanInput').inputValue(), '');
            assert.ok(await page.locator('#scanInput').evaluate(e => e === document.activeElement));
            await page.locator('#resultMeta').scrollIntoViewIfNeeded();
            assert.ok(await page.locator('#resultMeta').evaluate(e => e.getBoundingClientRect().bottom <= innerHeight + 1));
            if (path.endsWith('/aktif') && [320,1024,1280,1920].includes(width)) {
                await page.evaluate(() => window.scrollTo(0, 0));
                await page.screenshot({path:resolve(output,`success-${width}x${height}.png`),fullPage:true,scale:'css'});
            }
        }
    }
    await page.setViewportSize({width:1280,height:640});
    await page.goto('http://localhost/scan-audit/pegawai/aktif');
    for (const [i,state] of ['success','duplicate','warning','offline','success','success','success'].entries()) {
        scanState = state;
        await page.locator('#scanInput').fill('19921103201902100' + i);
        await page.locator('#scanInput').press('Enter');
        const expected = {success:'is-success',duplicate:'is-recorded',warning:'is-warning',offline:'is-error'}[state];
        await page.waitForFunction(expected => document.querySelector('#resultCard').classList.contains(expected), expected);
        await inspect('history-' + state, 1280, 640, true);
        await page.waitForTimeout(700);
    }
    assert.equal(await page.locator('.history-item').count(), 6);
    await page.screenshot({path:resolve(output,'history-1280x640.png'),fullPage:true,scale:'css'});
    for (const [width,height] of [[1920,1080],[1280,720],[1280,640],[1024,768],[820,614],[390,844]]) {
        await page.setViewportSize({width,height});
        await inspect('live-monitor-resize', width, height, width >= 900 && height >= 640);
    }
    await page.waitForFunction(() => document.querySelector('#resultStatus').textContent === 'Siap scan', null, {timeout:7000});
    assert.equal(await page.locator('#employeePhoto img').count(), 0);
    await writeFile(resolve(output,'audit.json'), JSON.stringify({checks,issues,errors}, null, 2));
    console.log(JSON.stringify({checks:checks.length,issues:issues.length,errors,output}));
    assert.deepEqual(issues, [], 'Viewport failures in audit.json');
    assert.deepEqual(errors, []);
} finally {
    await browser.close();
}
