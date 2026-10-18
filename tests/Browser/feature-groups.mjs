import assert from 'node:assert/strict';
import {readFile, mkdir, writeFile} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const fixtures = resolve('storage/framework/testing/feature-groups');
const output = resolve('storage/logs/feature-groups-qa');
const publicRoot = resolve('public');
const manifest = JSON.parse(await readFile(resolve(fixtures, 'manifest.json'), 'utf8'));
const key = value => { const u = new URL(value, 'http://localhost'); u.searchParams.sort(); return u.pathname + u.search; };
const pages = Object.fromEntries(Object.entries(manifest).map(([path, entry]) => [key(path), entry]));
const browser = await chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const errors = [], missing = [], writes = [], checks = [], issues = [];
await mkdir(output, {recursive: true});
try {
    const context = await browser.newContext({serviceWorkers: 'block', reducedMotion: 'reduce'});
    const page = await context.newPage();
    page.on('pageerror', error => errors.push({path: new URL(page.url()).pathname, error: error.message}));
    await page.route('**/*', async route => {
        const request = route.request(), url = new URL(request.url());
        if (!['localhost', '127.0.0.1'].includes(url.hostname)) return route.abort();
        if (request.method() !== 'GET') { writes.push(url.pathname); return route.fulfill({status: 405, body: ''}); }
        if (pages[key(url.href)]) return route.fulfill({contentType: 'text/html', body: await readFile(resolve(fixtures, pages[key(url.href)].file + '.html'), 'utf8')});
        if (url.pathname === '/notifikasi/ringkasan') return route.fulfill({json: {jumlah_belum_dibaca: 0, notifikasi: []}});
        const path = resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
        assert.ok(path.startsWith(publicRoot + sep));
        try { return route.fulfill({body: await readFile(path), contentType: ({'.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.webmanifest': 'application/manifest+json', '.woff2': 'font/woff2'})[extname(path)] || 'application/octet-stream'}); }
        catch { missing.push(url.pathname); return route.fulfill({status: 404, body: ''}); }
    });
    async function ready(path) {
        await page.goto('http://localhost' + path);
        await page.waitForFunction(() => !!document.querySelector('[data-admin-sidebar-toggle] svg.lucide'));
        await page.evaluate(() => document.fonts.ready);
    }
    const shots = new Set();
    for (const [width, height] of [[320,740],[390,844],[768,1024],[1024,768],[1440,960],[1920,1080]]) {
        await page.setViewportSize({width, height});
        for (const [path, entry] of Object.entries(pages)) {
            await ready(path);
            const result = await page.evaluate(() => {
                const root = document.querySelector('.content-shell');
                const visible = e => !!e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden';
                const clipped = [...root.querySelectorAll('.page-title,.panel-title,.person-name,.button,.stat-label')].filter(visible)
                    .filter(e => e.clientWidth > 0 && e.scrollWidth > e.clientWidth + 2)
                    .map(e => ({text: e.textContent.trim().slice(0,120), width: e.clientWidth, scroll: e.scrollWidth}));
                return {rootWidth: root.clientWidth, rootScroll: root.scrollWidth, pageWidth: document.documentElement.scrollWidth,
                    module: document.body.dataset.adminModule, styles: document.querySelectorAll('link[href*="admin-feature-pages.css"]').length,
                    clipped, smallControls: [...root.querySelectorAll('.input,.select')].filter(visible).filter(e => parseFloat(getComputedStyle(e).fontSize) < 16).map(e => e.id),
                    images: [...root.querySelectorAll('img[src]')].filter(visible).filter(e => e.getAttribute('src')).map(e => ({src: e.getAttribute('src'), ok: e.complete && e.naturalWidth > 0})),
                };
            });
            assert.equal(result.module, entry.group);
            assert.equal(result.styles, 1);
            if (result.rootScroll > result.rootWidth + 2 || result.pageWidth > width + 1 || result.clipped.length || result.smallControls.length) {
                issues.push({path, width, ...result});
            }
            const wanted = /input-nilai\?komponen|monitoring|rekap-absensi-harian$|laporan-pembinaan-siswa\/\d|persuratan\/\d/.test(path);
            const shotKey = `${entry.group}-${width}`;
            if ([390,1440].includes(width) && (wanted || !shots.has(shotKey))) {
                shots.add(shotKey);
                await page.screenshot({path: resolve(output, `${entry.file}-${width}.png`), fullPage: false});
            }
            checks.push({path, width, group: entry.group});
        }
    }
    // Exercise the real unsaved-value guard and decimal input without issuing writes.
    const gradePath = Object.keys(pages).find(p => p.startsWith('/input-nilai?komponen'));
    await page.setViewportSize({width: 1440, height: 960});
    await ready(gradePath);
    const input = page.locator('[data-grade-form] input[inputmode="decimal"]').first();
    await input.fill('88,75');
    assert.ok(await input.evaluate(e => e.checkValidity()));
    assert.equal(await page.locator('[data-grade-unsaved]').textContent(), '1 perubahan belum disimpan');
    page.once('dialog', dialog => dialog.dismiss());
    const original = await page.locator('#jenis_komponen').inputValue();
    await page.locator('#jenis_komponen').selectOption('formatif');
    assert.equal(await page.locator('#jenis_komponen').inputValue(), original);
    assert.equal(await input.inputValue(), '88,75');
    await input.fill('87,50');
    assert.ok(await page.locator('[data-grade-unsaved]').isHidden());
    await page.locator('.grade-table-wrap').evaluate(e => { e.scrollTop = e.scrollHeight; });
    const grade = await page.locator('.grade-table-wrap').evaluate(e => {
        const th = e.querySelector('th').getBoundingClientRect(), r = e.getBoundingClientRect();
        return {sticky: Math.abs(th.top-r.top) < 3, scrolled: e.scrollTop > 0};
    });
    assert.ok(grade.sticky && grade.scrolled, 'Grade header must remain visible');
    for (const path of Object.keys(pages).filter(p => /monitoring$|rekap-absensi-harian$|rekap-absensi-pegawai-harian$/.test(p))) {
        await ready(path);
        const table = page.locator('.feature-worktable');
        if (!await table.count()) continue;
        await table.evaluate(e => { e.scrollLeft = 300; e.scrollTop = 200; });
        const pinned = await table.evaluate(e => {
            const index = e.classList.contains('attendance-worktable') ? 2 : 1;
            const cell = e.querySelector(`tbody tr td:nth-child(${index})`), head = e.querySelector('thead th');
            const r = e.getBoundingClientRect();
            return {left: cell?.getBoundingClientRect().left, expected: r.left, top: head?.getBoundingClientRect().top, expectedTop: r.top};
        });
        if (pinned.left !== undefined) assert.ok(Math.abs(pinned.left-pinned.expected) < 3, `Identity not pinned: ${path}`);
        assert.ok(Math.abs(pinned.top-pinned.expectedTop) < 3, `Header not pinned: ${path}`);
    }
    await writeFile(resolve(output, 'audit.json'), JSON.stringify({checks, issues, errors, missing, writes}, null, 2));
    console.log(JSON.stringify({pages: Object.keys(pages).length, states: checks.length, issues: issues.length, errors, missing, writes, output}));
    assert.deepEqual(issues, [], 'Visual layout issues; inspect audit.json');
    assert.deepEqual(errors, []);
    assert.deepEqual(missing, []);
    assert.deepEqual(writes, []);
} finally { await browser.close(); }
