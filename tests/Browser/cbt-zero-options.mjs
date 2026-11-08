// Fixtures: CBT_ZERO_FIXTURE=1 php artisan test --filter=SoalCbtTest
import assert from 'node:assert/strict';
import {mkdir, readFile} from 'node:fs/promises';
import {extname, resolve, sep} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const root = resolve('public');
const output = resolve('storage/logs/cbt-zero-qa');
try {
    await mkdir(output, {recursive: true});
    const page = await browser.newPage({serviceWorkers: 'block'});
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        assert.equal(url.hostname, 'localhost');
        assert.equal(route.request().method(), 'GET', 'Pratinjau tidak boleh mengirim jawaban');
        const fixture = url.pathname === '/soal-cbt/create' ? 'form' : url.pathname === '/soal-cbt' ? 'bank' : null;
        if (fixture) return route.fulfill({contentType: 'text/html', body: await readFile(`storage/framework/testing/cbt-zero/${fixture}.html`)});
        if (url.pathname === '/notifikasi/ringkasan') return route.fulfill({json: {jumlah_belum_dibaca: 0, notifikasi: []}});
        const path = resolve(root, '.' + decodeURIComponent(url.pathname));
        assert.ok(path.startsWith(root + sep));
        const types = {'.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.woff2': 'font/woff2', '.svg': 'image/svg+xml'};
        const body = await readFile(path).catch(() => null);
        return body ? route.fulfill({contentType: types[extname(path)] || 'application/octet-stream', body}) : route.fulfill({status: 404});
    });

    for (const width of [320, 390, 768, 1366]) {
        await page.setViewportSize({width, height: 900});
        await page.goto('http://localhost/soal-cbt');
        const selector = `${width <= 900 ? '.mobile-only' : '.desktop-only'} [data-question-preview]`;
        const buttons = page.locator(selector);
        assert.equal(await buttons.count(), 2);
        for (let i = 0; i < 2; i++) {
            const button = buttons.nth(i);
            await button.click();
            const dialog = page.locator('[data-question-preview-dialog]');
            await dialog.waitFor({state: 'visible'});
            const options = dialog.locator('.option-card');
            assert.equal(await options.count(), 4);
            assert.deepEqual(await options.locator('.option-code').allTextContents(), ['A', 'B', 'C', 'D']);
            assert.equal(await options.first().locator('.option-text').textContent(), '0');
            await options.first().locator('input').check();
            assert.equal(await options.first().locator('input').inputValue(), 'A');
            assert.ok(await dialog.evaluate(el => {
                const r = el.getBoundingClientRect();
                return r.left >= 0 && r.right <= innerWidth + 1 && r.top >= 0 && r.bottom <= innerHeight + 1;
            }));
            if ([390, 1366].includes(width)) await page.screenshot({path: resolve(output, `bank-${i}-${width}.png`)});
            await dialog.getByRole('button', {name: 'Tutup', exact: true}).click();
        }

        await page.goto('http://localhost/soal-cbt/create');
        const dialog = page.locator('[data-question-preview-dialog]');
        await page.locator('textarea[name="opsi[A]"] ~ .inline-equation-editor').waitFor();
        for (const [code, text] of [['A', '0'], ['B', '1'], ['C', 'Tidak berubah'], ['D', 'Bergantung pada input kedua']]) {
            await page.locator(`textarea[name="opsi[${code}]"] ~ .inline-equation-editor`).fill(text);
        }
        for (const type of ['pilihan_ganda', 'pilihan_ganda_kompleks']) {
            await page.locator(`[data-soal-kind][value="${type}"]`).check();
            await page.getByRole('button', {name: 'Pratinjau soal', exact: true}).click();
            await dialog.waitFor({state: 'visible'});
            assert.equal(await dialog.locator('.option-card').count(), 4);
            assert.equal(await dialog.locator('.option-text').first().textContent(), '0');
            await dialog.getByRole('button', {name: 'Tutup', exact: true}).click();
        }
    }
    assert.deepEqual(errors, []);
    console.log('CBT angka nol: pratinjau formulir dan soal tersimpan PG/PGK lolos pada 4 ukuran layar.');
} finally {
    await browser.close();
}
