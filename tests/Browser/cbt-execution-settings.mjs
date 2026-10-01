// CBT_EXECUTION_FIXTURE=1 php artisan test --filter=test_paket_terbit_menyinkronkan_peserta_ruang_dan_tampil_di_akun_siswa
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { extname, resolve, sep } from 'node:path';
import { pathToFileURL } from 'node:url';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE
    ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const html = await readFile('storage/logs/cbt-execution-settings-audit.html', 'utf8');
const browser = await chromium.launch({ headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || undefined });

try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/audit') {
            return route.fulfill({ contentType:'text/html', body:html });
        }

        const root = resolve('public');
        const file = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(root + sep)) return route.abort();
        try {
            return route.fulfill({
                body:await readFile(file),
                contentType:({ '.js':'text/javascript', '.css':'text/css', '.png':'image/png', '.jpg':'image/jpeg', '.woff2':'font/woff2' })[extname(file).toLowerCase()] || 'application/octet-stream',
            });
        } catch {
            return route.fulfill({ status:404, body:'' });
        }
    });

    for (const [width, height] of [[320, 568], [390, 844], [768, 900], [1366, 900]]) {
        await page.setViewportSize({ width, height });
        await page.goto('http://localhost/audit');
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Halaman melebar pada ${width}px`);
        assert.equal(await page.locator('.execution-session-divider').count(), 2, 'Jadwal harus dipisahkan menjadi dua sesi');
        assert.equal(await page.locator('.execution-card').count(), 3, 'Fixture harus menampilkan tiga pelaksanaan');
        assert.equal(await page.locator('.execution-card.is-ready').count(), 1, 'Paket siap harus memiliki warna status siap');
        assert.equal(await page.locator('.execution-card.is-needs-setup').count(), 2, 'Paket belum siap harus memiliki warna peringatan');

        const cardHeaderColors = await page.locator('.execution-card-head').evaluateAll(headers => headers.map(header => getComputedStyle(header).backgroundColor));
        assert.notEqual(cardHeaderColors[0], cardHeaderColors[1], 'Kartu siap dan belum siap harus memiliki warna header berbeda');

        if (width === 1366) await page.screenshot({ path:'storage/logs/cbt-execution-settings-overview-desktop.png', fullPage:false });

        const supervisorDetails = page.locator('.execution-card.is-ready .supervisor-details');
        await supervisorDetails.locator('summary').first().click();
        const rows = supervisorDetails.locator('.supervisor-row');
        assert.equal(await rows.count(), 2, 'Fixture harus menampilkan dua ruang pengawas');
        const roomColors = await rows.evaluateAll(items => items.map(item => getComputedStyle(item).backgroundColor));
        assert.notEqual(roomColors[0], roomColors[1], 'Ruang ganjil dan genap harus memiliki warna selang-seling');
        assert.equal(await supervisorDetails.locator('.supervisor-replacement:not([open])').count(), 1, 'Penggantian mendadak harus tampil ringkas saat tertutup');
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Pengaturan pengawas melebar pada ${width}px`);

        if (width === 390) await page.screenshot({ path:'storage/logs/cbt-execution-settings-mobile.png', fullPage:true });
        if (width === 1366) await page.screenshot({ path:'storage/logs/cbt-execution-settings-desktop.png', fullPage:true });
    }

    assert.deepEqual(errors, []);
    console.log('PASS: pemisah sesi, warna status, ruang selang-seling, penggantian ringkas, dan layout 320-1366px.');
} finally {
    await browser.close();
}
