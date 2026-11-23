// NUSA_MATCHING_PREVIEW_FIXTURE=1 php artisan test --filter=SoalCbtTest
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { extname, resolve, sep } from 'node:path';
import { pathToFileURL } from 'node:url';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE
    ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const fixtures = new Map(await Promise.all(['bank', 'editor'].map(async name => [
    `/preview-${name}`, await readFile(`storage/framework/testing/matching-preview/${name}.html`, 'utf8'),
])));
const browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined });
const errors = [];
const prompts = ['LAN (Local Area Network)', 'WAN (Wide Area Network)', 'Topologi Star (Bintang)', 'Topologi Bus'];

async function assertMatchingLayout(body, context) {
    assert.deepEqual(await body.locator('.matching-row .option-text').allTextContents(), prompts);
    assert.equal(await body.locator('.matching-answer-option').count(), 6);
    const rows = await body.locator('.matching-row').evaluateAll(nodes => nodes.map(node => {
        const row = node.getBoundingClientRect();
        const content = node.firstElementChild.getBoundingClientRect();
        const text = node.querySelector('.option-text').getBoundingClientRect();
        const select = node.querySelector('select').getBoundingClientRect();
        return {
            separate: content.right <= select.left || content.bottom <= select.top,
            readable: content.width >= Math.min(200, row.width - 26),
            contained: content.left >= row.left && content.right <= row.right + 1
                && select.left >= row.left && select.right <= row.right + 1
                && text.right <= content.right + 1 && text.bottom <= content.bottom + 1,
            overflow: node.scrollWidth > node.clientWidth + 1,
        };
    }));
    assert.equal(rows.length, 4);
    for (const [index, row] of rows.entries()) {
        assert.ok(row.separate && row.readable && row.contained && !row.overflow,
            `Matching row ${index + 1} overlaps or clips at ${context}: ${JSON.stringify(row)}`);
    }
    assert.ok(await body.evaluate(node => node.scrollWidth <= node.clientWidth + 1), `Modal overflow: ${context}`);
    const select = body.locator('.matching-select').first();
    assert.equal(await select.locator('option').count(), 7);
    assert.equal(await select.locator('option').last().textContent(), 'F. 0');
    await select.selectOption({ index: 1 });
    assert.equal(await select.inputValue(), 'Menghubungkan perangkat dalam area terbatas seperti sekolah atau gedung.');
}

try {
    const page = await browser.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        assert.equal(route.request().method(), 'GET', 'Preview must not save or submit answers');
        if (fixtures.has(url.pathname)) {
            return route.fulfill({ contentType: 'text/html', body: fixtures.get(url.pathname) });
        }
        if (url.pathname === '/notifikasi/ringkasan') {
            return route.fulfill({ json: { jumlah_belum_dibaca: 0, notifikasi: [] } });
        }
        const root = resolve('public');
        const file = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(root + sep)) return route.abort();
        try {
            return route.fulfill({ body: await readFile(file), contentType: ({
                '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png',
                '.svg': 'image/svg+xml', '.woff2': 'font/woff2',
            })[extname(file)] || 'application/octet-stream' });
        } catch { return route.fulfill({ status: 404, body: '' }); }
    });

    for (const name of ['bank', 'editor']) {
        for (const width of [320, 390, 680, 768, 1024, 1366]) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto(`http://localhost/preview-${name}`);
            const button = page.locator('[data-question-preview]:visible').first();
            const formData = () => button.evaluate(node => {
                const form = node.closest('form');
                return form ? [...new FormData(form).entries()] : null;
            });
            const initial = await formData();
            await button.click();
            const dialog = page.locator('[data-question-preview-dialog]');
            await dialog.waitFor({ state: 'visible' });
            const body = dialog.locator('[data-question-preview-body]');
            await assertMatchingLayout(body, `${name}, ${width}px`);
            if (name === 'bank' && [390, 1366].includes(width)) {
                await body.locator('.matching-row').first().scrollIntoViewIfNeeded();
                await page.screenshot({ path: `storage/framework/testing/matching-preview/${name}-${width}.png` });
            }
            if (width === 1366) {
                await dialog.evaluate(node => { node.style.width = '430px'; });
                await assertMatchingLayout(body, `${name}, narrow modal on desktop`);
            }
            assert.deepEqual(await formData(), initial, 'Preview must not alter the question form');
            await dialog.getByRole('button', { name: 'Tutup', exact: true }).click();
            await button.click();
            assert.equal(await body.locator('.matching-select').first().inputValue(), '', 'Preview choices should reset');
            await page.keyboard.press('Escape');
            assert.ok(await page.evaluate(() => document.body.scrollWidth <= innerWidth + 1), `Page overflow: ${name}, ${width}px`);
        }
    }
    assert.deepEqual(errors, []);
    console.log('PASS: saved Bank Soal and editor previews at 6 viewport widths plus narrow desktop modals; no overlap, working choices including 0, no changes to question data.');
} finally { await browser.close(); }
