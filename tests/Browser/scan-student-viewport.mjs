import assert from 'node:assert/strict';
import {readFile, mkdir, writeFile} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const fixtures = resolve('storage/framework/testing/role-theme');
const publicRoot = resolve('public');
const output = resolve('storage/logs/scan-student-viewport');
const pages = Object.fromEntries(Object.entries(JSON.parse(await readFile(resolve(fixtures, 'manifest-scan.json'), 'utf8'))).filter(([path]) => path.startsWith('/scan-audit/siswa/')));
assert.equal(Object.keys(pages).length, 4, 'Capture TampilanLintasRoleTest with NUSA_CAPTURE_ROLE_THEME=1 first');
const browser = await chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const issues = [], errors = [], checks = [];
let scanState = 'success';
await mkdir(output, {recursive: true});
try {
    const context = await browser.newContext({serviceWorkers: 'block', reducedMotion: 'reduce'});
    const page = await context.newPage();
    page.on('pageerror', e => errors.push(e.message));
    await page.route('**/*', async route => {
        const request = route.request(), url = new URL(request.url());
        if (!['localhost', '127.0.0.1'].includes(url.hostname)) return route.abort();
        if (request.method() !== 'GET') {
            assert.equal(url.pathname, '/scan-absensi');
            if (scanState === 'offline') return route.abort('internetdisconnected');
            return route.fulfill({json: {
                berhasil: scanState === 'success',
                status: scanState === 'duplicate' ? 'sudah_scan_masuk' : scanState === 'warning' ? 'di_luar_jadwal_masuk' : 'berhasil',
                pesan: 'Presensi masuk berhasil dicatat. Selamat mengikuti pembelajaran hari ini.',
                jenis_scan: 'masuk', waktu_server: '08:05:00', scanner_id: 'SCANNER-GERBANG-UTAMA',
                siswa: {nama_lengkap: 'MUHAMMAD ABDURRAHMAN HARIANJA DENGAN NAMA LENGKAP PANJANG', nisn: '0130000001', inisial: 'MA', foto_url: '/images/logo-nusa.png'},
                absensi: {jam_masuk: '08:05:00', menit_terlambat: 65},
            }});
        }
        if (pages[url.pathname]) return route.fulfill({contentType: 'text/html', body: await readFile(resolve(fixtures, pages[url.pathname].file + '.html'), 'utf8')});
        const path = resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
        assert.ok(path.startsWith(publicRoot + sep));
        const type = {'.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.woff2': 'font/woff2', '.webp': 'image/webp'}[extname(path)] || 'application/octet-stream';
        return route.fulfill({contentType: type, body: await readFile(path)});
    });

    async function inspect(label, width, height, mustFit) {
        await page.evaluate(() => window.scrollTo(0, 0));
        const result = await page.evaluate(() => {
            const selectors = ['.scan-header', '.time-panel', '.scanner-panel', '#clockText', '#scanInput', '#resultCard', '#studentPhoto', '#resultStatus', '#resultName', '#resultNisn', '#resultMessage', '#resultMeta'];
            const boxes = selectors.map(selector => {
                const e = document.querySelector(selector), r = e.getBoundingClientRect();
                let crop = null;
                for (let p = e.parentElement; p; p = p.parentElement) {
                    const s = getComputedStyle(p), b = p.getBoundingClientRect();
                    if (['hidden', 'clip'].includes(s.overflowY) && (r.bottom > b.bottom + 2 || r.top < b.top - 2)) crop = p.className || p.tagName;
                }
                return {selector, visible: !!e.getClientRects().length, x: r.x, y: r.y, width: r.width, height: r.height, right: r.right, bottom: r.bottom, crop, overflow: e.scrollWidth > e.clientWidth + 2};
            });
            return {width: document.documentElement.scrollWidth, height: document.documentElement.scrollHeight, viewport: innerHeight, scrollable: !['hidden', 'clip'].includes(getComputedStyle(document.documentElement).overflowY) && !['hidden', 'clip'].includes(getComputedStyle(document.body).overflowY), boxes};
        });
        const invalid = result.boxes.filter(e => e.visible && (e.crop || e.overflow || e.x < -1 || e.right > width + 1));
        const core = result.boxes.filter(e => e.visible && ['#scanInput', '#resultCard', '#clockText'].includes(e.selector));
        const photo = result.boxes.find(e => e.selector === '#studentPhoto');
        const name = result.boxes.find(e => e.selector === '#resultName');
        const card = result.boxes.find(e => e.selector === '#resultCard');
        const photoInvalid = photo.width < (width <= 540 ? 158 : 134) || Math.abs(photo.width / photo.height - .75) > .02 || photo.bottom > card.bottom - 8 || (width > 540 && photo.right > name.x - 8) || (width >= 1920 && height > 800 && photo.width < 220);
        if (result.width > width + 1 || invalid.length || photoInvalid || (result.height > height + 2 && !result.scrollable) || (mustFit && core.some(e => e.bottom > height + 1))) issues.push({label, width, height, mustFit, invalid, photoInvalid, ...result});
        checks.push({label, width, height, pageHeight: result.height});
    }

    const sizes = [[1024,768],[1024,680],[1024,600],[1280,720],[1366,658],[1440,900],[1920,1080],[2560,1440],[900,640],[820,614],[800,600],[768,1024],[640,480],[390,844],[320,568]];
    for (const [width, height] of sizes) {
        await page.setViewportSize({width, height});
        for (const [path, entry] of Object.entries(pages)) {
            await page.goto('http://localhost' + path);
            await page.evaluate(() => document.fonts.ready);
            await inspect(entry.file + '-idle', width, height, width >= 900 && height >= 640);
            if (path.endsWith('/aktif') && width === 1024 && height === 768) await page.screenshot({path: resolve(output, 'idle-1024x768.png'), fullPage: true});
            scanState = 'success';
            await page.locator('#scanInput').fill('0130000001');
            await page.locator('#scanInput').press('Enter');
            await page.waitForFunction(() => document.querySelector('#resultCard').classList.contains('is-success'));
            await page.locator('#studentPhoto img').evaluate(e => e.decode());
            await inspect(entry.file + '-success', width, height, width >= 900 && height >= 640);
            if (path.endsWith('/aktif') && [320,800,1024,1920].includes(width)) {
                await page.screenshot({path: resolve(output, `success-${width}x${height}.png`), fullPage: true});
            }
            assert.equal(await page.locator('#scanInput').inputValue(), '');
            assert.ok(await page.locator('#scanInput').evaluate(e => e === document.activeElement));
            // On small screens the entire result remains reachable rather than clipped.
            await page.locator('#resultMeta').scrollIntoViewIfNeeded();
            assert.ok(await page.locator('#resultMeta').evaluate(e => e.getBoundingClientRect().bottom <= innerHeight + 1));
        }
    }

    await page.setViewportSize({width: 1024, height: 680});
    await page.goto('http://localhost/scan-audit/siswa/aktif');
    for (const [i, state] of ['success','duplicate','warning','offline','success','success','success'].entries()) {
        scanState = state;
        await page.locator('#scanInput').fill('013000000' + i);
        await page.locator('#scanInput').press('Enter');
        const expected = {success:'is-success',duplicate:'is-recorded',warning:'is-warning',offline:'is-error'}[state];
        await page.waitForFunction(expected => document.querySelector('#resultCard').classList.contains(expected), expected);
        await inspect('history-' + state, 1024, 680, true);
        await page.waitForTimeout(700);
    }
    assert.equal(await page.locator('.history-item').count(), 6);
    const history = await page.locator('#historyList').evaluate(e => ({height:e.clientHeight,scroll:e.scrollHeight}));
    assert.ok(history.scroll > history.height, 'History must scroll within its own area');
    await page.screenshot({path: resolve(output, 'history-1024x680.png'), fullPage: true});
    for (const [width, height] of [[1920,1080],[1024,768],[1024,680],[820,614],[390,844]]) {
        await page.setViewportSize({width, height});
        assert.ok(await page.locator('#resultCard').evaluate(e => e.classList.contains('is-success')));
        await inspect('live-monitor-resize', width, height, width >= 900 && height >= 640);
        assert.ok(await page.locator('#scanInput').evaluate(e => e === document.activeElement));
    }
    await page.waitForFunction(() => document.querySelector('#resultStatus').textContent === 'Siap scan', null, {timeout: 7000});
    assert.equal(await page.locator('#studentPhoto img').count(), 0);
    await writeFile(resolve(output, 'audit.json'), JSON.stringify({checks, issues, errors}, null, 2));
    console.log(JSON.stringify({checks:checks.length,issues:issues.length,errors,output}));
    assert.deepEqual(issues, [], 'Viewport failures in audit.json');
    assert.deepEqual(errors, []);
} finally {
    await browser.close();
}
