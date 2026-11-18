import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const fixtures = resolve('storage/framework/testing/tahfidz-kolektif');
const output = resolve('storage/logs/tahfidz-kolektif-qa');
const assets = resolve('public');
const errors = [];
const dialogs = [];
const submissions = [];
let screenshots = 0;

try {
    await mkdir(output, {recursive: true});
    const page = await browser.newPage();
    page.on('pageerror', error => errors.push(error.message));
    page.on('dialog', dialog => {
        dialogs.push(dialog.message());
        return dialog.accept();
    });
    await page.route('**/*', async route => {
        const request = route.request();
        const url = new URL(request.url());
        assert.equal(url.hostname, 'localhost');
        if (request.method() === 'POST') {
            assert.match(url.pathname, /^\/tahfidz\/\d+\/pertemuan\/\d+\/kolektif$/);
            submissions.push(new URLSearchParams(request.postData()));
            return route.fulfill({contentType: 'text/html', body: '<title>Batch received</title><p>Batch received</p>'});
        }
        const fixture = url.pathname.match(/^\/_tf-batch-qa\/([a-z]+)$/)?.[1];
        if (fixture) return route.fulfill({contentType: 'text/html', body: await readFile(resolve(fixtures, fixture + '.html'))});
        if (url.pathname === '/notifikasi/ringkasan') return route.fulfill({json: {jumlah_belum_dibaca: 0, notifikasi: []}});
        const asset = resolve(assets, '.' + decodeURIComponent(url.pathname));
        assert.ok(asset.startsWith(assets + sep));
        const types = {'.css': 'text/css', '.js': 'text/javascript', '.svg': 'image/svg+xml', '.png': 'image/png', '.webp': 'image/webp', '.woff2': 'font/woff2'};
        try {
            return route.fulfill({contentType: types[extname(asset)] || 'application/octet-stream', body: await readFile(asset)});
        } catch {
            return route.fulfill({status: 404, body: ''});
        }
    });
    for (const [width, height] of [[320, 740], [390, 844], [768, 1024], [1024, 768], [1440, 900]]) {
        await page.setViewportSize({width, height});
        for (const fixture of ['empty', 'table', 'student', 'reference', 'invalid', 'full']) {
            await page.goto('http://localhost/_tf-batch-qa/' + fixture);
            const root = page.locator('.tf-page');
            await root.waitFor();
            assert.ok(await root.evaluate(el => el.scrollWidth <= el.clientWidth + 1), width + ' ' + fixture + ' page overflow');
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            assert.ok(await page.evaluate(() => document.body.scrollWidth <= innerWidth + 1), width + ' ' + fixture + ' body overflow');
            assert.equal(await page.locator('[data-sidebar-item][data-sidebar-search-text="akademik penilaian tahfidz"][aria-current="page"]').count(), 1);
            if (fixture === 'empty') {
                assert.equal(await root.locator('input[name="surah[]"]').count(), 37);
                await root.getByText('112. Al-Ikhlas', {exact: true}).click();
                assert.equal(await root.locator('[data-tf-surah-count]').textContent(), '1/37');
            } else {
                const table = root.locator('.tf-kolektif-table');
                const cells = root.locator('[data-tf-batch-cell]');
                const save = root.getByRole('button', {name: 'Simpan kolektif', exact: true});
                assert.ok(await save.isVisible());
                assert.equal(await cells.count(), fixture === 'full' ? 555 : fixture === 'student' ? 2 : 6);
                if (fixture === 'reference') {
                    assert.ok(await root.getByText('90,25', {exact: true}).count() > 0);
                    assert.equal(await cells.first().inputValue(), '');
                    await cells.first().fill('0');
                    await root.getByRole('button', {name: 'Selesaikan pertemuan', exact: true}).click();
                    assert.ok(dialogs.includes('Masih ada nilai yang belum disimpan. Simpan kolektif terlebih dahulu.'));
                    assert.equal(submissions.length, 0);
                    await cells.first().fill('');
                }
                if (fixture === 'full') {
                    const region = root.getByRole('region', {name: 'Nilai setoran baru', exact: true});
                    assert.ok(await region.evaluate(el => el.scrollWidth > el.clientWidth));
                    await region.evaluate(el => { el.scrollLeft = 300; });
                    assert.ok(await table.locator('.tf-kolektif-student').first().evaluate(el => {
                        const parent = el.closest('[role="region"]').getBoundingClientRect();
                        return Math.abs(el.getBoundingClientRect().left - parent.left) < 3;
                    }));
                }
                if (fixture === 'table') {
                    assert.ok(await save.isDisabled());
                    await cells.first().fill('0');
                    assert.equal(await root.locator('[data-tf-batch-count]').textContent(), '1 setoran baru');
                    assert.ok(await save.isEnabled());
                    await cells.nth(1).fill('101');
                    assert.equal(await cells.nth(1).evaluate(el => el.checkValidity()), false);
                    await cells.nth(1).fill('90,25');
                    assert.equal(await cells.nth(1).evaluate(el => el.checkValidity()), true);
                    await cells.first().focus();
                    await cells.first().press('Enter');
                    assert.ok(await cells.nth(1).evaluate(el => el === document.activeElement));
                    await cells.first().fill('');
                    await cells.nth(1).fill('');
                    assert.ok(await save.isDisabled());
                }
            }
            const clipped = await root.locator('h1, h2, .button, .tf-kolektif-reference').evaluateAll(elements => elements
                .filter(el => el.clientWidth && el.scrollWidth > el.clientWidth + 2).map(el => el.textContent));
            assert.deepEqual(clipped, [], width + ' ' + fixture + ' clipped text');
            const png = await page.screenshot({path: resolve(output, fixture + '-' + width + '.png'), fullPage: true});
            assert.equal(png.readUInt32BE(16), width, width + ' ' + fixture + ' screenshot overflow');
            screenshots++;
        }
    }
    await page.goto('http://localhost/_tf-batch-qa/table');
    const fields = page.locator('[data-tf-batch-cell]');
    await fields.first().fill('0');
    await fields.nth(1).fill('90,25');
    await page.getByRole('button', {name: 'Simpan kolektif'}).click();
    await page.getByText('Batch received', {exact: true}).waitFor();
    assert.equal(submissions.length, 1);
    assert.equal(submissions[0].getAll('surah[]').length, 3);
    const values = [...submissions[0]].filter(([key]) => key.startsWith('nilai[')).map(([, value]) => value);
    assert.deepEqual(values, ['0', '90,25', '', '', '', '']);
    assert.ok(submissions[0].get('token_batch'));
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({screenshots, submissions: submissions.length, errors}));
} finally {
    await browser.close();
}
