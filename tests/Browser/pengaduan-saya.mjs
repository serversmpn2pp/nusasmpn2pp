// Fixtures: NUSA_CAPTURE_PENGADUAN_ORTU_UI=1 php artisan test --filter=PengaduanOrangTuaTest
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (playwright.chromium || playwright.default.chromium).launch({headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/pengaduan-saya-audit');
await mkdir(output, {recursive:true});
try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({contentType:'text/html',body:await readFile(`storage/framework/testing/pengaduan-saya/${name}.html`, 'utf8')});
        if (url.pathname.startsWith('/pengaduan-saya') || /\/pengaduan-humas\/\d+\/balasan/.test(url.pathname)) return route.fulfill({contentType:'text/html',body:await readFile('storage/framework/testing/pengaduan-saya/active.html', 'utf8')});
        const root = resolve('public');
        const path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try {return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'});}
        catch {return route.fulfill({status:404,body:''});}
    });
    for (const [width,height] of [[320,700],[390,844],[768,900],[1366,900]]) {
        await page.setViewportSize({width,height});
        for (const name of ['index','empty','form','new','active','closed','manager','handler']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Halaman melebar ${name}@${width}`);
            assert.ok(await page.locator('.pengaduan-page').evaluate(el => el.scrollWidth <= el.clientWidth + 2), `Konten melebar ${name}@${width}`);
            const clipped = await page.locator('.pengaduan-page .button, .agenda-metric, .pengaduan-status').evaluateAll(els => els.filter(el => el.scrollHeight > el.clientHeight + 2 || el.scrollWidth > el.clientWidth + 2).map(el => el.textContent.trim()));
            assert.deepEqual(clipped, [], `Teks terpotong ${name}@${width}`);
            if (!['manager','handler'].includes(name)) {
                assert.equal(await page.locator('[name=petugas_pengguna_id], [name=prioritas], [name=nama_pelapor], [name=kontak_pelapor], .publikasi-history').count(), 0);
                assert.ok(!(await page.content()).includes('Tindak lanjut internal penanganan.'));
                assert.equal(await page.locator('.sidebar-link[href$="/pengaduan-saya"]').count(), 1);
            }
            if (name === 'handler') {
                assert.ok(!(await page.content()).includes('Pelapor Orang Tua Rahasia'));
                assert.ok(!(await page.content()).includes('081234567890'));
                assert.equal(await page.getByRole('button', {name:'Kirim balasan resmi',exact:true}).count(), 0);
            }
            if (name === 'closed') assert.equal(await page.locator('textarea').count(), 0);
            if ([390,1366].includes(width) && ['index','form','active','manager'].includes(name)) {
                await page.evaluate(() => {document.querySelector('.app-content').scrollTop = 0; window.scrollTo(0, 0);});
                await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
            }
        }
    }
    // Official and parent messages are native submissions with a CSRF token and stable UUID.
    for (const [fixture,selector,button,action] of [
        ['active','#isi_pesan','Kirim informasi','/informasi'],
        ['manager','#isi_balasan','Kirim balasan resmi','/balasan'],
    ]) {
        await page.goto(`http://localhost/audit/${fixture}`);
        await page.locator(selector).fill('Pesan pengujian aman untuk komunikasi laporan.');
        const sent = page.waitForRequest(req => req.method() === 'POST' && req.url().endsWith(action));
        const navigation = page.waitForNavigation({waitUntil:'load'});
        await page.getByRole('button', {name:button,exact:true}).click();
        const body = new URLSearchParams((await sent).postData());
        assert.equal(body.get('isi_pesan'), 'Pesan pengujian aman untuk komunikasi laporan.');
        assert.ok(body.has('_token') && body.has('versi') && body.has('token_pengiriman'));
        await navigation;
    }
    await page.goto('http://localhost/audit/index');
    const filtered = page.waitForRequest(req => req.isNavigationRequest() && req.url().includes('status=selesai'));
    const filterNavigation = page.waitForNavigation({waitUntil:'load'});
    await page.locator('#status').selectOption('selesai');
    assert.equal(new URL((await filtered).url()).searchParams.get('status'), 'selesai');
    await filterNavigation;
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
    await page.locator('#judul').fill('Laporan pengujian layanan orang tua');
    await page.locator('#isi').fill('Mohon tindak lanjut atas masukan fasilitas pembelajaran sekolah.');
    const foto = {name:'bukti.jpg',mimeType:'image/jpeg',buffer:await readFile('public/images/login-sekolah.jpg')};
    await page.locator('#lampiran').setInputFiles([foto,foto,foto,foto]);
    assert.equal(await page.locator('#lampiran').evaluate(el => el.checkValidity()), false);
    await page.locator('#lampiran').setInputFiles(foto);
    assert.match(await page.locator('[data-file-list]').textContent(), /bukti.jpg/);
    await page.getByRole('button', {name:'Kirim laporan',exact:true}).click();
    assert.equal(await page.locator('[data-publikasi-upload]').getAttribute('aria-busy'), 'true');
    assert.equal(await page.evaluate(() => window.dispatchEvent(new Event('beforeunload', {cancelable:true}))), false);
    const sent = await page.evaluate(() => window.uploadRequests[0]);
    assert.ok(!sent.some(([key]) => ['nama_pelapor','kontak_pelapor','prioritas','status','petugas_pengguna_id'].includes(key)));
    assert.ok(sent.some(([key,value]) => key === 'rahasiakan_identitas' && value === '1'));
    await page.evaluate(() => window.uploadMock.upload.dispatchEvent(new ProgressEvent('progress', {lengthComputable:true,loaded:50,total:100})));
    assert.match(await page.locator('[data-upload-label]').textContent(), /laporan: 50%/);
    await page.evaluate(() => window.uploadMock.upload.dispatchEvent(new ProgressEvent('progress', {lengthComputable:true,loaded:100,total:100})));
    assert.match(await page.locator('[data-upload-label]').textContent(), /Sedang menyimpan laporan/);
    await page.evaluate(() => {
        window.uploadMock.status = 422;
        window.uploadMock.responseText = JSON.stringify({errors:{lampiran:['Lampiran tidak sesuai.']}});
        window.uploadMock.dispatchEvent(new Event('load'));
    });
    assert.ok(await page.getByRole('button', {name:'Kirim laporan',exact:true}).isEnabled());
    assert.match(await page.locator('[data-upload-label]').textContent(), /Lampiran tidak sesuai/);
    await page.getByRole('button', {name:'Kirim laporan',exact:true}).click();
    await page.evaluate(() => window.uploadMock.dispatchEvent(new Event('timeout')));
    assert.ok(await page.getByRole('button', {name:'Kirim laporan',exact:true}).isEnabled());
    await page.getByRole('button', {name:'Kirim laporan',exact:true}).click();
    assert.equal(await page.evaluate(() => new Set(window.uploadRequests.map(data => data.find(([key]) => key === 'token_pembuatan')[1])).size), 1);
    await page.evaluate(() => {
        window.uploadMock.status = 200;
        window.uploadMock.responseText = JSON.stringify({redirect:'http://localhost/audit/new',pesan:'Laporan terkirim.'});
        window.uploadMock.dispatchEvent(new Event('load'));
    });
    await page.waitForURL('**/audit/new');
    assert.deepEqual(errors, []);
    console.log('PASS: 8 tampilan x 4 ukuran, sidebar orang tua, privasi, filter, pesan, unggah, timeout, retry.');
} finally {await browser.close();}
