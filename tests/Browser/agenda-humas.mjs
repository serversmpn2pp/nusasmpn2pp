// NUSA_CAPTURE_AGENDA_UI=1 php artisan test --filter='AgendaHumasTest|PresensiPertemuanHumasTest'
import assert from 'node:assert/strict';
import { readFile, mkdir } from 'node:fs/promises';
import { extname, resolve, sep } from 'node:path';
import { pathToFileURL } from 'node:url';
import jsQR from 'jsqr';
import sharp from 'sharp';

const playwright = await import(process.env.PLAYWRIGHT_MODULE
    ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const chromium = playwright.chromium || playwright.default.chromium;
const output = resolve('storage/logs/agenda-humas-audit');
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined });

try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({ contentType: 'text/html', body: await readFile(`storage/framework/testing/agenda-humas/${name}.html`, 'utf8') });
        if (/\/agenda-humas\/\d+\/pantau-presensi$/.test(url.pathname)) {
            const data = JSON.parse(await readFile('storage/framework/testing/agenda-humas/pantau.json', 'utf8'));
            data.rekap.hadir = 1;
            data.rekap.belum_dicatat = Math.max(0, data.rekap.belum_dicatat - 1);
            data.terbaru = [{ nama: '<b>Ibu Penguji</b>', hadir_pada: '2026-10-05T00:15:00Z', sumber_kehadiran: 'qr' }];
            return route.fulfill({ contentType: 'application/json', body: JSON.stringify(data) });
        }
        const root = resolve('public');
        const file = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(root + sep)) return route.abort();
        try {
            return route.fulfill({ body: await readFile(file), contentType: ({ '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.woff2': 'font/woff2' })[extname(file)] || 'application/octet-stream' });
        } catch {
            return route.fulfill({ status: 404, body: '' });
        }
    });

    for (const [width, height] of [[320, 700], [390, 844], [768, 900], [1366, 900]]) {
        await page.setViewportSize({ width, height });
        for (const name of ['index', 'form', 'ringkasan', 'peserta', 'notulen', 'tindak-lanjut', 'dokumen', 'qr', 'undangan', 'pertemuan', 'konfirmasi', 'hadir']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Layout ${name} melebar pada ${width}px`);
            if (name === 'ringkasan') {
                await page.getByLabel('Status agenda', { exact: true }).selectOption('dibatalkan');
                assert.ok(await page.getByLabel('Alasan pembatalan', { exact: true }).isVisible());
                assert.equal(await page.getByLabel('Alasan pembatalan', { exact: true }).getAttribute('required'), '');
                await page.getByLabel('Status agenda', { exact: true }).selectOption('terjadwal');
                assert.equal(await page.getByLabel('Alasan pembatalan', { exact: true }).isVisible(), false);
            }
            if (name === 'peserta') {
                assert.equal(await page.locator('.agenda-presensi tbody tr').count(), 50);
                await page.getByRole('button', { name: 'Tambah baris peserta', exact: true }).click();
                assert.equal(await page.locator('.agenda-invite-row').count(), 2);
                await page.locator('.agenda-invite-row').last().getByRole('button', { name: 'Hapus baris', exact: true }).click();
                assert.equal(await page.locator('.agenda-invite-row').count(), 1);
                assert.equal(await page.locator('select[form="form_presensi"]').count(), 50);
                assert.equal(await page.locator('input[name$="[versi_presensi]"]').count(), 50);
            }
            if (name === 'qr') {
                await page.waitForFunction(() => document.querySelector('[data-rekap="hadir"]').textContent === '1');
                assert.equal(await page.locator('#qr_terbaru b').count(), 0, 'Monitoring harus menampilkan teks tanpa HTML dari nama');
                assert.equal(await page.locator('.agenda-qr-image svg').count(), 1);
                assert.ok(await page.locator('.agenda-qr-image svg path').count() > 0, 'QR kosong');
                const qrPixels = await sharp(await page.locator('.agenda-qr-image svg').screenshot()).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
                const decoded = jsQR(new Uint8ClampedArray(qrPixels.data), qrPixels.info.width, qrPixels.info.height);
                assert.equal(decoded?.data, await page.getByLabel('Tautan presensi pertemuan', { exact: true }).inputValue(), 'QR harus terbaca dan cocok dengan tautan presensi');
                await page.getByRole('button', { name: 'Perbarui', exact: true }).click();
                await page.waitForFunction(() => !document.getElementById('segarkan_presensi').disabled);
            }
            if (name === 'undangan') {
                await page.getByRole('radio', { name: 'Per tingkat', exact: true }).check();
                assert.ok(await page.getByLabel('Tingkat', { exact: true }).isVisible());
                assert.equal(await page.locator('input[name="kelas_ids[]"]').first().isEnabled(), false);
                await page.getByRole('radio', { name: 'Seluruh sekolah', exact: true }).check();
                assert.equal(await page.getByLabel('Tingkat', { exact: true }).isVisible(), false);
                await page.getByRole('radio', { name: 'Per kelas', exact: true }).check();
                assert.equal(await page.locator('input[name="kelas_ids[]"]').first().isEnabled(), true);
            }
            if (name === 'konfirmasi') {
                const button = page.getByRole('button', { name: 'Konfirmasi hadir', exact: true });
                await page.locator('[data-agenda-submit]').evaluate(form => form.addEventListener('submit', event => event.preventDefault()));
                await button.click();
                assert.ok(await page.getByRole('button', { name: 'Menyimpan...', exact: true }).isDisabled());
            }
            if ((width === 390 || width === 1366) && ['index', 'ringkasan', 'peserta', 'notulen', 'qr', 'undangan', 'pertemuan', 'hadir'].includes(name)) {
                await page.evaluate(() => document.querySelectorAll('*').forEach(element => { if (element.scrollTop > 0) element.scrollTop = 0; }));
                await page.screenshot({ path: `${output}/${name}-${width}.png`, fullPage: name !== 'peserta' });
            }
        }
    }
    await page.setViewportSize({ width: 900, height: 1100 });
    await page.emulateMedia({ media: 'print' });
    for (const name of ['cetak-hadir', 'cetak-notulen', 'cetak-qr']) {
        await page.goto(`http://localhost/audit/${name}`);
        if (name === 'cetak-hadir') await page.waitForFunction(() => window.agendaPrintReady === true);
        assert.equal(await page.locator('.toolbar').isVisible(), false);
        assert.ok(await page.locator('.header img').evaluateAll(images => images.every(image => image.complete && image.naturalWidth > 0)), 'Logo cetak gagal dimuat');
        await page.pdf({ path: `${output}/${name}.pdf`, preferCSSPageSize: true, printBackground: true });
        await page.screenshot({ path: `${output}/${name}.png`, fullPage: true });
        if (name === 'cetak-hadir') {
            await page.evaluate(() => {
                document.querySelectorAll('.records tbody tr').forEach(row => {
                    row.children[1].textContent = 'Nama Peserta Pertemuan dengan Nama Lengkap yang Panjang Sekali';
                    row.children[2].textContent = 'Perwakilan Orang Tua dan Wali Siswa SMP Negeri 2 Padang Panjang Kelas VII.A / Anggota Pengurus Komite Sekolah';
                });
                window.paginateHumasAttendance();
            });
            const heights = await page.locator('.sheet--attendance').evaluateAll(sheets => sheets.map(sheet => ({ height: sheet.getBoundingClientRect().height, rows: sheet.querySelectorAll('tbody tr').length })));
            assert.ok(heights.every(sheet => sheet.height <= 273 * 96 / 25.4 && sheet.rows <= 24), 'Teks panjang mendorong tanda tangan ke halaman tambahan');
            assert.equal(await page.locator('.records tbody tr').count(), 51, 'Pembagian lembar tidak boleh menghilangkan peserta');
            await page.pdf({ path: `${output}/cetak-hadir-nama-panjang.pdf`, preferCSSPageSize: true, printBackground: true });
            console.log(`Daftar hadir panjang: ${heights.length} lembar, seluruh 51 peserta tetap tercetak.`);
        }
    }
    assert.deepEqual(errors, []);
    console.log('PASS: 12 halaman pada 320/390/768/1366px, kontrol undangan, presensi, monitoring QR, logo dan cetak PDF.');
} finally {
    await browser.close();
}
