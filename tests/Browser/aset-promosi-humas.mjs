// Capture isolated fixtures: NUSA_CAPTURE_ASET_UI=1 php artisan test --filter=AsetPromosiHumasTest
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';
import sharp from 'sharp';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (playwright.chromium || playwright.default.chromium).launch({headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/aset-promosi-humas-audit');
await mkdir(output, {recursive:true});
try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({contentType:'text/html', body:await readFile(`storage/framework/testing/aset-promosi-humas/${name}.html`, 'utf8')});
        if (url.pathname === '/aset-promosi-humas/pilihan-dokumen') return route.fulfill({json:{dokumen:[{id:99,judul:'Profil sekolah - <script>uji</script>'}]}});
        if (url.pathname === '/publikasi-humas/pilihan-aset') return route.fulfill({json:{aset:[{id:99,nama:'Video kegiatan - <script>uji</script>',kategori:'Video / audio',versi:1}]}});
        if (/\/aset-promosi-humas\/\d+\/berkas\/\d+$/.test(url.pathname)) return route.fulfill({contentType:'image/jpeg',body:await readFile('public/images/login-sekolah.jpg')});
        const root = resolve('public');
        const path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try {return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'});}
        catch {return route.fulfill({status:404,body:''});}
    });
    for (const [width,height] of [[320,700],[390,844],[768,900],[1366,900]]) {
        await page.setViewportSize({width,height});
        for (const name of ['index','filtered','form','edit','show','pub-form','pub-edit','pub-show']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Halaman melebar ${name}@${width}`);
            assert.ok(await page.locator('.publikasi-page').evaluate(el => el.scrollWidth <= el.clientWidth + 2), `Konten melebar ${name}@${width}`);
            const clipped = await page.locator('.publikasi-page .button, .agenda-metric, .aset-tags span, .aset-pick label').evaluateAll(elements => elements.filter(el => el.scrollHeight > el.clientHeight + 2 || el.scrollWidth > el.clientWidth + 2).map(el => el.textContent.trim()));
            assert.deepEqual(clipped, [], `Teks terpotong ${name}@${width}`);
            const images = page.locator('.publikasi-page img');
            if (await images.count()) {
                await page.waitForFunction(() => [...document.querySelectorAll('.publikasi-page img')].every(img => img.complete && img.naturalWidth > 0));
                const stats = await sharp(await images.first().screenshot()).stats();
                assert.ok(stats.channels.some(channel => channel.stdev > 20), 'Gambar kosong');
            }
            if (name === 'form') {
                await page.getByRole('radio', {name:'Pilih dokumen Humas',exact:true}).check();
                assert.ok(await page.locator('#dokumen_humas_id').isEnabled());
                assert.ok(await page.locator('#berkas').isDisabled());
                await page.getByRole('radio', {name:'Tautan video / media',exact:true}).check();
                assert.ok(await page.locator('#tautan').isEnabled());
                assert.ok(await page.locator('#dokumen_humas_id').isDisabled());
            }
            if (name === 'edit') {
                assert.ok(await page.locator('#tautan').isDisabled());
                await page.getByRole('radio', {name:'Tautan video / media',exact:true}).check();
                assert.equal(await page.locator('#catatan_revisi').getAttribute('required'), '');
                await page.getByRole('radio', {name:'Tidak mengganti berkas / tautan',exact:true}).check();
                assert.equal(await page.locator('#catatan_revisi').getAttribute('required'), null);
            }
            if (name === 'pub-edit') {
                const refresh = page.getByRole('checkbox', {name:'Gunakan versi terbaru (2)',exact:true});
                await refresh.check();
                const selected = page.locator('[name="aset_ids[]"]').first();
                assert.ok(await selected.isChecked());
                await selected.uncheck();
                assert.equal(await refresh.isChecked(), false);
            }
            if ([390,1366].includes(width) && ['filtered','form','show','pub-edit'].includes(name)) await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
        }
    }
    await page.goto('http://localhost/audit/form');
    await page.getByRole('radio', {name:'Pilih dokumen Humas',exact:true}).check();
    await page.locator('#cari_dokumen').fill('Profil');
    await page.waitForFunction(() => document.querySelector('[data-dokumen-state]').textContent.includes('ditemukan'));
    assert.match(await page.locator('#dokumen_humas_id option[value="99"]').textContent(), /<script>uji<\/script>/);
    await page.goto('http://localhost/audit/pub-edit');
    await page.locator('#cari_aset').fill('Video');
    await page.waitForFunction(() => document.querySelector('[data-aset-pick-state]').textContent.includes('ditemukan'));
    assert.ok(await page.locator('[data-aset-id="99"]').isVisible());
    assert.equal(await page.locator('[data-aset-id="99"] script').count(), 0);
    assert.ok(await page.locator('[name="aset_ids[]"]').first().isChecked(), 'Pencarian mempertahankan pilihan lama');
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
    await page.locator('#nama').fill('Foto kegiatan sekolah');
    await page.locator('#berkas').setInputFiles({name:'foto.jpg',mimeType:'image/jpeg',buffer:await readFile('public/images/login-sekolah.jpg')});
    await page.getByRole('button', {name:'Simpan aset',exact:true}).click();
    assert.equal(await page.locator('[data-publikasi-upload]').getAttribute('aria-busy'), 'true');
    assert.ok(await page.getByRole('button', {name:'Menyimpan...',exact:true}).isDisabled());
    assert.equal(await page.evaluate(() => window.dispatchEvent(new Event('beforeunload', {cancelable:true}))), false);
    await page.evaluate(() => window.uploadMock.upload.dispatchEvent(new ProgressEvent('progress', {lengthComputable:true,loaded:50,total:100})));
    assert.match(await page.locator('[data-upload-label]').textContent(), /50%/);
    await page.evaluate(() => window.uploadMock.upload.dispatchEvent(new ProgressEvent('progress', {lengthComputable:true,loaded:100,total:100})));
    assert.match(await page.locator('[data-upload-label]').textContent(), /Sedang menyimpan/);
    await page.evaluate(() => {
        window.uploadMock.status = 422;
        window.uploadMock.responseText = JSON.stringify({errors:{berkas:['Berkas terlalu besar.']}});
        window.uploadMock.dispatchEvent(new Event('load'));
    });
    assert.ok(await page.getByRole('button', {name:'Simpan aset',exact:true}).isEnabled());
    assert.match(await page.locator('[data-upload-label]').textContent(), /Berkas terlalu besar/);
    assert.ok(await page.locator('#tautan').isDisabled(), 'Kolom sumber lain tetap nonaktif setelah gagal');
    await page.getByRole('button', {name:'Simpan aset',exact:true}).click();
    await page.evaluate(() => window.uploadMock.dispatchEvent(new Event('timeout')));
    assert.ok(await page.getByRole('button', {name:'Simpan aset',exact:true}).isEnabled());
    await page.getByRole('button', {name:'Simpan aset',exact:true}).click();
    assert.equal(await page.evaluate(() => new Set(window.uploadRequests.map(data => data.find(([k]) => k === 'token_pembuatan')[1])).size), 1);
    await page.evaluate(() => {
        window.uploadMock.status = 200;
        window.uploadMock.responseText = JSON.stringify({redirect:'http://localhost/audit/show'});
        window.uploadMock.dispatchEvent(new Event('load'));
    });
    await page.waitForURL('**/audit/show');
    assert.deepEqual(errors, []);
    console.log('PASS: 8 tampilan x 4 ukuran, gambar, pilihan sumber, pencarian aman, versi aset, upload, timeout, retry.');
} finally {await browser.close();}
