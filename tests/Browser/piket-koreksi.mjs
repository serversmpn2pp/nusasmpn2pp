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
                assert.ok(await time.isVisible());
                assert.equal(await time.inputValue(), '06:22');
                assert.ok(await root.getByText('Input manual', {exact: false}).isVisible());
                assert.ok(await root.getByText('Petugas Simulasi Admin', {exact: false}).isVisible());
                await time.fill('06:23');
                await form.locator('textarea[name="catatan"]').fill('Koreksi manual berdasarkan konfirmasi.');
                if (fixture === 'checkout') {
                    assert.equal(await form.locator('[name="status_kehadiran"] option').count(), 1);
                    assert.ok(await root.getByText('Pulang 14:40', {exact: false}).isVisible());
                    assert.ok(await root.getByText('Scanner', {exact: false}).isVisible());
                } else {
                    const status = form.getByLabel('Status kehadiran', {exact: true});
                    await status.selectOption('sakit');
                    assert.ok(await time.isDisabled());
                    await status.selectOption('hadir');
                    assert.ok(await time.isEnabled());
                }
                assert.ok(await form.evaluate(el => el.checkValidity()));
            }
            const png = await page.screenshot({path: resolve(output, fixture + '-' + width + '.png'), fullPage: true});
            assert.equal(png.readUInt32BE(16), width);
            checked++;
        }
    }
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({checked, errors}));
} finally {
    await browser.close();
}
