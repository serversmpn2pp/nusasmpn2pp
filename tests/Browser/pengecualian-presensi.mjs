// Fixtures: NUSA_CAPTURE_PENGECUALIAN=1 php vendor/phpunit/phpunit/phpunit tests/Feature/PengecualianPresensiSiswaTest.php
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await playwright.chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/pengecualian-presensi-audit');
await mkdir(output, {recursive: true});
try {
    const page = await browser.newPage(), errors = [], dialogs = [];
    let posts = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('dialog', async dialog => { dialogs.push(dialog.message()); await dialog.dismiss(); });
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        let fixture;
        if (url.pathname === '/pengecualian-presensi') fixture = route.request().method() === 'POST' ? 'history' : 'index';
        if (url.pathname === '/pengecualian-presensi/pratinjau') fixture = 'preview';
        if (/^\/laporan-absensi\/\d+\/rincian$/.test(url.pathname)) fixture = 'detail';
        if (fixture) {
            if (route.request().method() === 'POST') posts.push({path: url.pathname, data: new URLSearchParams(route.request().postData())});
            return route.fulfill({contentType: 'text/html', body: await readFile(`storage/framework/testing/pengecualian-presensi/${fixture}.html`, 'utf8')});
        }
        const root = resolve('public'), path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try { return route.fulfill({body: await readFile(path), contentType: ({'.js': 'text/javascript', '.css': 'text/css', '.svg': 'image/svg+xml', '.png': 'image/png', '.woff2': 'font/woff2', '.woff': 'font/woff'})[extname(path)] || 'application/octet-stream'}); }
        catch { return route.fulfill({status: 404, body: ''}); }
    });
    const layout = async () => {
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Luapan halaman');
        assert.ok(await page.locator('.exception-section, .exception-item, .presensi-history').evaluateAll(elements => elements.every(e => {
            const r = e.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth + 1;
        })), 'Konten keluar viewport');
        assert.ok(await page.locator('img:visible').evaluateAll(images => images.every(i => i.complete && i.naturalWidth > 0)), 'Aset tidak termuat');
        assert.ok(await page.locator('.exception-stats > div').evaluateAll(elements => {
            const boxes = elements.map(e => e.getBoundingClientRect());
            return boxes.every((a, i) => boxes.every((b, j) => i === j || a.right <= b.left || b.right <= a.left || a.bottom <= b.top || b.bottom <= a.top));
        }), 'Statistik tumpang tindih');
    };
    for (const width of [360, 768, 1366]) {
        posts = [];
        await page.setViewportSize({width, height: 1000});
        await page.goto('http://localhost/pengecualian-presensi');
        const firstYear = await page.locator('#exception-year').inputValue();
        const years = await page.locator('#exception-year option').evaluateAll(options => options.map(o => o.value));
        assert.equal(years.length, 2);
        await page.selectOption('#exception-year', years.find(y => y !== firstYear));
        assert.ok(await page.locator('#exception-class option[data-year]').evaluateAll(options => options.filter(o => !o.disabled).every(o => o.textContent.includes('Tahun Baru'))));
        await page.selectOption('#exception-year', firstYear);
        await page.fill('#exception-start', '2026-09-14');
        await page.fill('#exception-end', '2026-09-18');
        await page.fill('#exception-reason', 'PJJ akibat bencana asap sesuai keputusan sekolah.');
        await layout();
        await page.screenshot({path: `${output}/form-${width}.png`, fullPage: true});
        await page.getByRole('button', {name: 'Pratinjau dampak'}).click();
        await page.waitForURL(/\/pratinjau$/);
        assert.equal(posts.length, 1);
        assert.equal(posts[0].data.get('tanggal_mulai'), '2026-09-14');
        await layout();
        await page.screenshot({path: `${output}/preview-${width}.png`, fullPage: true});
        await page.getByRole('button', {name: 'Terapkan pengecualian', exact: true}).click();
        assert.equal(posts.length, 1, 'Konfirmasi wajib');
        await page.locator('[data-exception-confirm] input[type=checkbox]').check();
        await page.getByRole('button', {name: 'Terapkan pengecualian', exact: true}).click();
        await page.waitForURL(/\/pengecualian-presensi$/);
        assert.equal(posts.length, 2);
        assert.equal(posts[1].data.get('konfirmasi'), '1');
        await page.locator('[data-exception-item] summary').click();
        const cancel = page.locator('[data-exception-item] details form');
        assert.equal(await cancel.evaluate(f => f.checkValidity()), false);
        await cancel.locator('textarea').fill('Tanggal keliru, dibatalkan oleh sekolah.');
        await cancel.locator('input[type=checkbox]').check();
        assert.equal(await cancel.evaluate(f => f.checkValidity()), true);
        await layout();
        await page.screenshot({path: `${output}/history-${width}.png`, fullPage: true});
        await page.goto('http://localhost/laporan-absensi/1/rincian');
        assert.match(await page.locator('.presensi-history').innerText(), /PJJ - scan sekolah tidak diwajibkan/);
        await layout();
        await page.screenshot({path: `${output}/detail-${width}.png`, fullPage: true});
    }
    assert.deepEqual(errors, []); assert.deepEqual(dialogs, []);
    console.log(JSON.stringify({result: 'passed', viewports: [360, 768, 1366], checks: ['year-class-scope', 'preview-submit', 'confirmation-required', 'cancel-reason-required', 'exception-detail', 'no-overlap', 'images-loaded', 'no-script-errors']}));
} finally { await browser.close(); }
