// Export the isolated Blade fixture with CBT_PHONE_FIXTURE=1 php artisan test --filter=test_paket_simulasi.
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve, extname, sep } from 'node:path';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const html = await readFile('storage/logs/cbt-phone-audit.html', 'utf8');
const browser = await chromium.launch({ headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || undefined });
try {
    const page = await browser.newPage({ viewport:{ width:390, height:844 }, isMobile:true, hasTouch:true });
    let mode = 'normal', unblock;
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        if (route.request().method() === 'POST') {
            if (mode === 'offline') return route.abort('internetdisconnected');
            if (mode === 'slow') await new Promise(resolve => { unblock = resolve; });
            return route.fulfill({ json:{ terjawab:true, ragu:false, tersimpan_pada:'10:00:00' } }).catch(() => {});
        }
        if (new URL(route.request().url()).pathname === '/audit') return route.fulfill({ contentType:'text/html', body:html });
        const root = resolve('public');
        const file = resolve(root, '.' + decodeURIComponent(new URL(route.request().url()).pathname));
        if (!file.startsWith(root + sep)) return route.abort();
        try {
            await route.fulfill({ body:await readFile(file), contentType:({ '.js':'text/javascript', '.css':'text/css', '.jpg':'image/jpeg', '.png':'image/png', '.woff2':'font/woff2' })[extname(file)] || 'application/octet-stream' });
        } catch { await route.fulfill({ status:404, body:'' }); }
    });
    await page.goto('http://localhost/audit');
    const go = async index => {
        await page.locator('button[data-question-index]').nth(index).evaluate(button => button.click());
        await page.waitForTimeout(100);
    };
    for (const width of [320, 360, 390, 412, 768, 1366]) {
        await page.setViewportSize({ width, height:844 });
        for (let index = 0; index < 12; index++) {
            await go(index);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Overflow: width ${width}, question ${index + 1}`);
        }
    }
    await page.setViewportSize({ width:390, height:844 });
    await go(11);
    assert.ok(await page.locator('.question-card:not([hidden]) .question-media-table-scroll').evaluate(node => node.scrollWidth > node.clientWidth));
    await page.screenshot({ path:'storage/logs/cbt-phone-fixed.png', fullPage:true });
    await go(0);
    const singleChoices = page.locator('.question-card:not([hidden]) .option-card input[type=radio]');
    for (let index = 0; index < await singleChoices.count(); index++) {
        await singleChoices.nth(index).check();
        assert.equal(await page.locator('.question-card:not([hidden]) .option-card input:checked').count(), 1);
        assert.ok(await singleChoices.nth(index).isChecked());
    }
    await page.waitForTimeout(500);
    mode = 'slow';
    await page.locator('.question-card:not([hidden]) input[type=radio]').first().evaluate(input => input.click());
    await page.waitForTimeout(250);
    await page.locator('#nextQuestion').click();
    assert.equal(await page.locator('#questionPosition').textContent(), 'Soal 2 dari 12');
    await page.waitForTimeout(10200);
    assert.match(await page.locator('.answer-save-state').textContent(), /Koneksi lambat/);
    mode = 'normal'; unblock();
    await page.locator('#retrySave').click();
    await page.waitForTimeout(300);
    assert.equal(await page.locator('.question-card').first().getAttribute('data-dirty'), '0');
    for (const index of [4, 6]) {
        await go(index);
        const card = page.locator('.question-card:not([hidden])');
        if (index === 4) await card.locator('input[type=radio]').first().evaluate(input => input.click());
        else await card.locator('select').first().selectOption({ index:1 });
        await page.waitForTimeout(300);
        assert.equal(await card.getAttribute('data-answered'), '0');
        assert.equal(await card.getAttribute('data-partial'), '1');
        if (index === 4) await card.locator('.statement-row').nth(1).locator('input[type=radio]').first().evaluate(input => input.click());
        else await card.locator('select').nth(1).selectOption({ index:2 });
        await page.waitForTimeout(300);
        assert.equal(await card.getAttribute('data-answered'), '1');
        assert.equal(await card.getAttribute('data-partial'), '0');
    }
    assert.deepEqual(errors, []);
    console.log('PASS: 72 viewport/question checks; table scroll; immediate navigation; timeout and retry; partial/complete statements and matching.');
} finally { await browser.close(); }
