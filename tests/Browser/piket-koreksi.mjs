import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const fixtures = resolve('storage/framework/testing/piket-koreksi');
const output = resolve('storage/logs/piket-koreksi-qa');
const assets = resolve('public');
const errors = [];
let checked = 0;

try {
    await mkdir(output, {recursive: true});
    const page = await browser.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const request = route.request();
        const url = new URL(request.url());
        assert.equal(url.hostname, 'localhost');
        assert.equal(request.method(), 'GET');
        const fixture = url.pathname.match(/^\/_piket-koreksi\/([a-z]+)$/)?.[1];
        if (fixture) return route.fulfill({contentType: 'text/html', body: await readFile(resolve(fixtures, fixture + '.html'))});
        const rekap = url.pathname.match(/^\/_rekap-status\/(koreksi|verifikasi)$/)?.[1];
        if (rekap) return route.fulfill({contentType: 'text/html', body: await readFile(resolve('storage/framework/testing/rekap-sumber', rekap + '.html'))});
        if (url.pathname === '/notifikasi/ringkasan') return route.fulfill({json: {jumlah_belum_dibaca: 0, notifikasi: []}});
        const asset = resolve(assets, '.' + decodeURIComponent(url.pathname));
        assert.ok(asset.startsWith(assets + sep));
        const types = {'.css': 'text/css', '.js': 'text/javascript', '.svg': 'image/svg+xml', '.png': 'image/png', '.webp': 'image/webp', '.woff2': 'font/woff2'};
        try {
            return route.fulfill({contentType: types[extname(asset)] || 'application/octet-stream', body: await readFile(asset)});
        } catch {
            return route.fulfill({status: 404, body: ''});
        }
    });
    for (const [width, height] of [[320, 740], [390, 844], [1024, 768], [1440, 900]]) {
        await page.setViewportSize({width, height});
        for (const fixture of ['manual', 'checkout', 'locked']) {
            await page.goto('http://localhost/_piket-koreksi/' + fixture);
            const root = page.locator(width <= 820 ? '.picket-mobile-list' : '.picket-desktop');
            await root.waitFor({state: 'visible'});
            await page.getByRole('heading', {name: 'Kehadiran Siswa Hari Ini', exact: true}).waitFor();
            assert.equal(await root.getByText('Murid Uji Piket', {exact: true}).count(), 1);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            if (fixture === 'locked') {
                assert.equal(await root.locator('[data-hadir-manual]').count(), 0);
                assert.ok(await root.getByText('Ada bukti scan masuk berhasil.', {exact: false}).isVisible());
            } else {
                await root.getByText('Ubah catatan manual', {exact: true}).click();
                const form = root.locator('[data-hadir-manual]');
                const time = form.locator('[name="jam_masuk"]');
                const choice = form.locator('[name="status_masuk_manual"]');
                await form.locator('[name="alasan_hadir_manual"]').selectOption('tidak_membawa_kartu');
                assert.ok(await time.isHidden());
                assert.ok(await time.isDisabled());
                assert.ok(await choice.isVisible());
                assert.equal(await choice.inputValue(), '', 'Riwayat lama harus dipilih ulang secara eksplisit');
                await choice.selectOption('terlambat');
                assert.ok(await root.getByText('Input manual', {exact: false}).isVisible());
                assert.ok(await root.getByText('Petugas Simulasi Admin', {exact: false}).isVisible());
                await form.locator('textarea[name="catatan"]').fill('Koreksi manual berdasarkan konfirmasi.');
                if (fixture === 'checkout') {
                    assert.equal(await form.locator('[name="status_kehadiran"] option').count(), 1);
                    assert.ok(await root.getByText('Pulang 14:40', {exact: false}).isVisible());
                    assert.ok(await root.getByText('Scanner', {exact: false}).isVisible());
                } else {
                    const status = form.getByLabel('Status kehadiran', {exact: true});
                    await status.selectOption('sakit');
                    assert.ok(await choice.isDisabled());
                    await status.selectOption('hadir');
                    assert.ok(await choice.isEnabled());
                }
                assert.ok(await form.evaluate(el => el.checkValidity()));
                assert.deepEqual(await form.evaluate(el => {
                    const data = new FormData(el);
                    return [data.get('status_masuk_manual'), data.has('jam_masuk')];
                }), ['terlambat', false]);
            }
            const png = await page.screenshot({path: resolve(output, fixture + '-' + width + '.png'), fullPage: true});
            assert.equal(png.readUInt32BE(16), width);
            checked++;
        }
        for (const fixture of ['koreksi', 'verifikasi']) {
            await page.goto('http://localhost/_rekap-status/' + fixture);
            const choice = page.locator('[name="status_masuk_manual"]');
            const time = page.locator('#jam_masuk');
            const form = choice.locator('xpath=ancestor::form');
            const verifying = fixture === 'verifikasi';
            const reason = page.locator(verifying ? '#hasil_verifikasi_scan' : '#alasan_hadir_manual');
            await reason.selectOption(verifying ? 'gangguan_terkonfirmasi' : 'scan_bermasalah');
            assert.ok(await time.isVisible());
            assert.ok(await time.evaluate(el => el.required));
            assert.ok(await choice.isDisabled());
            await reason.selectOption('kartu_hilang');
            assert.ok(await time.isHidden());
            assert.ok(await choice.isEnabled());
            if (!verifying) assert.equal(await choice.inputValue(), 'tepat_waktu');
            await choice.selectOption('terlambat');
            await form.locator('[name="catatan"]').fill('Kartu hilang dikonfirmasi.');
            assert.deepEqual(await form.evaluate(el => {
                const data = new FormData(el);
                return [data.get('status_masuk_manual'), data.has('jam_masuk'), el.checkValidity()];
            }), ['terlambat', false, true]);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            await page.screenshot({path: resolve(output, fixture + '-' + width + '.png'), fullPage: true});
            checked++;
        }
    }
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({checked, errors}));
} finally {
    await browser.close();
}
