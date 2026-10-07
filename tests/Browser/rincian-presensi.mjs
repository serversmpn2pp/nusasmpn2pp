// Fixtures: NUSA_CAPTURE_RINCIAN_PRESENSI=1 php vendor/phpunit/phpunit/phpunit tests/Feature/RincianPresensiSiswaTest.php
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await playwright.chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/rincian-presensi-audit');
await mkdir(output, {recursive: true});
try {
    const page = await browser.newPage(), errors = [], dialogs = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('dialog', async dialog => { dialogs.push(dialog.message()); await dialog.dismiss(); });
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        let fixture;
        if (url.pathname === '/laporan-absensi') fixture = 'index';
        if (/^\/laporan-absensi\/\d+\/rincian$/.test(url.pathname)) {
            const status = url.searchParams.get('status_rincian') || 'semua';
            fixture = status === 'semua' ? 'detail' : status;
        }
        if (fixture) return route.fulfill({contentType: 'text/html', body: await readFile(`storage/framework/testing/rincian-presensi/${fixture}.html`, 'utf8')});
        const root = resolve('public'), path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try { return route.fulfill({body: await readFile(path), contentType: ({'.js': 'text/javascript', '.css': 'text/css', '.svg': 'image/svg+xml', '.png': 'image/png', '.woff2': 'font/woff2', '.woff': 'font/woff'})[extname(path)] || 'application/octet-stream'}); }
        catch { return route.fulfill({status: 404, body: ''}); }
    });
    const checkLayout = async () => {
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Luapan halaman');
        assert.ok(await page.locator('.presensi-identity, .presensi-summary, .presensi-history').evaluateAll(elements => elements.every(element => {
            const box = element.getBoundingClientRect(); return box.left >= 0 && box.right <= innerWidth + 1;
        })), 'Konten rincian keluar viewport');
        assert.ok(await page.locator('.presensi-summary > div').evaluateAll(elements => {
            const boxes = elements.map(element => element.getBoundingClientRect());
            return boxes.every((a, i) => boxes.every((b, j) => i === j || a.right <= b.left || b.right <= a.left || a.bottom <= b.top || b.bottom <= a.top));
        }), 'Ringkasan saling tumpang tindih');
        assert.ok(await page.locator('img:visible').evaluateAll(images => images.every(img => img.complete && img.naturalWidth > 0)), 'Aset gambar belum termuat');
    };
    for (const width of [360, 768, 1366]) {
        await page.setViewportSize({width, height: 1000});
        await page.goto('http://localhost/laporan-absensi');
        await page.locator('[data-presensi-rincian]:visible').first().click();
        await page.waitForURL(/\/rincian/);
        assert.equal(new URL(page.url()).searchParams.get('periode'), 'rentang');
        assert.equal(new URL(page.url()).searchParams.get('tanggal_mulai'), '2026-09-28');
        await checkLayout();
        assert.equal(await page.locator('[data-presensi-row]:visible').count(), 8);
        assert.match(await page.locator('.presensi-summary').innerText(), /17\s+menit/);
        await page.screenshot({path: `${output}/detail-${width}.png`, fullPage: true});
        for (const [status, count] of [['terlambat', 2], ['sakit', 1], ['izin', 1], ['alfa', 2], ['belum_scan', 1]]) {
            await page.selectOption('#status-rincian', status);
            await page.waitForURL(url => url.searchParams.get('status_rincian') === status);
            await checkLayout();
            assert.equal(await page.locator('[data-presensi-row]:visible').count(), count);
            assert.equal(new URL(page.url()).searchParams.get('tanggal_selesai'), '2026-10-07');
            if (status === 'terlambat') {
                const rows = await page.locator('[data-presensi-row]:visible').allTextContents();
                assert.ok(rows.every(text => /Hadir/.test(text) && /(?:12|5) menit/.test(text)));
                await page.screenshot({path: `${output}/terlambat-${width}.png`, fullPage: true});
            }
            if (status === 'alfa') assert.match(await page.locator('body').innerText(), /Alfa otomatis: hari berakhir tanpa konfirmasi/);
        }
        await page.getByRole('link', {name: 'Reset', exact: true}).click();
        await page.waitForURL(url => !url.searchParams.has('status_rincian'));
        assert.equal(await page.locator('[data-presensi-row]:visible').count(), 8);
        const back = new URL(await page.locator('[data-presensi-kembali]').getAttribute('href'));
        assert.equal(back.searchParams.get('tanggal_mulai'), '2026-09-28');
        await page.locator('[data-presensi-kembali]').click();
        await page.waitForURL(url => url.pathname === '/laporan-absensi');
        assert.equal(new URL(page.url()).searchParams.get('periode'), 'rentang');
    }
    assert.deepEqual(errors, []);
    assert.deepEqual(dialogs, []);
    console.log(JSON.stringify({result: 'passed', viewports: [360, 768, 1366], checks: ['detail-link', 'period-preserved', 'status-and-late-filters', 'summary-unchanged', 'reset-and-back', 'no-overlap', 'images-loaded', 'no-script-errors']}));
} finally { await browser.close(); }
