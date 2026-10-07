// Fixtures: NUSA_CAPTURE_ALFA_UI=1 php vendor/phpunit/phpunit/phpunit tests/Feature/AlfaOtomatisSiswaTest.php tests/Feature/RaporStsTest.php
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await playwright.chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/alfa-otomatis-audit');
await mkdir(output, {recursive: true});
try {
    const page = await browser.newPage(), errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url()), name = url.pathname.match(/^\/audit\/([a-z]+)$/)?.[1];
        if (name) return route.fulfill({contentType: 'text/html', body: await readFile(`storage/framework/testing/alfa-otomatis/${name}.html`, 'utf8')});
        const root = resolve('public'), path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try { return route.fulfill({body: await readFile(path), contentType: ({'.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.woff2': 'font/woff2'})[extname(path)] || 'application/octet-stream'}); }
        catch { return route.fulfill({status: 404, body: ''}); }
    });
    for (const width of [390, 1366]) {
        await page.setViewportSize({width, height: 1000});
        for (const name of ['before', 'after', 'report', 'rapor']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Luapan halaman ${name}@${width}`);
            const text = await page.locator('body').innerText();
            if (name === 'before') assert.match(text, /Belum dikonfirmasi/);
            if (name === 'after') assert.match(text, /Otomatis: hari berakhir tanpa konfirmasi/);
            if (name === 'report') assert.match(text, /Hari ini masih menunggu konfirmasi/);
            if (name === 'rapor') assert.match(text, /Hari presensi aktif tanpa catatan atau konfirmasi dihitung alfa setelah hari berakhir/);
            await page.screenshot({path: `${output}/${name}-${width}.png`, fullPage: true});
        }
    }
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({result: 'passed', viewports: [390, 1366], pages: ['before', 'after', 'report', 'rapor'], checks: ['pending-before-midnight', 'automatic-alfa-label', 'date-cutoff-copy', 'no-page-overflow', 'no-script-errors']}));
} finally { await browser.close(); }
