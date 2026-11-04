import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {mkdir, readFile} from 'node:fs/promises';
import {extname, resolve, sep} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const fixtures = resolve('storage/framework/testing/tahfidz');
const output = resolve('storage/logs/tahfidz-qa');
const publicRoot = resolve('public');
const errors = [];
const writes = [];
let checked = 0;

try {
    await mkdir(output, {recursive: true});
    const context = await browser.newContext({serviceWorkers: 'block', reducedMotion: 'reduce'});
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const request = route.request();
        const url = new URL(request.url());
        assert.ok(['localhost', '127.0.0.1'].includes(url.hostname), `Unexpected origin: ${url.hostname}`);
        if (request.method() !== 'GET') {
            writes.push(url.pathname);
            return route.fulfill({status: 405, body: ''});
        }
        const fixture = url.pathname.match(/^\/_tahfidz-qa\/([a-z]+)$/)?.[1];
        if (fixture) return route.fulfill({contentType: 'text/html', body: await readFile(resolve(fixtures, `${fixture}.html`))});
        if (/^\/tahfidz\/\d+\/anggota\/\d+\/rekap$/.test(url.pathname)) {
            const status = url.searchParams.get('status');
            const file = status === 'sudah' ? 'rekapsudah' : status === 'belum' ? 'rekapbelum' : 'rekapsiswa';
            return route.fulfill({contentType: 'text/html', body: await readFile(resolve(fixtures, `${file}.html`))});
        }
        if (url.pathname === '/notifikasi/ringkasan') return route.fulfill({json: {jumlah_belum_dibaca: 0, notifikasi: []}});
        const asset = resolve(publicRoot, `.${decodeURIComponent(url.pathname)}`);
        assert.ok(asset.startsWith(publicRoot + sep));
        const types = {'.css': 'text/css', '.js': 'text/javascript', '.svg': 'image/svg+xml', '.png': 'image/png', '.webp': 'image/webp', '.woff2': 'font/woff2'};
        try {
            return route.fulfill({contentType: types[extname(asset)] || 'application/octet-stream', body: await readFile(asset)});
        } catch {
            return route.fulfill({status: 404, body: ''});
        }
    });

    for (const [width, height] of [[320, 740], [360, 800], [768, 1024], [1024, 768], [1440, 900]]) {
        await page.setViewportSize({width, height});
        for (const fixture of ['index', 'empty', 'form', 'detail', 'anggota', 'surah', 'pembimbing', 'jadwal', 'pertemuan', 'riwayat', 'rekapkelompok', 'rekapsiswa', 'targetrapor', 'raporkelompok', 'raporkelompokblokir', 'wisuda', 'anak', 'anakdokumen']) {
            await page.goto(`http://localhost/_tahfidz-qa/${fixture}`);
            const content = page.locator('.tf-page');
            await content.waitFor();
            const modeOrtu = fixture.startsWith('anak');
            const modeRapor = ['targetrapor', 'raporkelompok', 'raporkelompokblokir'].includes(fixture);
            const menu = modeOrtu ? page.locator('[data-sidebar-item][href$="/tahfidz-anak"]')
                : page.locator(`[data-sidebar-item][data-sidebar-search-text="akademik penilaian ${modeRapor ? 'rapor tahfidz' : 'tahfidz'}"]`);
            assert.equal(await menu.count(), 1);
            assert.ok(await menu.evaluate((el, isParent) => el.closest('[data-sidebar-section-id]')?.dataset.sidebarSectionId === (isParent ? 'informasi-anak' : 'akademik')
                && (isParent || el.closest('[data-sidebar-subgroup]')?.dataset.sidebarSubgroupTitle === 'penilaian')
                && el.getAttribute('aria-current') === 'page', modeOrtu));
            assert.ok(await content.evaluate(el => el.scrollWidth <= el.clientWidth + 1), `Content overflow: ${width} ${fixture}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Page overflow: ${width} ${fixture}`);
            if (fixture === 'surah') assert.equal(await content.locator('.tf-surah-grid li').count(), 37);
            if (fixture === 'pembimbing') assert.equal(await content.getByText('Atur anggota', {exact: true}).count(), 0);
            if (fixture === 'jadwal') {
                assert.equal(await content.getByLabel('Tanggal pertemuan').inputValue(), '2026-11-04');
                assert.equal(await content.getByRole('link', {name: 'Lihat pertemuan'}).count(), 2);
            }
            if (fixture === 'pertemuan') {
                await content.getByLabel('Siswa', {exact: true}).selectOption({label: 'Peserta Delapan'});
                await content.getByLabel('Surah', {exact: true}).selectOption('112');
                await content.getByLabel('Nilai (0-100)', {exact: true}).fill('92,5');
                await content.getByLabel('Catatan (opsional)', {exact: true}).fill('Setoran lanjutan');
                assert.ok(await content.locator('form:has([name="token_setoran"])').evaluate(el => el.checkValidity()));
                assert.equal(await content.getByRole('button', {name: 'Selesaikan pertemuan'}).count(), 1);
                assert.deepEqual(await content.locator('.tf-setoran-score strong').allTextContents(), ['0', '100', '90']);
            }
            if (fixture === 'riwayat') {
                assert.equal(await content.getByText('Al-Ikhlas', {exact: true}).count(), 4);
                assert.deepEqual(await content.locator('.tf-setoran-score strong').allTextContents(), ['0', '100', '90', '85,5']);
            }
            if (fixture === 'rekapkelompok') {
                assert.deepEqual(await content.locator('progress').evaluateAll(items => items.map(item => [item.value, item.max])), [[0, 37], [37, 37], [2, 37]]);
                assert.equal(await content.locator('.tf-rekap-row').count(), 3);
            }
            if (fixture === 'rekapsiswa') {
                assert.equal(await content.locator('.tf-rekap-surah-row').count(), 37);
                await content.getByRole('link', {name: 'Sudah disetor', exact: true}).click();
                assert.equal(await content.locator('.tf-rekap-surah-row').count(), 2);
                assert.deepEqual(await content.locator('.tf-setoran-score strong').allTextContents(), ['92,25', '0']);
                await content.getByRole('link', {name: 'Belum disetor', exact: true}).click();
                assert.equal(await content.locator('.tf-rekap-surah-row').count(), 35);
                assert.equal(await content.locator('.tf-setoran-score').count(), 0);
                assert.equal(await content.getByRole('link', {name: 'Belum disetor', exact: true}).getAttribute('aria-current'), 'page');
                await content.getByRole('link', {name: 'Sudah disetor', exact: true}).click();
            }
            if (fixture === 'targetrapor') {
                const mode = content.getByLabel('Pilihan surah', {exact: true});
                const choices = content.locator('[data-tf-target-choices]');
                assert.ok(await choices.isVisible());
                assert.equal(await choices.locator('[data-tf-target-count]').textContent(), '4/4 dipilih');
                await mode.selectOption('bebas');
                assert.ok(await choices.isHidden());
                assert.equal(await content.locator('[data-tf-target-form]').evaluate(form => new FormData(form).getAll('surah[]').length), 0);
                await mode.selectOption('tetap');
                assert.equal(await choices.locator('input:checked').count(), 4);
            }
            if (fixture.startsWith('raporkelompok')) {
                const kelompok = content.getByLabel('Kelompok Tahfidz', {exact: true});
                assert.equal(await kelompok.locator('option:checked').textContent(), 'Kelompok Cetak Bersama');
                assert.equal(await content.locator('.tf-rapor-row').count(), 3);
                if (fixture === 'raporkelompok') {
                    const cetak = content.getByRole('link', {name: 'Cetak semua rapor'});
                    assert.match(await cetak.getAttribute('href'), /\/tahfidz\/rapor\/kelompok\/\d+\/cetak$/);
                    assert.equal(await cetak.getAttribute('target'), '_blank');
                    assert.equal(await content.getByRole('link', {name: /^Pratinjau rapor/}).count(), 3);
                } else {
                    assert.ok(await content.getByRole('button', {name: 'Cetak semua rapor'}).isDisabled());
                    assert.equal(await content.getByRole('link', {name: /^Pratinjau rapor/}).count(), 2);
                }
            }
            if (fixture === 'wisuda') {
                const acara = content.getByLabel('Acara wisuda', {exact: true});
                await acara.selectOption({label: '12/12/2026 - Wisuda Tahfidz NUSA'});
                assert.ok(await acara.evaluate(el => el.form.checkValidity()));
            }
            if (fixture === 'anak') {
                assert.equal(await content.getByLabel('Nama anak').locator('option').count(), 2);
                assert.equal(await content.locator('.tf-rekap-surah-row').count(), 1);
                assert.deepEqual(await content.locator('.tf-setoran-score strong').allTextContents(), ['90', '90', '85']);
                assert.equal(await content.getByText('Catatan privat Tahfidz').count(), 0);
            }
            if (fixture === 'anakdokumen') {
                assert.equal(await content.getByRole('link', {name: 'Buka sertifikat'}).count(), 1);
                assert.equal(await content.getByRole('link', {name: 'Buka rapor'}).count(), 0);
            }

            if (fixture === 'anggota') {
                const bulk = content.getByLabel('Pilih semua hasil');
                const search = content.getByLabel('Cari siswa');
                const grade = content.getByLabel('Tingkat', {exact: true});
                const classroom = content.getByLabel('Kelas', {exact: true});
                const status = content.getByLabel('Pilihan', {exact: true});
                const count = content.locator('[data-roster-count]');
                await bulk.uncheck();
                assert.equal(await count.textContent(), '0 terpilih');
                await grade.selectOption('7');
                await classroom.selectOption({label: 'VII.A'});
                await bulk.check();
                await grade.selectOption('8');
                assert.equal(await classroom.inputValue(), '');
                await bulk.check();
                await grade.selectOption('9');
                assert.ok(await bulk.isDisabled());
                assert.ok(await content.getByRole('checkbox', {name: /Peserta Sembilan/}).isDisabled());
                await grade.selectOption('');
                await search.fill('peserta tujuh');
                await search.press('Enter');
                assert.equal(await content.locator('[data-roster-row]:visible').count(), 1);
                assert.equal(await count.textContent(), '2 terpilih');
                assert.equal(await content.locator('form').evaluate(el => new FormData(el).getAll('siswa_ids[]').length), 2);
                await search.fill('tidak ditemukan');
                assert.ok(await content.getByText('Belum ada siswa yang sesuai.').isVisible());
                await search.fill('');
                await status.selectOption('selected');
                assert.equal(await content.locator('[data-roster-row]:visible').count(), 2);
                await status.selectOption('');
            }

            if ([360, 1440].includes(width) && ['index', 'form', 'anggota', 'surah', 'jadwal', 'pertemuan', 'riwayat', 'rekapkelompok', 'rekapsiswa', 'targetrapor', 'raporkelompok', 'raporkelompokblokir', 'wisuda', 'anak', 'anakdokumen'].includes(fixture)) {
                await page.screenshot({path: resolve(output, `${fixture}-${width}.png`), fullPage: true, animations: 'disabled'});
            }
            checked++;
        }
    }
    for (const [fixture, width, height, pages, paper] of [
        ['raporcetak', 794, 1123, 1, 'a4 portrait'],
        ['raporpanjang', 794, 1123, 2, 'a4 portrait'],
        ['raporkelompokcetak', 794, 1123, 4, 'a4 portrait'],
        ['raporanak', 794, 1123, 1, 'a4 portrait'],
        ['sertifikat', 1123, 794, 1, 'a4 landscape'],
    ]) {
        await page.setViewportSize({width: 360, height: 800});
        await page.goto(`http://localhost/_tahfidz-qa/${fixture}`);
        if (['raporanak', 'raporkelompokcetak'].includes(fixture)) assert.equal(await page.locator('.tf-print-toolbar form').count(), 0);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Preview overflow: ${fixture}`);
        await page.setViewportSize({width, height});
        await page.emulateMedia({media: 'print'});
        await page.evaluate(async () => { for (const img of document.images) await img.decode(); });
        assert.ok(await page.locator('.tf-print-toolbar').isHidden());
        const sheets = page.locator('.tf-print-sheet');
        assert.equal(await sheets.count(), pages);
        if (fixture === 'raporkelompokcetak') {
            assert.deepEqual(await sheets.evaluateAll(items => items.map(item => item.getAttribute('aria-label'))),
                ['Rapor Peserta Delapan', 'Rapor Peserta Sembilan', 'Rapor Peserta Sembilan', 'Rapor Peserta Tujuh']);
        }
        const sizes = await page.evaluate(() => [...document.styleSheets].flatMap(sheet => [...sheet.cssRules]
            .filter(rule => rule instanceof CSSPageRule).map(rule => rule.style.getPropertyValue('size').toLowerCase().trim())));
        assert.ok(sizes.some(size => size.split(/\s+/).sort().join(' ') === paper || (paper === 'a4 portrait' && size === 'a4')), `${fixture}: ${sizes.join(', ')}`);
        if (process.env.NUSA_VERIFY_PRINT_PDF) {
            const pdf = await page.pdf({preferCSSPageSize: true, printBackground: true});
            const info = execFileSync('pdfinfo', ['-'], {input: pdf, encoding: 'utf8'});
            assert.match(info, new RegExp(`^Pages:\\s+${pages}$`, 'm'));
            assert.match(info, /^Page size:.*\(A4\)/m);
        }
        for (let index = 0; index < pages; index++) {
            const bounds = await sheets.nth(index).boundingBox();
            assert.ok(bounds.width <= width + 1 && bounds.height <= height + 1, `Print overflow: ${fixture} sheet ${index + 1}`);
            await sheets.nth(index).screenshot({path: resolve(output, `${fixture}-print-${index + 1}.png`), animations: 'disabled'});
        }
        await page.emulateMedia({media: 'screen'});
        checked++;
    }
    assert.deepEqual(errors, []);
    assert.deepEqual(writes, []);
    console.log(`Tahfidz: ${checked} tampilan lolos; formulir, rekap, rapor, dan sertifikat diverifikasi.`);
} finally {
    await browser.close();
}
