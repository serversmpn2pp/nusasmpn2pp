// Fixtures: NUSA_CAPTURE_POIN_PRESENSI=1 php vendor/phpunit/phpunit/phpunit tests/Feature/PoinPresensiLangsungTest.php
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await playwright.chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/poin-presensi-audit');
await mkdir(output, {recursive: true});
try {
    const page = await browser.newPage(), errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/(settings|correction|attendance)$/)?.[1];
        if (name) return route.fulfill({contentType: 'text/html', body: await readFile(`storage/framework/testing/poin-presensi/${name}.html`, 'utf8')});
        const root = resolve('public'), path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try { return route.fulfill({body: await readFile(path), contentType: ({'.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.woff2': 'font/woff2'})[extname(path)] || 'application/octet-stream'}); }
        catch { return route.fulfill({status: 404, body: ''}); }
    });
    for (const width of [360, 768, 1366]) {
        await page.setViewportSize({width, height: 1000});
        await page.goto('http://localhost/audit/settings');
        const mode = page.locator('#otomatis_langsung');
        assert.ok(await mode.isChecked());
        assert.ok(await page.locator('#legacy-late-settings').isHidden());
        assert.equal(await page.locator('#poin_terlambat').inputValue(), '15');
        assert.equal(await page.locator('#poin_alfa').inputValue(), '25');
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Settings overflow @${width}`);
        await mode.scrollIntoViewIfNeeded();
        await page.screenshot({path: `${output}/settings-${width}.png`});
        await mode.uncheck();
        assert.ok(await page.locator('#legacy-late-settings').isVisible());
        await mode.check();
        assert.ok(await page.locator('#legacy-late-settings').isHidden());

        await page.goto('http://localhost/audit/correction');
        const reason = page.locator('#alasan-poin');
        await reason.scrollIntoViewIfNeeded();
        assert.ok(await reason.evaluate(el => el.required));
        assert.ok(!(await reason.evaluate(el => el.checkValidity())));
        await reason.fill('Siswa mendapat tugas resmi dari sekolah.');
        assert.ok(await reason.evaluate(el => el.checkValidity()));
        const button = page.getByRole('button', {name: 'Terima alasan dan batalkan poin'});
        assert.ok(await button.isVisible());
        assert.ok(await button.evaluate(el => {
            const rect = el.getBoundingClientRect();
            return el.scrollWidth <= el.clientWidth + 1 && rect.left >= 0 && rect.right <= innerWidth + 1;
        }), `Correction button @${width}`);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Correction overflow @${width}`);
        await page.screenshot({path: `${output}/correction-${width}.png`});

        await page.goto('http://localhost/audit/attendance');
        assert.equal(await page.locator('#jam_masuk').inputValue(), '07:00:01');
        assert.equal(await page.locator('#jam_masuk').getAttribute('step'), '1');
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Attendance overflow @${width}`);
    }
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({result: 'passed', checks: ['desktop-mobile', 'mode-toggle', '15-25-points', 'reason-required', 'seconds-preserved', 'no-overflow']}));
} finally { await browser.close(); }
