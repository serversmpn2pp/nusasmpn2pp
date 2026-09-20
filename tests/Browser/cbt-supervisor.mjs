// CBT_SUPERVISOR_FIXTURE=1 php artisan test --filter=UjianTerpusatPelaksanaanNilaiTest
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve, extname, sep } from 'node:path';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await chromium.launch({ headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || undefined });
try {
    const page = await browser.newPage();
    const errors = [];
    let offline = false;
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (url.pathname.startsWith('/tugas-pengawas-ujian/')) {
            if (offline) return route.abort('internetdisconnected');
            const phase = url.searchParams.get('tahap') || 'persiapan';
            return route.fulfill({ contentType:'text/html', body:await readFile(`storage/logs/cbt-supervisor-${phase}.html`, 'utf8') });
        }
        const root = resolve('public');
        const file = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(root + sep)) return route.abort();
        try {
            return route.fulfill({ body:await readFile(file), contentType:({ '.js':'text/javascript', '.css':'text/css', '.png':'image/png', '.woff2':'font/woff2' })[extname(file)] || 'application/octet-stream' });
        } catch { return route.fulfill({ status:404, body:'' }); }
    });
    for (const width of [390, 768, 1024, 1366]) {
        await page.setViewportSize({ width, height:768 });
        for (const phase of ['persiapan', 'pantau', 'bukti']) {
            await page.goto(`http://localhost/tugas-pengawas-ujian/1?tahap=${phase}`);
            assert.equal(await page.locator('.supervisor-tabs a[aria-current=page]').count(), 1);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Overflow ${width} ${phase}`);
            const bounds = await page.locator('.supervisor-tabs').boundingBox();
            assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width + 1);
            if (width === 1366) await page.screenshot({ path:`storage/logs/supervisor-${phase}.png`, fullPage:true });
        }
    }
    await page.goto('http://localhost/tugas-pengawas-ujian/1?tahap=pantau');
    await page.locator('#supervisor-status').selectOption('selesai');
    assert.ok(await page.locator('#supervisor-empty').isVisible());
    await page.locator('#supervisor-status').selectOption('');
    assert.equal(await page.locator('[data-supervisor-student]:visible').count(), 2);
    await page.screenshot({ path:'storage/logs/supervisor-monitor-final.png', fullPage:true });
    await page.locator('#supervisor-search').fill('nama tidak ada');
    await page.locator('#supervisor-refresh').click();
    await page.waitForFunction(() => !document.getElementById('supervisor-refresh').disabled);
    assert.equal(await page.locator('#supervisor-search').inputValue(), 'nama tidak ada');
    assert.ok(await page.locator('#supervisor-empty').isVisible());
    offline = true;
    await page.locator('#supervisor-refresh').click();
    await page.waitForFunction(() => !document.getElementById('supervisor-refresh').disabled);
    assert.match(await page.locator('#supervisor-live').textContent(), /Data belum diperbarui/);
    assert.ok(await page.locator('#supervisor-monitor').isVisible());
    offline = false;
    await page.locator('.supervisor-tabs a').nth(2).click();
    assert.ok(await page.locator('[data-proof-input]').first().isVisible());
    await page.locator('.supervisor-tabs a').first().click();
    assert.ok(await page.locator('#supervisor-token').isVisible());
    assert.deepEqual(errors, []);
    console.log('PASS: 12 viewport/phase checks, navigation, search retained after refresh, offline recovery message, no JS errors.');
} finally { await browser.close(); }
