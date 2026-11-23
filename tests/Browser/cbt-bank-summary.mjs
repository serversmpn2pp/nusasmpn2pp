// CBT_BANK_FIXTURE=1 php artisan test --filter=SoalCbtTest
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve, extname, sep } from 'node:path';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await chromium.launch({ headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || undefined });
try {
    const page = await browser.newPage();
    const html = await readFile('storage/logs/cbt-bank-summary.html', 'utf8');
    const oldestHtml = await readFile('storage/logs/cbt-bank-terlama.html', 'utf8');
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/soal-cbt') return route.fulfill({
            contentType: 'text/html',
            body: url.searchParams.get('urutan') === 'terlama' ? oldestHtml : html,
        });
        const root = resolve('public');
        const file = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(root + sep)) return route.abort();
        try {
            return route.fulfill({ body:await readFile(file), contentType:({ '.js':'text/javascript', '.css':'text/css', '.png':'image/png', '.woff2':'font/woff2' })[extname(file)] || 'application/octet-stream' });
        } catch { return route.fulfill({ status:404, body:'' }); }
    });
    await page.goto('http://localhost/soal-cbt');
    for (const width of [320, 390, 768, 1024, 1280, 1366, 1600]) {
        await page.setViewportSize({ width, height:900 });
        const cards = await page.locator('.bank-summary .stat').evaluateAll(nodes => nodes.map(node => {
            const rect = node.getBoundingClientRect();
            return { x:rect.x, right:rect.right, width:rect.width, height:rect.height, overflow:node.scrollWidth > node.clientWidth + 1 };
        }));
        assert.equal(cards.length, 4);
        for (const card of cards) {
            assert.ok(card.x >= 0 && card.right <= width + 1 && !card.overflow, `Bounds at ${width}`);
            assert.ok(Math.abs(card.width - cards[0].width) < 1 && Math.abs(card.height - cards[0].height) < 1, `Unequal cards at ${width}`);
        }
        assert.deepEqual(await page.locator('.bank-summary .stat-value').allTextContents(), ['15', '13', '1', '15']);
        const filtersFit = await page.locator('.soal-filter-grid').evaluate(grid => {
            const bounds = grid.getBoundingClientRect();
            return [...grid.children].every(field => {
                const box = field.getBoundingClientRect();
                return box.left >= bounds.left - 1 && box.right <= Math.min(bounds.right + 1, innerWidth);
            });
        });
        assert.ok(filtersFit, `Bank filters overflow at ${width}px`);
        if (width === 1366) await page.screenshot({ path:'storage/logs/cbt-bank-summary.png', fullPage:true });
    }
    await page.locator('[data-question-preview]').first().click();
    const radios = page.locator('[data-question-preview-body] .option-card input[type=radio]');
    assert.ok(await radios.count() >= 2);
    for (let index = 0; index < await radios.count(); index++) {
        await radios.nth(index).check();
        assert.equal(await page.locator('[data-question-preview-body] .option-card input:checked').count(), 1);
        assert.ok(await radios.nth(index).isChecked());
    }
    await page.locator('[data-close-question-preview]').click();
    await page.locator('[data-question-preview]').first().click();
    assert.equal(await page.locator('[data-question-preview-body] .option-card input:checked').count(), 0);
    await page.locator('[data-close-question-preview]').click();
    // Exercise the complex-choice renderer using the same options in the isolated fixture.
    await page.locator('[data-question-preview]').first().evaluate(button => {
        document.getElementById(button.dataset.previewSource).content.querySelector('[data-soal-kind]').value = 'pilihan_ganda_kompleks';
    });
    await page.locator('[data-question-preview]').first().click();
    const checks = page.locator('[data-question-preview-body] .option-card input[type=checkbox]');
    await checks.nth(0).check();
    await checks.nth(1).check();
    assert.equal(await page.locator('[data-question-preview-body] .option-card input:checked').count(), 2);
    await page.locator('[data-close-question-preview]').click();

    const sort = page.getByLabel('Urutkan soal');
    const firstCode = page.locator('.desktop-only tbody .person-name').first();
    assert.equal(await sort.inputValue(), 'terbaru');
    assert.equal(await firstCode.textContent(), 'RINGKASAN-3');
    await sort.selectOption('terlama');
    await page.waitForURL(url => url.searchParams.get('urutan') === 'terlama');
    const query = new URL(page.url()).searchParams;
    assert.ok(query.get('mata_pelajaran_id'));
    assert.equal(query.get('tingkat'), '8');
    assert.equal(query.get('folder'), 'semua');
    assert.equal(await sort.inputValue(), 'terlama');
    assert.equal(await firstCode.textContent(), 'SOAL-CBT-UJI-001');
    assert.match(await page.getByRole('link', { name: 'Berikutnya', exact: true }).getAttribute('href'), /urutan=terlama/);
    await sort.selectOption('terbaru');
    await page.waitForURL(url => url.searchParams.get('urutan') === 'terbaru');
    assert.equal(await firstCode.textContent(), 'RINGKASAN-3');
    assert.deepEqual(errors, []);
    console.log('PASS: Bank Soal summary, query-aware sorting, preview, and filters at seven viewport widths.');
} finally { await browser.close(); }
