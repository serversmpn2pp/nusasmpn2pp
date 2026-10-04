// Fixtures: NUSA_CAPTURE_KOMITE_UI=1 php artisan test --filter=KomiteHumasTest
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (playwright.chromium || playwright.default.chromium).launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/komite-humas-audit');
await mkdir(output,{recursive:true});
try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({contentType:'text/html',body:await readFile(`storage/framework/testing/komite-humas/${name}.html`, 'utf8')});
        if (url.pathname.endsWith('/komite-humas/pilihan-dokumen')) return route.fulfill({json:{dokumen:[{id:991,judul:'SK Komite 2026'},{id:992,judul:'<img src=x onerror=alert(1)>'}]}});
        if (url.pathname === '/komite-humas') return route.fulfill({contentType:'text/html',body:await readFile('storage/framework/testing/komite-humas/index.html', 'utf8')});
        const root = resolve('public');
        const path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try {return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'});}
        catch {return route.fulfill({status:404,body:''});}
    });
    for (const [width,height] of [[320,700],[390,844],[768,900],[1366,900]]) {
        await page.setViewportSize({width,height});
        for (const name of ['index','empty','form','show','edit','history','archive','readonly','nodoc']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Halaman melebar ${name}@${width}`);
            assert.ok(await page.locator('.komite-page').evaluate(el => el.scrollWidth <= el.clientWidth + 2), `Konten melebar ${name}@${width}`);
            const clipped = await page.locator('.komite-page .button, .agenda-metric, .komite-badge').evaluateAll(els => els.filter(el => el.scrollWidth > el.clientWidth + 2 || el.scrollHeight > el.clientHeight + 2).map(el => el.textContent.trim()));
            assert.deepEqual(clipped, [], `Teks terpotong ${name}@${width}`);
            if (['readonly','nodoc'].includes(name)) {
                assert.ok(!(await page.content()).includes('081234567890'));
                assert.equal(await page.getByRole('link',{name:'Edit kepengurusan',exact:true}).count(), 0);
            }
            if (name === 'nodoc') assert.equal(await page.getByRole('link',{name:'Buka SK',exact:true}).count(), 0);
            if (name === 'form') {
                assert.equal(await page.locator('[data-komite-member]').count(), 3);
                await page.getByRole('button',{name:'Tambah pengurus',exact:true}).click();
                assert.equal(await page.locator('[data-komite-member]').count(), 4);
                await page.locator('[data-remove-member]').nth(1).click();
                assert.equal(await page.locator('[data-komite-member]').count(), 3);
                const names = await page.locator('[data-member-field=nama]').evaluateAll(els => els.map(el => el.name));
                assert.deepEqual(names, ['pengurus[0][nama]','pengurus[1][nama]','pengurus[2][nama]']);
                await page.locator('[data-member-field=aktif]').first().selectOption('0');
                assert.equal(await page.locator('.komite-member--nonaktif').count(), 1);
                await page.locator('[name=metode][value=unggah]').check();
                assert.ok(await page.locator('#berkas').isEnabled());
                assert.ok(await page.locator('#berkas').evaluate(el => el.required));
                await page.locator('[name=metode][value=dokumen]').check();
                assert.ok(await page.locator('#berkas').isDisabled());
                assert.ok(await page.locator('#dokumen_humas_id').isEnabled());
                await page.locator('#status').selectOption('aktif');
                assert.ok(await page.locator('#nomor_sk').evaluate(el => el.required));
                await page.locator('#status').selectOption('draf');
                assert.equal(await page.locator('#nomor_sk').evaluate(el => el.required), false);
            }
            if (name === 'edit') assert.equal(await page.locator('[data-remove-member]').count(), 0);
            if (name === 'history') {
                await page.locator('.publikasi-history summary').first().click();
                assert.ok(await page.locator('.publikasi-history details[open] .publikasi-snapshot').isVisible());
                assert.ok(await page.locator('.komite-page').evaluate(el => el.scrollWidth <= el.clientWidth + 2));
            }
            if ([390,1366].includes(width) && ['index','show','edit','readonly'].includes(name)) {
                await page.evaluate(() => {document.querySelector('.app-content').scrollTop = 0; window.scrollTo(0,0);});
                await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
                if (name === 'edit') {
                    await page.getByRole('button',{name:'Simpan kepengurusan',exact:true}).scrollIntoViewIfNeeded();
                    await page.screenshot({path:`${output}/${name}-bottom-${width}.png`,fullPage:true});
                }
            }
        }
    }
    await page.goto('http://localhost/audit/form');
    await page.locator('[name=metode][value=dokumen]').check();
    await page.locator('#cari_dokumen').fill('komite');
    await page.waitForFunction(() => document.querySelector('[data-dokumen-state]').textContent.includes('2 SK'));
    assert.equal(await page.locator('#dokumen_humas_id option').count(), 3);
    assert.equal(await page.locator('#dokumen_humas_id img').count(), 0);
    await page.locator('#dokumen_humas_id').selectOption('991');
    await page.locator('#cari_dokumen').fill('baru');
    await page.waitForFunction(() => document.querySelector('[data-dokumen-state]').textContent.includes('2 SK'));
    assert.equal(await page.locator('#dokumen_humas_id').inputValue(), '991');
    await page.goto('http://localhost/audit/index');
    const filtered = page.waitForRequest(req => req.isNavigationRequest() && req.url().includes('status=arsip'));
    const navigation = page.waitForNavigation({waitUntil:'load'});
    await page.locator('#status').selectOption('arsip');
    assert.equal(new URL((await filtered).url()).searchParams.get('status'), 'arsip');
    await navigation;
    await page.addInitScript(() => {
        window.uploadRequests = [];
        window.XMLHttpRequest = class extends EventTarget {
            constructor() {super(); this.upload = new EventTarget(); window.uploadMock = this;}
            open() {}
            setRequestHeader() {}
            send(data) {window.uploadRequests.push([...data.entries()].map(([k,v]) => [k,typeof v === 'string' ? v : v.name]));}
        };
    });
    await page.goto('http://localhost/audit/form');
    await page.locator('#nama').fill('Kepengurusan Komite 2026-2029');
    await page.locator('[data-member-field=nama]').first().fill('Ketua Komite');
    await page.locator('[data-member-field=nomor_telepon]').first().fill('081234567890');
    await page.locator('[name=metode][value=unggah]').check();
    const file = {name:'SK-komite.jpg',mimeType:'image/jpeg',buffer:await readFile('public/images/login-sekolah.jpg')};
    await page.locator('#berkas').setInputFiles(file);
    await page.locator('[data-komite-preview] img').waitFor();
    await page.waitForFunction(() => {const img = document.querySelector('[data-komite-preview] img'); return img.complete && img.naturalWidth > 0;});
    const variedPixels = await page.locator('[data-komite-preview] img').evaluate(img => {
        const canvas = document.createElement('canvas'); canvas.width = canvas.height = 16;
        const ctx = canvas.getContext('2d'); ctx.drawImage(img,0,0,16,16);
        const data = ctx.getImageData(0,0,16,16).data;
        return new Set(Array.from({length:256}, (_,i) => `${data[i*4]},${data[i*4+1]},${data[i*4+2]}`)).size;
    });
    assert.ok(variedPixels > 10);
    await page.getByRole('button',{name:'Simpan kepengurusan',exact:true}).click();
    assert.equal(await page.locator('[data-publikasi-upload]').getAttribute('aria-busy'), 'true');
    assert.equal(await page.evaluate(() => window.dispatchEvent(new Event('beforeunload',{cancelable:true}))), false);
    const data = await page.evaluate(() => window.uploadRequests[0]);
    assert.ok(data.some(([k,v]) => k === 'pengurus[0][nama]' && v === 'Ketua Komite'));
    assert.ok(data.some(([k,v]) => k === 'berkas' && v === 'SK-komite.jpg'));
    await page.evaluate(() => window.uploadMock.upload.dispatchEvent(new ProgressEvent('progress',{lengthComputable:true,loaded:100,total:100})));
    assert.match(await page.locator('[data-upload-label]').textContent(), /Sedang menyimpan kepengurusan/);
    await page.evaluate(() => {window.uploadMock.status=422; window.uploadMock.responseText=JSON.stringify({errors:{pengurus:['Susunan belum sesuai.']}}); window.uploadMock.dispatchEvent(new Event('load'));});
    assert.ok(await page.getByRole('button',{name:'Simpan kepengurusan',exact:true}).isEnabled());
    assert.match(await page.locator('[data-upload-label]').textContent(), /Susunan belum sesuai/);
    await page.getByRole('button',{name:'Simpan kepengurusan',exact:true}).click();
    await page.evaluate(() => window.uploadMock.dispatchEvent(new Event('timeout')));
    assert.ok(await page.getByRole('button',{name:'Simpan kepengurusan',exact:true}).isEnabled());
    await page.getByRole('button',{name:'Simpan kepengurusan',exact:true}).click();
    assert.equal(await page.evaluate(() => new Set(window.uploadRequests.map(data => data.find(([k]) => k === 'token_pembuatan')[1])).size), 1);
    await page.evaluate(() => {window.uploadMock.status=200; window.uploadMock.responseText=JSON.stringify({redirect:'http://localhost/audit/show',pesan:'Tersimpan.'}); window.uploadMock.dispatchEvent(new Event('load'));});
    await page.waitForURL('**/audit/show');
    assert.deepEqual(errors, []);
    console.log('PASS: 9 tampilan x 4 ukuran, pengurus dinamis, masa bakti, kontak privat, SK, pencarian, riwayat, preview pixel, upload, retry.');
} finally {await browser.close();}
