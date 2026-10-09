// Fixtures: NUSA_CAPTURE_PERILAKU_STS=1 php vendor/phpunit/phpunit/phpunit tests/Feature/LampiranPerilakuStsTest.php
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const pdfLib = await import(process.env.PDF_LIB_MODULE ? pathToFileURL(process.env.PDF_LIB_MODULE).href : 'pdf-lib');
const browser = await playwright.chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/perilaku-sts-audit');
await mkdir(output, {recursive: true});
try {
    const page = await browser.newPage(), errors = [], pages = {};
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/([a-z]+)$/)?.[1];
        if (name) return route.fulfill({contentType: 'text/html', body: await readFile(`storage/framework/testing/perilaku-sts/${name}.html`, 'utf8')});
        const root = resolve('public'), path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try { return route.fulfill({body: await readFile(path), contentType: ({'.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.woff2': 'font/woff2'})[extname(path)] || 'application/octet-stream'}); }
        catch { return route.fulfill({status: 404, body: ''}); }
    });
    for (const width of [360, 768, 1366]) {
        await page.setViewportSize({width, height: 1000});
        await page.goto('http://localhost/audit/review');
        const student = page.locator('[data-behavior-student]').first();
        await student.evaluate(el => { el.open = true; });
        const form = student.locator('form');
        assert.equal(await form.evaluate(el => el.checkValidity()), false, 'Pemeriksaan wajib dikonfirmasi');
        await form.locator('input[name=diperiksa]').check();
        assert.equal(await form.evaluate(el => el.checkValidity()), true);
        assert.equal(await form.locator('select[name=guru_bk_id] option:checked').textContent(), 'Guru BK Tujuh');
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Luapan review @${width}`);
        await page.screenshot({path: `${output}/review-${width}.png`, fullPage: true});
        await page.goto('http://localhost/audit/index');
        assert.equal(await page.locator('a').filter({hasText: /^Rapor \+ perilaku$/}).count(), 2);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Luapan rekap @${width}`);
        await page.screenshot({path: `${output}/index-${width}.png`});
    }
    await page.setViewportSize({width: 1366, height: 1000});
    for (const name of ['individual', 'class', 'preview', 'long']) {
        await page.goto(`http://localhost/audit/${name}`);
        await page.evaluate(() => document.fonts.ready);
        assert.ok(await page.locator('img').evaluateAll(els => els.every(el => el.complete && el.naturalWidth > 0)), `Logo ${name}`);
        const sheets = await page.locator('.sheet').count();
        assert.equal(await page.locator('[data-behavior-key]').count(), name === 'long' ? 28 : 1, `Tidak ada kasus terpotong ${name}`);
        if (name === 'class' || name === 'preview') {
            assert.deepEqual(await page.locator('.sheet').evaluateAll(els => els.map(el => el.hasAttribute('data-behavior-sheet'))), [false, true, false, true]);
        }
        await page.emulateMedia({media: 'print'});
        assert.ok(await page.locator('.toolbar').isHidden());
        const bad = await page.locator('[data-behavior-sheet]').evaluateAll(els => els.map(el => {
            const rect = el.getBoundingClientRect(), style = getComputedStyle(el), footer = el.querySelector('.notes').getBoundingClientRect();
            return {height: rect.height, footer: footer.bottom - rect.top, capacity: 297 * 96 / 25.4,
                clipped: footer.bottom > rect.bottom - parseFloat(style.paddingBottom) + 1};
        }).filter(el => el.clipped || el.height > el.capacity + 1));
        assert.deepEqual(bad, [], `Isi lampiran harus muat A4 ${name}`);
        const pdf = await page.pdf({path: `${output}/${name}.pdf`, preferCSSPageSize: true, printBackground: true, displayHeaderFooter: false});
        const document = await pdfLib.PDFDocument.load(pdf);
        pages[name] = document.getPageCount();
        assert.equal(pages[name], sheets, `Tidak ada halaman tambahan/terpotong ${name}`);
        assert.equal(pages[name], name === 'individual' ? 2 : name === 'long' ? sheets : 4);
        for (const sheet of document.getPages()) {
            assert.ok(Math.abs(sheet.getWidth() - 595.28) < 1 && Math.abs(sheet.getHeight() - 841.89) < 1, 'A4 portrait');
        }
        await page.locator('[data-behavior-sheet]').first().screenshot({path: `${output}/${name}-print.png`});
        await page.emulateMedia({media: 'screen'});
    }
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({result: 'passed', pages, checks: ['BK-confirmation', 'grade-signers', 'desktop-mobile', 'parent-safe-summary', 'logos', 'student-page-order', 'long-cases', 'no-clipping', 'A4']}));
} finally { await browser.close(); }
