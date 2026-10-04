// Capture isolated fixtures: NUSA_CAPTURE_KLIPING_UI=1 php artisan test --filter=KlipingBeritaHumasTest
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';
import sharp from 'sharp';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (playwright.chromium || playwright.default.chromium).launch({headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/kliping-berita-humas-audit');
await mkdir(output, {recursive:true});
try {
    const page = await browser.newPage();
    const errors = [];
    let searchFailure = false;
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({contentType:'text/html', body:await readFile(`storage/framework/testing/kliping-berita-humas/${name}.html`, 'utf8')});
        if (url.pathname === '/kliping-berita-humas/pilihan-dokumen') return route.fulfill(searchFailure ? {status:500,body:''} : {json:{dokumen:[{id:99,judul:'Kliping koran - <script>uji</script>'}]}});
        if (/\/kliping-berita-humas\/\d+\/berkas\/\d+$/.test(url.pathname)) return route.fulfill({contentType:'image/jpeg',body:await readFile('public/images/login-sekolah.jpg')});
        const root = resolve('public');
        const path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try {return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'});}
        catch {return route.fulfill({status:404,body:''});}
    });
    for (const [width,height] of [[320,700],[390,844],[768,900],[1366,900]]) {
        await page.setViewportSize({width,height});
        for (const name of ['index','all','empty','form','edit','show','history','readonly']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Halaman melebar ${name}@${width}`);
            assert.ok(await page.locator('.kliping-page').evaluate(el => el.scrollWidth <= el.clientWidth + 2), `Konten melebar ${name}@${width}`);
            const clipped = await page.locator('.kliping-page .button, .agenda-metric, .kliping-tag, .kliping-source label').evaluateAll(elements => elements.filter(el => el.scrollHeight > el.clientHeight + 2 || el.scrollWidth > el.clientWidth + 2).map(el => el.textContent.trim()));
            assert.deepEqual(clipped, [], `Teks terpotong ${name}@${width}`);
            const external = await page.locator('.kliping-page a[target="_blank"]').evaluateAll(links => links.map(link => ({url:link.href, rel:link.rel})));
            assert.ok(external.every(link => /^https?:/.test(link.url) && link.rel.includes('noopener')), 'Tautan eksternal aman');
            const images = page.locator('.kliping-page img');
            if (await images.count()) {
                for (const img of await images.all()) await img.scrollIntoViewIfNeeded();
                await page.waitForFunction(() => [...document.querySelectorAll('.kliping-page img')].every(img => img.complete && img.naturalWidth > 0));
                const stats = await sharp(await images.first().screenshot()).stats();
                assert.ok(stats.channels.some(channel => channel.stdev > 20), 'Gambar bukti kosong');
            }
            if (name === 'form') {
                assert.equal(await page.locator('#tautan').getAttribute('required'), '');
                assert.ok(await page.locator('#berkas').isDisabled());
                await page.getByRole('radio', {name:'Unggah bukti',exact:true}).check();
                assert.ok(await page.locator('#berkas').isEnabled());
                assert.equal(await page.locator('#berkas').getAttribute('required'), '');
                assert.equal(await page.locator('#tautan').getAttribute('required'), null);
                await page.getByRole('radio', {name:'Pilih dokumen Humas',exact:true}).check();
                assert.ok(await page.locator('#dokumen_humas_id').isEnabled());
                assert.ok(await page.locator('#berkas').isDisabled());
                await page.getByRole('radio', {name:'Tautan saja',exact:true}).check();
                assert.ok(await page.locator('#dokumen_humas_id').isDisabled());
            }
            if (name === 'edit') {
                assert.equal(await page.locator('#tautan').getAttribute('required'), null);
                assert.equal(await page.locator('#catatan_perubahan').getAttribute('required'), '');
                await page.getByRole('radio', {name:'Lepaskan bukti dari kliping',exact:true}).check();
                assert.equal(await page.locator('#tautan').getAttribute('required'), '');
                await page.getByRole('radio', {name:'Tidak mengganti bukti',exact:true}).check();
                assert.equal(await page.locator('#tautan').getAttribute('required'), null);
            }
            if (name === 'history') {
                await page.locator('.publikasi-history summary').last().click();
                assert.ok(await page.locator('.publikasi-history details[open] .publikasi-snapshot').isVisible());
                assert.ok(await page.locator('.kliping-page').evaluate(el => el.scrollWidth <= el.clientWidth + 2), `Riwayat melebar @${width}`);
            }
            if (name === 'readonly') assert.equal(await page.getByRole('link', {name:'Edit kliping',exact:true}).count(), 0);
            if ([390,1366].includes(width) && ['index','form','history'].includes(name)) {
                await page.evaluate(() => {
                    document.querySelector('.app-content').scrollTop = 0;
                    window.scrollTo(0, 0);
                });
                await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
            }
        }
    }
    await page.goto('http://localhost/audit/index');
    await page.locator('#kata_kunci').fill('prestasi');
    await page.locator('#nama_media').selectOption({label:'Media Padang'});
    await page.locator('#jenis').selectOption('online');
    await page.locator('#topik').selectOption('prestasi');
    await page.locator('#status').selectOption('semua');
    await page.locator('#mulai').fill('2026-10-01');
    await page.locator('#sampai').fill('2026-10-05');
    const filterRequest = page.waitForRequest(req => req.isNavigationRequest() && req.url().includes('kata_kunci=prestasi'));
    await page.getByRole('button', {name:'Tampilkan',exact:true}).click();
    const query = new URL((await filterRequest).url()).searchParams;
    assert.deepEqual(Object.fromEntries(query), {kata_kunci:'prestasi',nama_media:'Media Padang',jenis:'online',topik:'prestasi',status:'semua',mulai:'2026-10-01',sampai:'2026-10-05'});
    await page.goto('http://localhost/audit/form');
    await page.getByRole('radio', {name:'Pilih dokumen Humas',exact:true}).check();
    await page.locator('#cari_dokumen').fill('Koran');
    await page.waitForFunction(() => document.querySelector('[data-dokumen-state]').textContent.includes('ditemukan'));
    assert.match(await page.locator('#dokumen_humas_id option[value="99"]').textContent(), /<script>uji<\/script>/);
    assert.equal(await page.locator('#dokumen_humas_id script').count(), 0);
    await page.locator('#dokumen_humas_id').selectOption('99');
    searchFailure = true;
    await page.locator('#cari_dokumen').fill('Tidak ditemukan');
    await page.waitForFunction(() => document.querySelector('[data-dokumen-state]').textContent.includes('gagal'));
    assert.equal(await page.locator('#dokumen_humas_id').inputValue(), '99');
    searchFailure = false;
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
    await page.locator('#judul').fill('Foto pemberitaan sekolah');
    await page.locator('#nama_media').fill('Media Pendidikan');
    await page.getByRole('radio', {name:'Unggah bukti',exact:true}).check();
    await page.locator('#berkas').setInputFiles({name:'bukti.jpg',mimeType:'image/jpeg',buffer:await readFile('public/images/login-sekolah.jpg')});
    await page.waitForFunction(() => document.querySelector('[data-kliping-preview] img')?.naturalWidth > 0);
    assert.ok(await page.locator('[data-kliping-preview] img').isVisible());
    await page.getByRole('button', {name:'Simpan kliping',exact:true}).click();
    assert.equal(await page.locator('[data-publikasi-upload]').getAttribute('aria-busy'), 'true');
    assert.ok(await page.getByRole('button', {name:'Menyimpan...',exact:true}).isDisabled());
    assert.equal(await page.evaluate(() => window.dispatchEvent(new Event('beforeunload', {cancelable:true}))), false);
    const submitted = await page.evaluate(() => window.uploadRequests[0]);
    assert.ok(submitted.some(([key,value]) => key === 'berkas' && value === 'bukti.jpg'));
    assert.ok(!submitted.some(([key]) => key === 'dokumen_humas_id'));
    await page.evaluate(() => window.uploadMock.upload.dispatchEvent(new ProgressEvent('progress', {lengthComputable:true,loaded:50,total:100})));
    assert.match(await page.locator('[data-upload-label]').textContent(), /50%/);
    await page.evaluate(() => window.uploadMock.upload.dispatchEvent(new ProgressEvent('progress', {lengthComputable:true,loaded:100,total:100})));
    assert.match(await page.locator('[data-upload-label]').textContent(), /Sedang menyimpan/);
    await page.evaluate(() => {
        window.uploadMock.status = 422;
        window.uploadMock.responseText = JSON.stringify({errors:{berkas:['Berkas terlalu besar.']}});
        window.uploadMock.dispatchEvent(new Event('load'));
    });
    assert.ok(await page.getByRole('button', {name:'Simpan kliping',exact:true}).isEnabled());
    assert.match(await page.locator('[data-upload-label]').textContent(), /Berkas terlalu besar/);
    assert.ok(await page.locator('#dokumen_humas_id').isDisabled(), 'Pilihan bukti lain tetap nonaktif setelah gagal');
    assert.equal(await page.evaluate(() => window.dispatchEvent(new Event('beforeunload', {cancelable:true}))), true);
    await page.getByRole('button', {name:'Simpan kliping',exact:true}).click();
    await page.evaluate(() => window.uploadMock.dispatchEvent(new Event('timeout')));
    assert.ok(await page.getByRole('button', {name:'Simpan kliping',exact:true}).isEnabled());
    await page.getByRole('button', {name:'Simpan kliping',exact:true}).click();
    assert.equal(await page.evaluate(() => new Set(window.uploadRequests.map(data => data.find(([k]) => k === 'token_pembuatan')[1])).size), 1);
    await page.evaluate(() => {
        window.uploadMock.status = 200;
        window.uploadMock.responseText = JSON.stringify({redirect:'http://localhost/audit/show'});
        window.uploadMock.dispatchEvent(new Event('load'));
    });
    await page.waitForURL('**/audit/show');
    assert.deepEqual(errors, []);
    console.log('PASS: 8 tampilan x 4 ukuran, bukti gambar, filter, riwayat, akses baca, pilihan bukti, pencarian aman, upload, timeout, retry.');
} finally {await browser.close();}
