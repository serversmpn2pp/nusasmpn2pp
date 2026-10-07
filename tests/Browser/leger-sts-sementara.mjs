// Fixtures: NUSA_CAPTURE_LEGER_SEMENTARA=1 php vendor/phpunit/phpunit/phpunit tests/Feature/RaporStsTest.php
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const pdfLib = await import(process.env.PDF_LIB_MODULE ? pathToFileURL(process.env.PDF_LIB_MODULE).href : 'pdf-lib');
const browser = await playwright.chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/leger-sts-sementara-audit');
await mkdir(output, {recursive: true});
try {
    const page = await browser.newPage(), errors = [], pages = {};
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/([a-z]+)$/)?.[1];
        if (name) return route.fulfill({contentType: 'text/html', body: await readFile(`storage/framework/testing/leger-sts-sementara/${name}.html`, 'utf8')});
        const root = resolve('public'), path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try { return route.fulfill({body: await readFile(path), contentType: ({'.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.woff2': 'font/woff2'})[extname(path)] || 'application/octet-stream'}); }
        catch { return route.fulfill({status: 404, body: ''}); }
    });
    for (const width of [360, 768, 1366]) {
        await page.setViewportSize({width, height: 1000});
        for (const name of ['kelas', 'tingkat']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Luapan halaman ${name} @${width}`);
            await page.locator('[data-leger-calculation]').scrollIntoViewIfNeeded();
            assert.match(await page.locator('[data-leger-calculation]').innerText(), /Ranking sementara/);
            assert.match(await page.locator('[data-leger-calculation]').innerText(), name === 'kelas' ? /dibagi 11 mapel/ : /dibagi 2 mapel/);
            await page.screenshot({path: `${output}/${name}-${width}-summary.png`});
            const rows = page.locator('[data-leger-row]');
            assert.equal(await rows.count(), name === 'kelas' ? 2 : 5);
            const rank = await rows.locator('.rank-column').allTextContents();
            assert.deepEqual(rank.map(s => s.trim()), name === 'kelas' ? ['1', '-'] : ['1', '2', '2', '4', '-']);
            assert.match(await rows.first().locator('.status-column').innerText(), name === 'kelas' ? /8\/11 mapel/ : /2\/2 mapel/);
            assert.equal(await page.locator('.leger-draft').count(), name === 'kelas' ? 0 : 1);
            const results = await rows.first().locator('.result-column').allTextContents();
            assert.deepEqual(results.slice(-2).map(s => s.trim()), name === 'kelas' ? ['640,00', '58,18'] : ['180,00', '90,00']);
            const emptyResults = await rows.last().locator('.result-column').allTextContents();
            assert.deepEqual(emptyResults.slice(-2).map(s => s.trim()), ['-', '-']);
            await page.locator('.leger-main').scrollIntoViewIfNeeded();
            await page.screenshot({path: `${output}/${name}-${width}-table.png`});
            await page.locator('.leger-table-wrap').evaluate(el => { el.scrollLeft = el.scrollWidth; });
            assert.ok(await rows.first().evaluate(el => {
                const wrapper = el.closest('.leger-table-wrap').getBoundingClientRect();
                const rank = el.querySelector('.rank-column').getBoundingClientRect();
                const cells = [...el.querySelectorAll('.result-column')];
                const average = cells.at(-1).getBoundingClientRect();
                const status = el.querySelector('.status-column').getBoundingClientRect();
                return average.left >= rank.right - 1 && status.right <= wrapper.right + 1
                    && [...el.querySelectorAll('.status-column *')].every(item => {
                        const rect = item.getBoundingClientRect();
                        return item.scrollWidth <= item.clientWidth + 1 && rect.left >= status.left - 1 && rect.right <= status.right + 1;
                    });
            }), `Rata-rata dan status tidak tertutup kolom nama ${name} @${width}`);
            await page.screenshot({path: `${output}/${name}-${width}-results.png`});
            await page.locator('[data-leger-search]').fill('Alya');
            assert.equal(await page.locator('[data-leger-row]:not([hidden])').count(), 1);
            assert.equal(await page.locator('[data-leger-count]').innerText(), '1');
            await page.locator('[data-leger-search]').fill('tidak-ada-nama');
            assert.equal(await page.locator('[data-leger-row]:not([hidden])').count(), 0);
            await page.locator('[data-leger-search]').fill('');
            assert.equal(await page.locator('[data-leger-row]:not([hidden])').count(), name === 'kelas' ? 2 : 5);
        }
    }

    await page.setViewportSize({width: 1366, height: 1000});
    for (const name of ['kelascetak', 'tingkatcetak']) {
        await page.goto(`http://localhost/audit/${name}`);
        await page.evaluate(() => document.fonts.ready);
        assert.ok(await page.locator('img').evaluateAll(els => els.every(el => el.complete && el.naturalWidth > 0)), `Logo ${name}`);
        assert.match(await page.locator('.notice').innerText(), /Ranking sementara/);
        assert.match(await page.locator('.notice').innerText(), name === 'kelascetak' ? /dibagi 11 mapel/ : /dibagi 2 mapel/);
        assert.equal(await page.locator('.draft').count(), name === 'kelascetak' ? 0 : 1);
        await page.evaluate(() => { window.printCalls = 0; window.print = () => window.printCalls++; });
        await page.getByRole('button', {name: 'Cetak / Simpan PDF'}).click();
        assert.equal(await page.evaluate(() => window.printCalls), 1);
        await page.emulateMedia({media: 'print'});
        assert.ok(await page.locator('.toolbar').isHidden());
        assert.ok(await page.locator('table').evaluate(el => el.scrollWidth <= el.clientWidth + 1), `Luapan cetak ${name}`);
        const pdf = await page.pdf({path: `${output}/${name}.pdf`, preferCSSPageSize: true, printBackground: true, displayHeaderFooter: false});
        const document = await pdfLib.PDFDocument.load(pdf);
        pages[name] = document.getPageCount();
        assert.equal(pages[name], 1, `Fixture cetak ${name}`);
        const sheet = document.getPages()[0];
        assert.ok(Math.abs(sheet.getWidth() - 841.89) < 1 && Math.abs(sheet.getHeight() - 595.28) < 1, 'A4 landscape');
        await page.screenshot({path: `${output}/${name}.png`, fullPage: true});
        await page.emulateMedia({media: 'screen'});
    }
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({result: 'passed', pages, checks: ['fixed-divisor', 'parallel-ranks', 'draft-labels', 'missing-grades', 'completeness', 'desktop-mobile', 'search', 'print-button', 'logos', 'a4-landscape']}));
} finally { await browser.close(); }
