// NUSA_CAPTURE_KEMITRAAN_UI=1 php artisan test --filter=KemitraanHumasTest
import assert from 'node:assert/strict';
import { readFile, mkdir } from 'node:fs/promises';
import { resolve, sep, extname } from 'node:path';
import { pathToFileURL } from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (playwright.chromium || playwright.default.chromium).launch({headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/kemitraan-humas-audit');
await mkdir(output, {recursive:true});
try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({contentType:'text/html', body:await readFile(`storage/framework/testing/kemitraan-humas/${name}.html`, 'utf8')});
        const root = resolve('public');
        const path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try {
            return route.fulfill({body:await readFile(path), contentType:({'.js':'text/javascript', '.css':'text/css', '.png':'image/png', '.jpg':'image/jpeg', '.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'});
        } catch { return route.fulfill({status:404,body:''}); }
    });
    const views = ['index','rekap','mitra','mou','mitra-form','mou-form','kegiatan','riwayat','upload'];
    for (const [width,height] of [[320,700],[390,844],[768,900],[1366,900]]) {
        await page.setViewportSize({width,height});
        for (const name of views) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${name} melebar pada ${width}px`);
            const clipped = await page.locator('.mitra-page .button, .mitra-page .agenda-tabs a, .mitra-page .agenda-metric').evaluateAll(elements => elements.filter(el => el.scrollHeight > el.clientHeight + 2 || el.scrollWidth > el.clientWidth + 2).map(el => el.textContent.trim()));
            assert.deepEqual(clipped, [], `Teks terpotong ${name}@${width}`);
            const overflow = await page.locator('.mitra-page').evaluateAll(elements => elements.filter(el => el.scrollWidth > el.clientWidth + 2).map(el => el.className));
            assert.deepEqual(overflow, [], `Konten melebar ${name}@${width}`);
            if (name === 'rekap') {
                const excel = new URL(await page.getByRole('link',{name:'Ekspor Excel',exact:true}).getAttribute('href'));
                const print = new URL(await page.getByRole('link',{name:'Cetak rekap MoU',exact:true}).getAttribute('href'));
                assert.equal(excel.search, print.search);
            }
            if (name === 'mou-form') {
                const status = page.getByLabel('Status administrasi', {exact:true});
                const reason = page.getByLabel('Alasan pengakhiran MoU', {exact:true});
                assert.equal(await reason.isVisible(), false);
                await status.selectOption('diakhiri');
                assert.ok(await reason.isVisible());
                assert.equal(await reason.getAttribute('required'), '');
                await status.selectOption('aktif');
                assert.equal(await reason.isVisible(), false);
                for (const id of ['tanggal_mulai','tanggal_selesai','dokumen_humas_id']) assert.equal(await page.locator(`#${id}`).getAttribute('required'), '');
                await status.selectOption('draf');
                for (const id of ['tanggal_mulai','tanggal_selesai','dokumen_humas_id']) assert.equal(await page.locator(`#${id}`).getAttribute('required'), null);
            }
            if (name === 'riwayat') {
                const details = page.locator('.mitra-history details').first();
                await details.locator('summary').click();
                assert.ok(await details.locator('.mitra-diff').first().isVisible());
            }
            if ([390,1366].includes(width) && ['rekap','mitra','mou-form','kegiatan','riwayat'].includes(name)) {
                await page.evaluate(() => document.querySelectorAll('*').forEach(el => {if (el.scrollTop > 0) el.scrollTop = 0;}));
                await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
            }
        }
    }
    await page.goto('http://localhost/audit/mitra-form');
    await page.getByLabel('Nama instansi / mitra', {exact:true}).fill('Mitra audit browser');
    await page.locator('form[data-kemitraan-submit]').evaluate(form => form.addEventListener('submit', event => event.preventDefault()));
    await page.getByRole('button', {name:'Simpan mitra',exact:true}).click();
    assert.equal(await page.locator('form[data-kemitraan-submit]').getAttribute('aria-busy'), 'true');
    assert.ok(await page.getByRole('button', {name:'Menyimpan...',exact:true}).isDisabled());

    await page.addInitScript(() => {
        window.uploadRequests = [];
        window.XMLHttpRequest = class extends EventTarget {
            constructor() {super(); this.upload = new EventTarget(); window.uploadMock = this;}
            open(method,url) {this.method = method; this.url = url;}
            setRequestHeader() {}
            send(data) {window.uploadRequests.push([...data.entries()].map(([k,v]) => [k, typeof v === 'string' ? v : v.name]));}
        };
    });
    await page.goto('http://localhost/audit/upload');
    await page.getByLabel('Berkas dokumen', {exact:true}).setInputFiles({name:'mou-uji.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4\n%%EOF')});
    await page.getByRole('button', {name:'Simpan dokumen',exact:true}).click();
    assert.equal(await page.locator('[data-mou-upload]').getAttribute('aria-busy'), 'true');
    assert.ok(await page.getByRole('button', {name:'Simpan dokumen',exact:true}).isDisabled());
    await page.evaluate(() => window.uploadMock.upload.dispatchEvent(new ProgressEvent('progress', {lengthComputable:true,loaded:50,total:100})));
    assert.match(await page.locator('[data-mou-upload-label]').textContent(), /50%/);
    await page.evaluate(() => window.uploadMock.upload.dispatchEvent(new ProgressEvent('progress', {lengthComputable:true,loaded:100,total:100})));
    assert.match(await page.locator('[data-mou-upload-label]').textContent(), /Sedang menyimpan/);
    assert.equal(await page.evaluate(() => window.dispatchEvent(new Event('beforeunload', {cancelable:true}))), false);
    await page.evaluate(() => {
        window.uploadMock.status = 422;
        window.uploadMock.responseText = JSON.stringify({errors:{berkas:['Berkas terlalu besar.']}});
        window.uploadMock.dispatchEvent(new Event('load'));
    });
    assert.ok(await page.getByRole('button', {name:'Simpan dokumen',exact:true}).isEnabled());
    assert.match(await page.locator('[data-mou-upload-label]').textContent(), /Berkas terlalu besar/);
    assert.equal(await page.evaluate(() => window.dispatchEvent(new Event('beforeunload', {cancelable:true}))), true);
    await page.getByRole('button', {name:'Simpan dokumen',exact:true}).click();
    await page.evaluate(() => window.uploadMock.dispatchEvent(new Event('timeout')));
    assert.ok(await page.getByRole('button', {name:'Simpan dokumen',exact:true}).isEnabled());
    await page.getByRole('button', {name:'Simpan dokumen',exact:true}).click();
    assert.equal(await page.evaluate(() => new Set(window.uploadRequests.map(data => data.find(([k]) => k === 'token_unggahan_mou')[1])).size), 1);
    await page.evaluate(() => {
        window.uploadMock.status = 200;
        window.uploadMock.responseText = JSON.stringify({redirect:'http://localhost/audit/mou',pesan:'Tersimpan'});
        window.uploadMock.dispatchEvent(new Event('load'));
    });
    await page.waitForURL('**/audit/mou');
    await page.emulateMedia({media:'print'});
    await page.goto('http://localhost/audit/cetak');
    assert.equal(await page.locator('.toolbar').isVisible(), false);
    await page.waitForFunction(() => [...document.querySelectorAll('header img')].every(img => img.complete && img.naturalWidth > 0));
    assert.equal(await page.locator('tbody tr').count(), 25);
    await page.pdf({path:`${output}/rekap-kemitraan.pdf`,preferCSSPageSize:true,printBackground:true});
    assert.deepEqual(errors, []);
    console.log('PASS: 9 tampilan pada 320/390/768/1366px, status, riwayat, indikator simpan, unggahan/retry, dan PDF A4.');
} finally {await browser.close();}
