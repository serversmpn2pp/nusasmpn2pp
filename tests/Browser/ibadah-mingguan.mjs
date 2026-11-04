// Fixtures: NUSA_CAPTURE_IBADAH_MINGGUAN=1 php artisan test --filter=RekapKegiatanIbadahTest
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {mkdir, readFile} from 'node:fs/promises';
import {extname, resolve, sep} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/ibadah-mingguan-qa');
const root = resolve('public');
try {
    await mkdir(output, {recursive: true});
    const page = await browser.newPage({serviceWorkers: 'block'});
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        assert.equal(url.hostname, 'localhost');
        assert.equal(route.request().method(), 'GET');
        if (url.pathname.startsWith('/rekap-kegiatan-ibadah/mingguan')) {
            const fixture = url.pathname.endsWith('/cetak') ? 'cetak' : 'index';
            return route.fulfill({contentType: 'text/html', body: await readFile(`storage/framework/testing/ibadah-mingguan/${fixture}.html`)});
        }
        if (url.pathname === '/notifikasi/ringkasan') return route.fulfill({json: {jumlah_belum_dibaca: 0, notifikasi: []}});
        const path = resolve(root, `.${decodeURIComponent(url.pathname)}`);
        assert.ok(path.startsWith(root + sep));
        const types = {'.css': 'text/css', '.js': 'text/javascript', '.svg': 'image/svg+xml', '.png': 'image/png', '.woff2': 'font/woff2'};
        const body = await readFile(path).catch(() => null);
        return body ? route.fulfill({contentType: types[extname(path)] || 'application/octet-stream', body}) : route.fulfill({status: 404});
    });
    for (const width of [320, 390, 768, 1024, 1440]) {
        await page.setViewportSize({width, height: 860});
        await page.goto('http://localhost/rekap-kegiatan-ibadah/mingguan');
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Overflow at ${width}`);
        assert.equal(await page.locator('.iw-table tbody tr').count(), 2);
        assert.ok(await page.locator('.iw-warning').isVisible());
        assert.ok(await page.locator('.iw-table').evaluate(el => el.scrollWidth <= el.clientWidth + 1));
        if ([390, 1440].includes(width)) await page.screenshot({path: resolve(output, `evaluasi-${width}.png`), fullPage: true});
    }
    const cetak = page.getByRole('link', {name: 'Cetak evaluasi'});
    assert.equal(await cetak.getAttribute('target'), '_blank');
    await page.goto(new URL(await cetak.getAttribute('href'), page.url()).href);
    await page.setViewportSize({width: 390, height: 820});
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    await page.setViewportSize({width: 794, height: 1123});
    await page.emulateMedia({media: 'print'});
    assert.ok(await page.locator('.iw-toolbar').isHidden());
    assert.equal(await page.getByRole('columnheader', {name: 'Hasil konfirmasi'}).count(), 1);
    await page.screenshot({path: resolve(output, 'cetak.png')});
    const pdf = await page.pdf({preferCSSPageSize: true});
    assert.match(execFileSync('pdfinfo', ['-'], {input: pdf, encoding: 'utf8'}), /^Page size:.*\(A4\)/m);
    await page.locator('.iw-table tbody').evaluate(tbody => {
        const sample = tbody.rows[0];
        for (let i = 0; i < 60; i++) tbody.append(sample.cloneNode(true));
        tbody.lastElementChild.cells[0].textContent = 'Siswa Pada Baris Terakhir';
    });
    const panjang = await page.pdf({preferCSSPageSize: true});
    const info = execFileSync('pdfinfo', ['-'], {input: panjang, encoding: 'utf8'});
    const pages = Number(info.match(/^Pages:\s+(\d+)/m)?.[1]);
    assert.ok(pages > 1);
    execFileSync('pdftoppm', ['-f', `${pages}`, '-l', `${pages}`, '-r', '96', '-png', '-singlefile', '-', resolve(output, 'cetak-halaman-akhir')], {input: panjang});
    assert.deepEqual(errors, []);
    console.log('Evaluasi ibadah mingguan: 5 ukuran layar dan cetak A4 satu/multi halaman lolos.');
} finally {
    await browser.close();
}
