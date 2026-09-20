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
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/soal-cbt') return route.fulfill({ contentType:'text/html', body:html });
        const root = resolve('public');
        const file = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(root + sep)) return route.abort();
        try {
            return route.fulfill({ body:await readFile(file), contentType:({ '.js':'text/javascript', '.css':'text/css', '.png':'image/png', '.woff2':'font/woff2' })[extname(file)] || 'application/octet-stream' });
        } catch { return route.fulfill({ status:404, body:'' }); }
    });
    await page.goto('http://localhost/soal-cbt');
    for (const width of [390, 768, 1024, 1366, 1600]) {
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
        if (width === 1366) await page.screenshot({ path:'storage/logs/cbt-bank-summary.png', fullPage:true });
    }
    assert.deepEqual(errors, []);
    console.log('PASS: four equal cards, correct totals, no clipped cards at five viewport widths.');
} finally { await browser.close(); }
