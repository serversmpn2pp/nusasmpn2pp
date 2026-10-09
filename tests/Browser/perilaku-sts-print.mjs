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
    const context = await browser.newContext();
    const page = await context.newPage(), errors = [], pages = {};
    page.on('pageerror', error => errors.push(error.message));
    await context.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/([a-z]+)$/)?.[1];
        if (name) return route.fulfill({contentType: 'text/html', body: await readFile(`storage/framework/testing/perilaku-sts/${name}.html`, 'utf8')});
        if (/\/rapor-sts\/.*\/cetak$/.test(url.pathname) && url.searchParams.get('perilaku') === '1' && url.searchParams.get('pratinjau') === '1') {
            return route.fulfill({contentType: 'text/html', body: await readFile('storage/framework/testing/perilaku-sts/combinedpreview.html', 'utf8')});
        }
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
        assert.deepEqual(await page.locator('.behavior-table thead th').allTextContents(), ['Tanggal', 'Kejadian / pelanggaran', 'Teguran / tindak lanjut', 'Poin']);
        const bulk = page.locator('#behavior-bulk-form');
        assert.ok(await page.locator('#behavior-bulk-save').isDisabled());
        await page.locator('#behavior-select-all').check();
        assert.equal(await page.locator('#behavior-selection-count').textContent(), '2 dari 2 siswa dipilih');
        assert.ok(await page.locator('#behavior-bulk-save').isEnabled());
        assert.equal(await bulk.evaluate(el => el.checkValidity()), false, 'Konfirmasi kolektif wajib');
        await page.locator('[data-behavior-select]').last().uncheck();
        assert.ok(await page.locator('#behavior-select-all').evaluate(el => el.indeterminate));
        assert.equal(await page.locator('#behavior-selection-count').textContent(), '1 dari 2 siswa dipilih');
        await page.locator('[data-behavior-select]').last().check();
        await form.locator('input[name=diperiksa]').uncheck();
        const occurrence = form.locator('textarea[name$="[kejadian]"]').first();
        await occurrence.fill('Ringkasan koreksi sebelum simpan kolektif');
        await form.locator('textarea[name=catatan]').fill('Catatan koreksi kolektif');
        await bulk.locator('input[name=diperiksa]').check();
        assert.equal(await bulk.evaluate(el => el.checkValidity()), true);
        await bulk.evaluate(el => {
            el.addEventListener('submit', event => {
                const data = new FormData(el);
                window.bulkSubmission = {blocked: event.defaultPrevented, selected: data.getAll('anggota_ids[]'),
                    siswa: JSON.parse(data.get('siswa_json') || '{}'), bk: data.get('guru_bk_id'), wakil: data.get('wakil_kesiswaan_id')};
                event.preventDefault();
            });
            el.requestSubmit();
        });
        const submission = await page.evaluate(() => window.bulkSubmission);
        assert.equal(submission.blocked, false);
        assert.equal(submission.selected.length, 2);
        assert.deepEqual(Object.keys(submission.siswa), submission.selected);
        const edited = submission.siswa[submission.selected[0]];
        assert.equal(edited.catatan, 'Catatan koreksi kolektif');
        assert.equal(Object.values(edited.baris)[0].kejadian, 'Ringkasan koreksi sebelum simpan kolektif');
        assert.deepEqual(submission.siswa[submission.selected[1]].baris, {});
        assert.ok(submission.bk && submission.wakil);
        await occurrence.fill('');
        assert.ok(!await bulk.locator('input[name=diperiksa]').isChecked(), 'Edit ulang memerlukan konfirmasi ulang');
        await bulk.locator('input[name=diperiksa]').check();
        await bulk.evaluate(el => el.requestSubmit());
        assert.equal(await page.evaluate(() => window.bulkSubmission.blocked), true, 'Ringkasan kosong harus ditolak sebelum kolektif');
        await occurrence.fill('Ringkasan koreksi sebelum simpan kolektif');
        await page.locator('[data-behavior-select]').last().uncheck();
        assert.ok(!await bulk.locator('input[name=diperiksa]').isChecked(), 'Ganti pilihan memerlukan konfirmasi ulang');
        await bulk.locator('input[name=diperiksa]').check();
        await bulk.evaluate(el => el.requestSubmit());
        assert.equal(await page.evaluate(() => Object.keys(window.bulkSubmission.siswa).length), 1, 'Hanya siswa yang dipilih dikirim');
        const otherStudent = page.locator('[data-behavior-student]').last();
        await otherStudent.evaluate(el => { el.open = true; });
        await otherStudent.locator('textarea[name=catatan]').fill('Koreksi siswa yang belum dipilih');
        page.once('dialog', dialog => dialog.dismiss());
        await bulk.evaluate(el => el.requestSubmit());
        assert.equal(await page.evaluate(() => window.bulkSubmission.blocked), true, 'Peringatkan koreksi siswa yang tidak dipilih');
        await page.locator('[data-behavior-select]').last().check();
        await bulk.locator('input[name=diperiksa]').check();
        await bulk.evaluate(el => el.requestSubmit());
        assert.equal(await page.evaluate(() => Object.values(window.bulkSubmission.siswa)[1].catatan), 'Koreksi siswa yang belum dipilih');
        await page.locator('[data-behavior-select]').last().uncheck();
        await otherStudent.evaluate(el => { el.open = false; });
        await page.locator('[data-behavior-select]').first().uncheck();
        assert.ok(await page.locator('#behavior-bulk-save').isDisabled());
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Luapan review @${width}`);
        await page.evaluate(() => {
            document.querySelectorAll('*').forEach(el => { if (el.scrollTop) el.scrollTop = 0; });
            window.scrollTo(0, 0);
        });
        await page.screenshot({path: `${output}/review-${width}.png`, fullPage: true});
        await page.goto('http://localhost/audit/index');
        assert.equal(await page.locator('a').filter({hasText: /^Rapor \+ perilaku$/}).count(), 2);
        const overflowButtons = await page.locator('.sts-report-actions .button').evaluateAll(buttons => buttons.filter(button => {
            const rect = button.getBoundingClientRect(), parent = button.parentElement.getBoundingClientRect();
            const range = document.createRange();
            range.selectNodeContents(button);
            return [...range.getClientRects()].some(text => text.left < rect.left - 1 || text.right > rect.right + 1 || text.bottom > rect.bottom + 1)
                || rect.left < parent.left - 1 || rect.right > parent.right + 1;
        }).map(button => button.textContent));
        assert.deepEqual(overflowButtons, [], `Tombol tidak meluap @${width}`);
        if (width === 1366) {
            const popupEvent = page.waitForEvent('popup');
            await page.locator('.sts-report-actions a').filter({hasText: 'Pratinjau gabungan'}).first().click();
            const preview = await popupEvent;
            await preview.waitForLoadState();
            assert.equal(new URL(preview.url()).searchParams.get('perilaku'), '1');
            assert.equal(await preview.locator('.sheet').count(), 2);
            assert.equal(await preview.locator('[data-behavior-sheet]').count(), 1);
            assert.ok(await preview.locator('.draft').first().isVisible());
            await preview.close();
        }
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Luapan rekap @${width}`);
        await page.screenshot({path: `${output}/index-${width}.png`});
    }
    await page.setViewportSize({width: 1366, height: 1000});
    for (const name of ['individual', 'class', 'preview', 'long']) {
        await page.goto(`http://localhost/audit/${name}`);
        await page.evaluate(() => document.fonts.ready);
        assert.ok(await page.locator('img').evaluateAll(els => els.every(el => el.complete && el.naturalWidth > 0)), `Logo ${name}`);
        const sheets = await page.locator('.sheet').count();
        assert.deepEqual(await page.locator('.behavior-print').first().locator('th').allTextContents(), ['No.', 'Tanggal', 'Kejadian / pelanggaran', 'Teguran / tindak lanjut', 'Poin']);
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
    console.log(JSON.stringify({result: 'passed', pages, checks: ['BK-confirmation', 'collective-selection', 'collective-confirmation', 'unsaved-edits', 'shared-signers', 'preview-button-fit', 'combined-preview', 'status-column-removed', 'desktop-mobile', 'parent-safe-summary', 'logos', 'student-page-order', 'long-cases', 'no-clipping', 'A4']}));
} finally { await browser.close(); }
