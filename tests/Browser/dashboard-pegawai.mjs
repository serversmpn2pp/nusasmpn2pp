import assert from 'node:assert/strict';
import {readFile, mkdir, writeFile} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const fixtures = resolve('storage/framework/testing/dashboard-pegawai');
const output = resolve('storage/logs/dashboard-pegawai-qa');
const publicRoot = resolve('public');
const roles = ['guru_mapel', 'tata_usaha', 'pimpinan', 'kosong'];
const errors = [], missing = [], issues = [], checks = [];
await mkdir(output, {recursive: true});
const browser = await chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
try {
    const page = await browser.newPage({serviceWorkers: 'block', reducedMotion: 'reduce'});
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const u = new URL(route.request().url());
        if (!['localhost', '127.0.0.1'].includes(u.hostname)) return route.abort();
        if (u.pathname.startsWith('/dashboard-audit/')) {
            const role = u.pathname.split('/').pop();
            return route.fulfill({contentType: 'text/html', body: await readFile(resolve(fixtures, `${role}.html`), 'utf8')});
        }
        if (u.pathname === '/notifikasi/ringkasan') return route.fulfill({json: {jumlah_belum_dibaca: 0, notifikasi: []}});
        const file = resolve(publicRoot, '.' + decodeURIComponent(u.pathname));
        if (!file.startsWith(publicRoot + sep)) return route.abort();
        try {
            return route.fulfill({body: await readFile(file), contentType: ({'.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.jpg': 'image/jpeg', '.svg': 'image/svg+xml', '.webp': 'image/webp', '.woff2': 'font/woff2'})[extname(file)] || 'application/octet-stream'});
        } catch {
            missing.push(u.pathname);
            return route.fulfill({status: 404, body: ''});
        }
    });
    for (const [width, height] of [[320, 740], [390, 844], [768, 1024], [1024, 768], [1280, 720], [1440, 960], [1920, 1080]]) {
        await page.setViewportSize({width, height});
        for (const role of roles) {
            await page.goto(`http://localhost/dashboard-audit/${role}`);
            await page.evaluate(() => document.fonts.ready);
            await page.waitForFunction(() => !!document.querySelector('[data-admin-sidebar-toggle] svg.lucide') && !!window.Chart?.getChart(document.querySelector('[data-chart-canvas]')));
            const layout = await page.evaluate(() => {
                const root = document.querySelector('.pegawai-home');
                const visible = e => !!e.getClientRects().length;
                return {
                    pageWidth: document.documentElement.scrollWidth,
                    width: root.clientWidth,
                    scroll: root.scrollWidth,
                    clipped: [...root.querySelectorAll('h1,h2,h3,.ph-badge,.ph-link,.ph-task-text,.ph-stat-label,.ph-actions a')].filter(visible).filter(e => e.scrollWidth > e.clientWidth + 2).map(e => ({text: e.textContent.trim(), class: e.className})),
                    missingImages: [...document.images].filter(visible).filter(e => !e.complete || !e.naturalWidth).map(e => e.src),
                };
            });
            if (layout.pageWidth > width + 1 || layout.scroll > layout.width + 2 || layout.clipped.length || layout.missingImages.length) issues.push({role, width, ...layout});
            if (width >= 1024) {
                assert.equal(await page.locator('.ph-row').first().evaluate(e => getComputedStyle(e).gridTemplateColumns.split(' ').length), 2, 'Desktop should retain two columns');
            }
            for (const range of ['bulan', 'minggu']) {
                await page.locator(`[data-chart-range="${range}"]`).click();
                for (const type of ['line', 'bar']) {
                    await page.locator(`[data-chart-type="${type}"]`).click();
                    const chart = await page.locator('[data-chart-canvas]').evaluate(canvas => {
                        const c = Chart.getChart(canvas);
                        const pixels = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data;
                        let painted = 0;
                        for (let i = 3; i < pixels.length; i += 4) if (pixels[i] > 0) painted++;
                        return {type: c.config.type, points: c.data.labels.length, values: c.data.datasets[0].data, painted};
                    });
                    assert.equal(chart.type, type);
                    assert.equal(chart.points, range === 'bulan' ? 19 : 7);
                    assert.ok(chart.painted > 100, 'Chart canvas must not be blank');
                    assert.equal(await page.locator(`[data-chart-range="${range}"]`).getAttribute('aria-pressed'), 'true');
                    assert.equal(await page.locator(`[data-chart-type="${type}"]`).getAttribute('aria-pressed'), 'true');
                    assert.equal(await page.locator('[data-chart-empty]').isVisible(), role === 'kosong');
                    if (role !== 'kosong') assert.equal(chart.values.filter(v => v === 7).length, 1);
                    checks.push({role, width, range, type});
                }
            }
            await page.locator('.ph-chart-data summary').click();
            assert.equal(await page.locator('[data-chart-table] li').count(), 7);
            await page.locator('.ph-chart-data summary').click();
            await page.evaluate(() => {
                window.scrollTo(0, 0);
                document.querySelector('.app-content').scrollTo(0, 0);
            });
            if ([390, 1024, 1440].includes(width)) await page.screenshot({path: resolve(output, `${role}-${width}.png`), fullPage: true});
        }
    }
    await writeFile(resolve(output, 'audit.json'), JSON.stringify({checks, issues, errors, missing}, null, 2));
    console.log(JSON.stringify({states: checks.length, issues, errors, missing, output}));
    assert.deepEqual(issues, []);
    assert.deepEqual(errors, []);
    assert.deepEqual(missing, []);
} finally {
    await browser.close();
}
