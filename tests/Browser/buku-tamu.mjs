// NUSA_CAPTURE_BUKU_TAMU_UI=1 php artisan test --filter=BukuTamuTest
import assert from 'node:assert/strict';
import { readFile, mkdir } from 'node:fs/promises';
import { resolve, sep, extname } from 'node:path';
import { pathToFileURL } from 'node:url';
import sharp from 'sharp';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (playwright.chromium || playwright.default.chromium).launch({ headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || undefined });
const output = resolve('storage/logs/buku-tamu-audit');
await mkdir(output, { recursive:true });
try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({ contentType:'text/html', body:await readFile(`storage/framework/testing/buku-tamu/${name}.html`, 'utf8') });
        if (/\/buku-tamu\/\d+\/lampiran\/\d+\/pratinjau$/.test(url.pathname)) {
            return route.fulfill({ contentType:'image/jpeg', body:await readFile('public/images/login-sekolah.jpg') });
        }
        const root = resolve('public');
        const path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try {
            return route.fulfill({ body:await readFile(path), contentType:({'.js':'text/javascript', '.css':'text/css', '.png':'image/png', '.jpg':'image/jpeg', '.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream' });
        } catch { return route.fulfill({ status:404, body:'' }); }
    });
    for (const [width, height] of [[320,700], [390,844], [768,900], [1366,900]]) {
        await page.setViewportSize({width,height});
        for (const name of ['index','rekap','satpam','form','edit','show']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${name} melebar pada ${width}px`);
            const clipped = await page.locator('.tamu-page .button, .tamu-tabs a, .tamu-metric').evaluateAll(elements => elements.filter(el => el.scrollHeight > el.clientHeight + 2 || el.scrollWidth > el.clientWidth + 2).map(el => el.textContent.trim()));
            assert.deepEqual(clipped, [], `Teks terpotong ${name}@${width}`);
            if (name === 'rekap') {
                await page.getByLabel('Periode laporan', {exact:true}).selectOption('semester');
                assert.ok(await page.getByLabel('Tahun pelajaran', {exact:true}).isVisible());
                assert.equal(await page.getByLabel('Mulai tanggal', {exact:true}).isEnabled(), false);
                await page.getByLabel('Periode laporan', {exact:true}).selectOption('rentang');
                assert.ok(await page.getByLabel('Mulai tanggal', {exact:true}).isVisible());
                assert.equal(await page.getByLabel('Tahun pelajaran', {exact:true}).isEnabled(), false);
                await page.getByLabel('Periode laporan', {exact:true}).selectOption('bulan_ini');
                assert.equal(await page.getByLabel('Mulai tanggal', {exact:true}).isVisible(), false);
                const excel = new URL(await page.getByRole('link', {name:'Ekspor Excel',exact:true}).getAttribute('href'));
                const cetak = new URL(await page.getByRole('link', {name:'Cetak laporan',exact:true}).getAttribute('href'));
                assert.equal(excel.search, cetak.search);
            }
            if (name === 'form') {
                const target = page.getByLabel('Pihak yang dituju', {exact:false});
                const other = page.getByLabel('Nama / bagian yang dituju', {exact:false});
                assert.ok(await other.isVisible());
                assert.equal(await other.getAttribute('required'), '');
                const option = await target.locator('option').nth(1).getAttribute('value');
                if (option) {
                    await target.selectOption(option);
                    assert.equal(await other.isVisible(), false);
                    assert.equal(await other.isEnabled(), false);
                    await target.selectOption('');
                }
            }
            if (name === 'show') {
                await page.waitForFunction(() => [...document.querySelectorAll('.tamu-file img')].every(img => img.complete && img.naturalWidth > 0));
                const pixels = await sharp(await page.locator('.tamu-file img').screenshot()).stats();
                assert.ok(pixels.channels.some(channel => channel.stdev > 20), 'Pratinjau foto kosong');
            }
            if ([390,1366].includes(width) && ['index','rekap','form','show'].includes(name)) {
                await page.evaluate(() => document.querySelectorAll('*').forEach(el => { if (el.scrollTop > 0) el.scrollTop = 0; }));
                await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
            }
        }
    }

    await page.addInitScript(() => {
        window.uploadRequests = [];
        window.XMLHttpRequest = class extends EventTarget {
            constructor() { super(); this.upload = new EventTarget(); window.uploadMock = this; }
            open(method, url) { this.method = method; this.url = url; }
            setRequestHeader() {}
            send(data) { window.uploadRequests.push([...data.entries()].map(([k,v]) => [k, typeof v === 'string' ? v : v.name])); }
        };
    });
    await page.goto('http://localhost/audit/form');
    await page.getByLabel('Nama tamu', {exact:false}).fill('Tamu audit browser');
    await page.getByLabel('Nama / bagian yang dituju', {exact:false}).fill('Tata Usaha');
    await page.getByLabel('Keperluan', {exact:false}).fill('Koordinasi sekolah');
    await page.getByRole('button', {name:'Simpan kedatangan',exact:true}).click();
    assert.equal(await page.locator('form[data-tamu-upload]').getAttribute('aria-busy'), 'true');
    assert.ok(await page.getByRole('button', {name:'Simpan kedatangan',exact:true}).isDisabled());
    await page.evaluate(() => {
        window.uploadMock.upload.dispatchEvent(new ProgressEvent('progress', {lengthComputable:true,loaded:50,total:100}));
    });
    assert.match(await page.locator('[data-upload-label]').textContent(), /50%/);
    await page.evaluate(() => window.uploadMock.upload.dispatchEvent(new ProgressEvent('progress', {lengthComputable:true,loaded:100,total:100})));
    assert.match(await page.locator('[data-upload-label]').textContent(), /Sedang menyimpan/);
    assert.equal(await page.evaluate(() => window.dispatchEvent(new Event('beforeunload', {cancelable:true}))), false, 'Navigasi harus mendapat peringatan saat unggahan aktif');
    await page.evaluate(() => {
        const xhr = window.uploadMock;
        xhr.status = 422;
        xhr.responseText = JSON.stringify({errors:{lampiran:['Berkas terlalu besar.']}});
        xhr.dispatchEvent(new Event('load'));
    });
    assert.ok(await page.getByRole('button', {name:'Simpan kedatangan',exact:true}).isEnabled());
    assert.match(await page.locator('[data-upload-label]').textContent(), /Berkas terlalu besar/);
    assert.equal(await page.evaluate(() => window.dispatchEvent(new Event('beforeunload', {cancelable:true}))), true);
    await page.getByRole('button', {name:'Simpan kedatangan',exact:true}).click();
    assert.equal(await page.evaluate(() => window.uploadRequests[0].find(([k]) => k === 'token_pencatatan')[1] === window.uploadRequests[1].find(([k]) => k === 'token_pencatatan')[1]), true);
    await page.evaluate(() => {
        const xhr = window.uploadMock;
        xhr.status = 200;
        xhr.responseText = JSON.stringify({redirect:'http://localhost/audit/show',pesan:'Tersimpan'});
        xhr.dispatchEvent(new Event('load'));
    });
    await page.waitForURL('**/audit/show');

    await page.emulateMedia({media:'print'});
    await page.goto('http://localhost/audit/cetak');
    assert.equal(await page.locator('.toolbar').isVisible(), false);
    await page.waitForFunction(() => [...document.querySelectorAll('header img')].every(img => img.complete && img.naturalWidth > 0));
    assert.equal(await page.locator('tbody tr').count(), 30);
    await page.pdf({path:`${output}/laporan-buku-tamu.pdf`, preferCSSPageSize:true,printBackground:true});
    await page.screenshot({path:`${output}/cetak.png`,fullPage:true});
    assert.deepEqual(errors, []);
    console.log('PASS: 6 tampilan pada 320/390/768/1366px, filter, target, foto privat, indikator unggah, retry, dan cetak PDF.');
} finally { await browser.close(); }
