// ASESMEN_PREVIEW_FIXTURE=1 php artisan test --filter=AsesmenKelasCbtTest
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve, extname, sep } from 'node:path';
import { pathToFileURL } from 'node:url';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE
    ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const html = await readFile('storage/logs/asesmen-preview.html', 'utf8');
const browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined });
const errors = [];
const types = ['pilihan_ganda', 'pilihan_ganda_kompleks', 'benar_salah', 'menjodohkan', 'isian_singkat', 'numerik', 'uraian', 'upload_file'];

try {
    const page = await browser.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (/^\/ujian-cbt\/\d+\/soal$/.test(url.pathname)) {
            assert.equal(route.request().method(), 'GET', 'Preview must not submit the form');
            return route.fulfill({ contentType: 'text/html', body: html });
        }
        const root = resolve('public');
        const file = url.pathname.endsWith('/soal-cbt/preview-fixture.png')
            ? resolve(root, 'images/logo-nusa.png')
            : resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(root + sep)) return route.abort();
        try {
            return route.fulfill({ body: await readFile(file), contentType: ({
                '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.woff2': 'font/woff2',
            })[extname(file)] || 'application/octet-stream' });
        } catch { return route.fulfill({ status: 404, body: '' }); }
    });

    for (const width of [320, 390, 768, 1366]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('http://localhost/ujian-cbt/1/soal');
        const layout = page.locator(`[data-form-layout="${width <= 900 ? 'mobile' : 'desktop'}"]`);
        const form = page.locator('form').filter({ has: layout });
        const dialog = page.locator('[data-question-preview-dialog]');
        const body = dialog.locator('[data-question-preview-body]');
        await page.evaluate(() => {
            window.previewSubmits = 0;
            document.querySelector('[data-form-layout]').closest('form').addEventListener('submit', event => {
                event.preventDefault();
                window.previewSubmits++;
            });
        });
        const questionId = await layout.getByRole('button', { name: 'Pratinjau soal REVIEW-uraian', exact: true })
            .getAttribute('data-preview-source');
        const id = questionId.replace('preview-soal-', '');
        await layout.locator(`[name="soal[${id}][dipilih]"][type=checkbox]`).check();
        await layout.locator(`[name="soal[${id}][nomor_urut]"]`).fill('27');
        assert.equal(await page.locator('[data-selected-count]').textContent(), '3');
        const initialData = await form.evaluate(node => [...new FormData(node).entries()]);

        for (const type of types) {
            const button = layout.getByRole('button', { name: `Pratinjau soal REVIEW-${type}`, exact: true });
            await button.click();
            await dialog.waitFor({ state: 'visible' });
            assert.equal(await body.locator('.question-title').count(), 1);
            assert.ok(await body.locator('.katex').count() >= 3, `Math missing: ${type}, ${width}`);
            assert.equal(await body.locator('.question-media-table').count(), 1);
            const image = body.getByAltText('Ilustrasi operasi bilangan');
            await image.evaluate(img => img.decode());
            assert.ok(await image.evaluate(img => {
                const canvas = document.createElement('canvas');
                canvas.width = canvas.height = 32;
                const context = canvas.getContext('2d');
                context.drawImage(img, 0, 0, 32, 32);
                const data = context.getImageData(0, 0, 32, 32).data;
                return new Set(Array.from({ length: 1024 }, (_, i) => data.slice(i * 4, i * 4 + 4).join(','))).size > 10;
            }), `Blank image: ${type}, ${width}`);
            assert.ok(await dialog.evaluate(el => {
                const rect = el.getBoundingClientRect();
                return rect.left >= 0 && rect.right <= innerWidth + 1 && rect.top >= 0 && rect.bottom <= innerHeight + 1
                    && el.scrollWidth <= el.clientWidth + 1;
            }), `Dialog overflow: ${type}, ${width}`);

            if (type === 'pilihan_ganda' || type === 'pilihan_ganda_kompleks') {
                const answers = body.locator('.option-card input');
                assert.equal(await answers.count(), 4);
                await answers.nth(0).check();
                await answers.nth(1).check();
                assert.equal(await body.locator('.option-card input:checked').count(), type === 'pilihan_ganda' ? 1 : 2);
            } else if (type === 'benar_salah') {
                assert.equal(await body.locator('.statement-row').count(), 2);
                await body.locator('.statement-row').first().getByText('Benar', { exact: true }).click();
            } else if (type === 'menjodohkan') {
                assert.equal(await body.locator('.matching-row').count(), 2);
                assert.equal(await body.locator('.matching-answer-option').count(), 3);
                await body.locator('.matching-select').first().selectOption('8');
            } else if (type === 'uraian') {
                await body.locator('textarea').fill('Jawaban percobaan, bukan jawaban siswa.');
            } else if (type === 'isian_singkat' || type === 'numerik') {
                await body.locator('.field input').fill('4');
            } else {
                assert.equal(await body.locator('.file-answer-box').count(), 1);
            }

            if (width === 1366 && type === 'pilihan_ganda') {
                await page.screenshot({ path: 'storage/logs/asesmen-preview-desktop.png' });
            }
            if (width === 390 && type === 'menjodohkan') {
                await page.screenshot({ path: 'storage/logs/asesmen-preview-mobile.png' });
            }
            assert.deepEqual(await form.evaluate(node => [...new FormData(node).entries()]), initialData);
            if (type === 'benar_salah') await page.keyboard.press('Escape');
            else await dialog.getByRole('button', { name: 'Tutup', exact: true }).click();
            await dialog.waitFor({ state: 'hidden' });
            assert.ok(await button.evaluate(el => el === document.activeElement), 'Focus should return to preview button');
        }

        const pgButton = layout.getByRole('button', { name: 'Pratinjau soal REVIEW-pilihan_ganda', exact: true });
        await pgButton.click();
        assert.equal(await body.locator('.option-card input:checked').count(), 0, 'Preview answers should reset');
        await page.keyboard.press('Escape');
        await page.locator('[data-exam-folder-filter]').selectOption('belum');
        const countBefore = await page.locator('[data-selected-count]').textContent();
        await layout.getByRole('button', { name: 'Pratinjau soal REVIEW-uraian', exact: true }).click();
        await dialog.getByRole('button', { name: 'Tutup', exact: true }).click();
        assert.equal(await page.locator('[data-selected-count]').textContent(), countBefore);
        await page.locator('[data-exam-folder-filter]').selectOption('');
        assert.deepEqual(await form.evaluate(node => [...new FormData(node).entries()]), initialData);
        assert.equal(await page.evaluate(() => window.previewSubmits), 0);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Page overflow at ${width}`);
        if (width === 1366) {
            await layout.getByRole('button', { name: 'Pratinjau soal REVIEW-pilihan_ganda', exact: true }).scrollIntoViewIfNeeded();
            await page.screenshot({ path: 'storage/logs/asesmen-preview-list-desktop.png' });
        }
        if (width === 390) {
            await layout.getByRole('button', { name: 'Pratinjau soal REVIEW-pilihan_ganda', exact: true }).scrollIntoViewIfNeeded();
            await page.screenshot({ path: 'storage/logs/asesmen-preview-list-mobile.png' });
        }
    }
    assert.deepEqual(errors, []);
    console.log('PASS: 8 question types x 4 viewports; math, images, tables, answer controls, focus, filters, no lost selections or form submissions.');
} finally { await browser.close(); }
