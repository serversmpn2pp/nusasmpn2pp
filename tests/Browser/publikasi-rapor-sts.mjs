import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const {PDFDocument} = await import(process.env.PDF_LIB_MODULE ? pathToFileURL(process.env.PDF_LIB_MODULE).href : 'pdf-lib');
const browser = await chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/publikasi-rapor-sts-qa');
await mkdir(output, {recursive: true});
try {
    const page = await browser.newPage(), errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const fixture = url.pathname.match(/^\/audit\/(workspace|pending|manager|manager-active|index|print)$/)?.[1];
        if (fixture) return route.fulfill({contentType: 'text/html', body: await readFile(`storage/framework/testing/publikasi-rapor-sts/${fixture}.html`, 'utf8')});
        const root = resolve('public'), path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try { return route.fulfill({body: await readFile(path), contentType: ({'.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.woff2': 'font/woff2'})[extname(path)] || 'application/octet-stream'}); }
        catch { return route.fulfill({status: 404, body: ''}); }
    });
    for (const width of [320, 390, 768, 1366, 1920]) {
        await page.setViewportSize({width, height: 1000});
        await page.goto('http://localhost/audit/workspace');
        assert.equal(await page.locator('.sts-metric').count(), 4);
        assert.deepEqual(await page.locator('.sts-band-title h2').allTextContents(), ['Periode dan tanggal rapor', 'Lampiran perilaku dan pembinaan', 'Pemeriksaan nilai dan kehadiran', 'Cetak rapor kelas']);
        const colors = await page.locator('.sts-band-head, .sts-publish-band').evaluateAll(els => els.map(el => getComputedStyle(el).backgroundColor));
        assert.equal(new Set(colors).size, 5, 'Distinct full-color content bands');
        assert.equal(await page.getByRole('button', {name: 'Simpan periode', exact: true}).evaluate(el => el.form.id), 'sts-periode-form');
        assert.deepEqual(await page.getByRole('button', {name: 'Simpan pemeriksaan', exact: true}).evaluateAll(els => els.map(el => el.form.id)), ['sts-kehadiran-form', 'sts-kehadiran-form']);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Workspace overflow ${width}`);
        const clipped = await page.locator('.sts-band-head, .sts-publish-band, .sts-metric, .sts-filters .button, .sts-band-footer .button').evaluateAll(els => els.filter(el => el.scrollWidth > el.clientWidth + 1).map(el => el.textContent.trim()));
        if (clipped.length) {
            await page.locator('.sts-publish-band').screenshot({path: `${output}/overflow-${width}.png`});
            console.log(await page.locator('.sts-publish-band').evaluate(el => ({width: el.clientWidth, scroll: el.scrollWidth, children: [...el.children].map(c => ({width: c.clientWidth, scroll: c.scrollWidth, display: getComputedStyle(c).display, min: getComputedStyle(c).minWidth}))})));
        }
        assert.deepEqual(clipped, [], `Controls clipped ${width}`);
        await page.locator('#sts-periksa-semua').uncheck();
        assert.equal(await page.locator('[data-sts-reviewed]:checked').count(), 0);
        await page.locator('#sts-periksa-semua').check();
        assert.equal(await page.locator('[data-sts-reviewed]:checked').count(), 3);
        await page.locator('[data-sts-grades]').first().click();
        assert.ok(await page.locator('#sts-grade-dialog').isVisible());
        await page.locator('[data-sts-close]').click();
        await page.goto('http://localhost/audit/pending');
        assert.equal(await page.getByText('Periode presensi belum berakhir. Rapor belum dapat dicetak.', {exact: true}).count(), 1);
        assert.equal(await page.locator('[data-sts-print]').count(), 0);
        if ([390,1366].includes(width)) {
            await page.screenshot({path: `${output}/workspace-${width}.png`, fullPage: true});
            await page.locator('.sts-band--blue').evaluate(el => {
                const parent = document.querySelector('.app-content'), top = document.querySelector('.app-topbar').getBoundingClientRect().height;
                if (getComputedStyle(parent).overflowY === 'auto') parent.scrollTop += el.getBoundingClientRect().top - top - 16;
                else window.scrollBy(0, el.getBoundingClientRect().top - top - 16);
            });
            await page.screenshot({path: `${output}/workspace-focus-${width}.png`});
        }
        await page.goto('http://localhost/audit/manager');
        assert.deepEqual(await page.locator('#rp-audience option').evaluateAll(els => els.map(e => e.value)), ['orang_tua', 'orang_tua_dan_siswa']);
        assert.equal(await page.locator('#rp-audience').inputValue(), 'orang_tua');
        await page.getByRole('button', {name: 'Pilih semua yang siap'}).click();
        assert.equal(await page.locator('[data-rp-select]:checked').count(), 3);
        assert.equal(await page.locator('[data-rp-row].is-selected').count(), 3);
        assert.equal(await page.locator('[data-rp-field]:enabled').count(), 9);
        await page.locator('#rp-behavior').check();
        await page.getByRole('button', {name: 'Pilih semua yang siap'}).click();
        assert.equal(await page.locator('[data-rp-select]:checked').count(), 0);
        await page.locator('#rp-search').fill('Alya');
        assert.equal(await page.locator('[data-rp-row]:visible').count(), 1);
        await page.locator('#rp-search').fill('');
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Manager overflow ${width}`);
        if ([390,1366].includes(width)) await page.screenshot({path: `${output}/manager-${width}.png`, fullPage: true});
        await page.goto('http://localhost/audit/manager-active');
        await page.getByRole('button', {name: 'Pilih yang terpublikasi'}).click();
        assert.equal(await page.locator('[data-rp-select]:checked').count(), 3);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Active manager overflow ${width}`);
        if ([390,1366].includes(width)) await page.screenshot({path: `${output}/manager-active-${width}.png`, fullPage: true});
        await page.goto('http://localhost/audit/index');
        assert.equal(await page.getByRole('link', {name: 'Lihat rapor'}).count(), 3);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Recipient overflow ${width}`);
        await page.screenshot({path: `${output}/index-${width}.png`, fullPage: true});
    }
    await page.goto('http://localhost/audit/print');
    assert.equal(await page.locator('.draft').count(), 0);
    assert.ok(await page.getByRole('link', {name: 'Kembali ke daftar rapor'}).isVisible());
    assert.ok(await page.locator('img').evaluateAll(els => els.every(e => e.complete && e.naturalWidth > 0)));
    await page.emulateMedia({media: 'print'});
    const pdf = await page.pdf({path: `${output}/published-web.pdf`, preferCSSPageSize: true, printBackground: true, displayHeaderFooter: false});
    assert.equal((await PDFDocument.load(pdf)).getPageCount(), 1);
    await page.locator('.sheet').screenshot({path: `${output}/published-web.png`});
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({result: 'passed', widths: [320,390,768,1366,1920], checks: ['colored-workflow', 'bound-save-buttons', 'collective-review', 'grade-dialog', 'pending-period', 'selected-row-color', 'two-audiences', 'safe-default', 'bulk-selection', 'behavior-ready-filter', 'search', 'no-overflow', 'logos', 'one-a4', 'published-not-draft']}));
} finally { await browser.close(); }
