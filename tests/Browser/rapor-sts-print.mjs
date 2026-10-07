// Fixtures: NUSA_CAPTURE_STS_PRINT=1 php vendor/phpunit/phpunit/phpunit tests/Feature/RaporStsTest.php
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const pdfLib = await import(process.env.PDF_LIB_MODULE ? pathToFileURL(process.env.PDF_LIB_MODULE).href : 'pdf-lib');
const browser = await playwright.chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/rapor-sts-print-audit');
await mkdir(output, {recursive: true});
try {
    const page = await browser.newPage(), errors = [], pages = {};
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/([a-z]+)$/)?.[1];
        if (name) return route.fulfill({contentType: 'text/html', body: await readFile(`storage/framework/testing/rapor-sts-print/${name}.html`, 'utf8')});
        const root = resolve('public'), path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try { return route.fulfill({body: await readFile(path), contentType: ({'.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.woff2': 'font/woff2'})[extname(path)] || 'application/octet-stream'}); }
        catch { return route.fulfill({status: 404, body: ''}); }
    });
    for (const width of [390, 1366]) {
        await page.setViewportSize({width, height: 1000});
        await page.goto('http://localhost/audit/index');
        assert.equal(await page.locator('[data-sts-print]').count(), 3);
        assert.equal(await page.locator('.sts-lateness').count(), 2);
        assert.ok(await page.locator('.sts-lateness').evaluateAll(els => els.every(el => {
            const attendance = el.parentElement.querySelector('.sts-attendance');
            return el.getBoundingClientRect().top >= attendance.getBoundingClientRect().bottom
                && el.scrollWidth <= el.clientWidth + 1 && !el.querySelector('input');
        })), `Rekap keterlambatan @${width}`);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Luapan rekap @${width}`);
        await page.locator('[data-sts-row]').first().scrollIntoViewIfNeeded();
        await page.screenshot({path: `${output}/index-${width}.png`});
        await page.goto('http://localhost/audit/individual');
        assert.ok(await page.getByRole('button', {name: 'Cetak / Simpan PDF'}).isVisible());
        assert.ok(await page.locator('.toolbar').evaluate(el => el.scrollWidth <= el.clientWidth + 1), `Luapan toolbar @${width}`);
        await page.screenshot({path: `${output}/individual-${width}.png`, fullPage: true});
    }
    await page.evaluate(() => { window.printCalls = 0; window.print = () => window.printCalls++; });
    await page.getByRole('button', {name: 'Cetak / Simpan PDF'}).click();
    assert.equal(await page.evaluate(() => window.printCalls), 1);

    await page.setViewportSize({width: 1366, height: 1000});
    for (const name of ['individual', 'class', 'dense', 'late', 'preview']) {
        const single = name === 'individual' || name === 'preview';
        const missing = name === 'individual' ? 10 : name === 'class' ? 20 : name === 'preview' ? 11 : 22;
        await page.goto(`http://localhost/audit/${name}`);
        await page.evaluate(() => document.fonts.ready);
        assert.ok(await page.locator('img').evaluateAll(els => els.every(el => el.complete && el.naturalWidth > 0)), `Logo ${name}`);
        assert.equal(await page.locator('.description').filter({hasText: /^Belum tersedia$/}).count(), missing);
        assert.deepEqual(await page.locator('.summary-row .score').allTextContents(), single ? ['-', '-'] : ['-', '-', '-', '-']);
        assert.deepEqual(await page.locator('[data-sts-late-count]').allTextContents(), name === 'late' ? ['0', '2'] : name === 'preview' ? ['2'] : single ? ['0'] : ['0', '0']);
        assert.deepEqual(await page.locator('[data-sts-late-minutes]').allTextContents(), name === 'late' ? ['0', '45'] : name === 'preview' ? ['45'] : single ? ['0'] : ['0', '0']);
        assert.ok(await page.locator('.attendance').evaluateAll(els => els.every(el => {
            const rows = el.querySelectorAll('.attendance-row');
            return rows.length === 2 && rows[1].getBoundingClientRect().top >= rows[0].getBoundingClientRect().bottom - 1
                && el.contains(el.querySelector('[data-sts-late-count]'))
                && [...el.querySelectorAll('.attendance-item')].every(item => {
                    const label = item.firstElementChild.getBoundingClientRect(), amount = item.lastElementChild.getBoundingClientRect();
                    return label.right <= amount.left + 1 && item.scrollWidth <= item.clientWidth + 1;
                });
        })), `Keterlambatan di bawah ketidakhadiran ${name}`);
        await page.emulateMedia({media: 'print'});
        assert.ok(await page.locator('.toolbar').isHidden());
        const overflow = await page.locator('.sheet').evaluateAll(els => els.map(el => {
            const footer = el.querySelector('.notes').getBoundingClientRect(), rect = el.getBoundingClientRect();
            return {overflow: el.scrollHeight > el.clientHeight + 1, footerClipped: footer.bottom > rect.bottom - parseFloat(getComputedStyle(el).paddingBottom) + 1};
        }));
        assert.ok(overflow.every(item => !item.overflow && !item.footerClipped), `Konten terpotong ${name}`);
        const pdf = await page.pdf({path: `${output}/${name}.pdf`, preferCSSPageSize: true, printBackground: true, displayHeaderFooter: false});
        const document = await pdfLib.PDFDocument.load(pdf);
        pages[name] = document.getPageCount();
        assert.equal(pages[name], single ? 1 : 2, `Satu halaman per siswa ${name}`);
        for (const sheet of document.getPages()) {
            assert.ok(Math.abs(sheet.getWidth() - 595.28) < 1 && Math.abs(sheet.getHeight() - 841.89) < 1, 'A4 portrait');
        }
        await page.locator('.sheet').first().screenshot({path: `${output}/${name}-print.png`});
        await page.emulateMedia({media: 'screen'});
    }
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({result: 'passed', pages, checks: ['partial-grades', 'print-links', 'desktop-mobile', 'print-button', 'logos', 'lateness-values', 'lateness-below-attendance', 'no-clipping', 'one-a4-per-student']}));
} finally { await browser.close(); }
