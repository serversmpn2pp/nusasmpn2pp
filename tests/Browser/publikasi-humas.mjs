// NUSA_CAPTURE_PUBLIKASI_UI=1 php artisan test --filter=PublikasiHumasTest
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';
import sharp from 'sharp';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (playwright.chromium || playwright.default.chromium).launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/publikasi-humas-audit');
await mkdir(output,{recursive:true});
try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({contentType:'text/html',body:await readFile(`storage/framework/testing/publikasi-humas/${name}.html`,'utf8')});
        if (/\/publikasi-humas\/\d+\/lampiran\/\d+$/.test(url.pathname)) return route.fulfill({contentType:'image/jpeg',body:await readFile('public/images/login-sekolah.jpg')});
        const root = resolve('public');
        const path = resolve(root,'.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try {return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'});}
        catch {return route.fulfill({status:404,body:''});}
    });
    const views = ['index','filtered','form','edit','draft','pending','review','revision','approved','published'];
    for (const [width,height] of [[320,700],[390,844],[768,900],[1366,900]]) {
        await page.setViewportSize({width,height});
        for (const name of views) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${name} melebar pada ${width}px`);
            const clipped = await page.locator('.publikasi-page .button, .publikasi-page .agenda-tabs a, .publikasi-page .agenda-metric, .publikasi-page .publikasi-status').evaluateAll(elements => elements.filter(el => el.scrollHeight > el.clientHeight + 2 || el.scrollWidth > el.clientWidth + 2).map(el => el.textContent.trim()));
            assert.deepEqual(clipped,[],`Teks terpotong ${name}@${width}`);
            assert.ok(await page.locator('.publikasi-page').evaluate(el => el.scrollWidth <= el.clientWidth + 2), `Konten melebar ${name}@${width}`);
            const photos = page.locator('.publikasi-photo img');
            if (await photos.count()) {
                await page.waitForFunction(() => [...document.querySelectorAll('.publikasi-photo img')].every(img => img.complete && img.naturalWidth > 0));
                const pixels = await sharp(await photos.first().screenshot()).stats();
                assert.ok(pixels.channels.some(channel => channel.stdev > 20),'Foto kosong');
            }
            if (name === 'review') {
                await page.getByText('Minta perbaikan konten',{exact:true}).click();
                assert.ok(await page.getByLabel('Catatan perbaikan',{exact:true}).isVisible());
                assert.equal(await page.getByLabel('Catatan perbaikan',{exact:true}).getAttribute('required'),'');
            }
            if (name === 'approved') {
                await page.getByText('Buka revisi sebelum tayang',{exact:true}).click();
                assert.ok(await page.getByLabel('Alasan revisi',{exact:true}).isVisible());
            }
            if (['draft','revision'].includes(name)) assert.ok(await page.getByRole('button',{name:'Ajukan ke pimpinan',exact:true}).isVisible());
            if (['pending','approved','published'].includes(name)) assert.equal(await page.getByRole('link',{name:'Edit draf',exact:true}).count(),0);
            if (name === 'published') assert.equal(await page.getByRole('button',{name:'Simpan bukti tayang',exact:true}).count(),0);
            if ([390,1366].includes(width) && ['index','form','review','approved','published'].includes(name)) {
                await page.evaluate(() => document.querySelectorAll('*').forEach(el => {if (el.scrollTop > 0) el.scrollTop = 0;}));
                await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
            }
        }
    }
    await page.goto('http://localhost/audit/review');
    await page.locator('form[data-publikasi-submit]').first().evaluate(form => form.addEventListener('submit',event => event.preventDefault()));
    await page.getByRole('button',{name:'Setujui konten',exact:true}).click();
    assert.ok(await page.getByRole('button',{name:'Memproses...',exact:true}).isDisabled());
    assert.equal(await page.evaluate(() => window.dispatchEvent(new Event('beforeunload',{cancelable:true}))),true,'Pengiriman form biasa tidak memunculkan peringatan keluar');
    await page.addInitScript(() => {
        window.uploadRequests = [];
        window.XMLHttpRequest = class extends EventTarget {
            constructor() {super(); this.upload = new EventTarget(); window.uploadMock = this;}
            open(method,url) {this.method = method; this.url = url;}
            setRequestHeader() {}
            send(data) {window.uploadRequests.push([...data.entries()].map(([k,v]) => [k,typeof v === 'string' ? v : v.name]));}
        };
    });
    await page.goto('http://localhost/audit/form');
    await page.getByLabel('Judul',{exact:true}).fill('Kegiatan sekolah audit browser');
    await page.getByLabel('Isi berita / pengumuman',{exact:true}).fill('Naskah konten yang akan diuji bersama foto kegiatan sekolah.');
    await page.locator('#foto').setInputFiles({name:'foto-kegiatan.jpg',mimeType:'image/jpeg',buffer:await readFile('public/images/login-sekolah.jpg')});
    await page.waitForFunction(() => document.querySelector('[data-foto-preview] img')?.naturalWidth > 0);
    await page.getByRole('button',{name:'Simpan draf',exact:true}).click();
    assert.equal(await page.locator('[data-publikasi-upload]').getAttribute('aria-busy'),'true');
    assert.ok(await page.getByRole('button',{name:'Menyimpan...',exact:true}).isDisabled());
    await page.evaluate(() => window.uploadMock.upload.dispatchEvent(new ProgressEvent('progress',{lengthComputable:true,loaded:50,total:100})));
    assert.match(await page.locator('[data-upload-label]').textContent(),/50%/);
    await page.evaluate(() => window.uploadMock.upload.dispatchEvent(new ProgressEvent('progress',{lengthComputable:true,loaded:100,total:100})));
    assert.match(await page.locator('[data-upload-label]').textContent(),/Sedang menyimpan/);
    assert.equal(await page.evaluate(() => window.dispatchEvent(new Event('beforeunload',{cancelable:true}))),false);
    await page.evaluate(() => {
        window.uploadMock.status = 422;
        window.uploadMock.responseText = JSON.stringify({errors:{foto:['Berkas terlalu besar.']}});
        window.uploadMock.dispatchEvent(new Event('load'));
    });
    assert.match(await page.locator('[data-upload-label]').textContent(),/Berkas terlalu besar/);
    assert.ok(await page.getByRole('button',{name:'Simpan draf',exact:true}).isEnabled());
    assert.equal(await page.evaluate(() => window.dispatchEvent(new Event('beforeunload',{cancelable:true}))),true);
    await page.getByRole('button',{name:'Simpan draf',exact:true}).click();
    await page.evaluate(() => window.uploadMock.dispatchEvent(new Event('timeout')));
    assert.ok(await page.getByRole('button',{name:'Simpan draf',exact:true}).isEnabled());
    await page.getByRole('button',{name:'Simpan draf',exact:true}).click();
    assert.equal(await page.evaluate(() => new Set(window.uploadRequests.map(data => data.find(([k]) => k === 'token_pembuatan')[1])).size),1);
    await page.evaluate(() => {
        window.uploadMock.status = 200;
        window.uploadMock.responseText = JSON.stringify({redirect:'http://localhost/audit/draft',pesan:'Draf tersimpan'});
        window.uploadMock.dispatchEvent(new Event('load'));
    });
    await page.waitForURL('**/audit/draft');
    await page.goto('http://localhost/audit/approved');
    await page.getByLabel('Tautan publikasi',{exact:true}).fill('https://sekolah.test/berita');
    await page.getByRole('button',{name:'Simpan bukti tayang',exact:true}).click();
    assert.equal(await page.locator('[data-publikasi-upload]').getAttribute('aria-busy'),'true');
    await page.evaluate(() => window.uploadMock.dispatchEvent(new Event('error')));
    assert.ok(await page.getByRole('button',{name:'Simpan bukti tayang',exact:true}).isEnabled());
    assert.deepEqual(errors,[]);
    console.log('PASS: 10 tampilan pada 320/390/768/1366px, foto, pemeriksaan, penguncian, indikator unggah, timeout dan retry.');
} finally {await browser.close();}
