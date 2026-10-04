// Capture isolated fixtures: NUSA_CAPTURE_PENGADUAN_UI=1 php artisan test --filter=PengaduanHumasTest
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (playwright.chromium || playwright.default.chromium).launch({headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/pengaduan-humas-audit');
await mkdir(output, {recursive:true});
try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({contentType:'text/html', body:await readFile(`storage/framework/testing/pengaduan-humas/${name}.html`, 'utf8')});
        if (/\/pengaduan-humas\/\d+\/tindakan\//.test(url.pathname)) return route.fulfill({contentType:'text/html',body:await readFile('storage/framework/testing/pengaduan-humas/handler.html', 'utf8')});
        const root = resolve('public');
        const path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try {return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'});}
        catch {return route.fulfill({status:404,body:''});}
    });
    for (const [width,height] of [[320,700],[390,844],[768,900],[1366,900]]) {
        await page.setViewportSize({width,height});
        for (const name of ['index','empty','form','edit','new','assigned','handler','verify','closed','readonly']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Halaman melebar ${name}@${width}`);
            assert.ok(await page.locator('.pengaduan-page').evaluate(el => el.scrollWidth <= el.clientWidth + 2), `Konten melebar ${name}@${width}`);
            const clipped = await page.locator('.pengaduan-page .button, .agenda-metric, .pengaduan-status').evaluateAll(elements => elements.filter(el => el.scrollHeight > el.clientHeight + 2 || el.scrollWidth > el.clientWidth + 2).map(el => el.textContent.trim()));
            assert.deepEqual(clipped, [], `Teks terpotong ${name}@${width}`);
            if (name === 'form') {
                assert.equal(await page.locator('#nama_pelapor').getAttribute('required'), '');
                await page.getByRole('checkbox', {name:'Tanpa identitas pelapor',exact:true}).check();
                assert.ok(await page.locator('#nama_pelapor').isDisabled());
                assert.equal(await page.locator('[data-identitas]').isVisible(), false);
                await page.getByRole('checkbox', {name:'Tanpa identitas pelapor',exact:true}).uncheck();
                assert.ok(await page.locator('#nama_pelapor').isEnabled());
                assert.ok(await page.locator('[data-identitas]').isVisible());
            }
            if (name === 'new') assert.ok(await page.getByRole('button', {name:'Simpan disposisi',exact:true}).isVisible());
            if (['handler','readonly'].includes(name)) {
                assert.equal(await page.locator('[data-identitas-privat], [data-pengaduan-files]').count(), 0);
                assert.equal(await page.getByRole('link', {name:'Koreksi data',exact:true}).count(), 0);
                assert.ok(!(await page.content()).includes('Pelapor Rahasia'));
                assert.ok(!(await page.content()).includes('081234567890'));
            }
            if (name === 'handler') assert.ok(await page.getByRole('button', {name:'Ajukan selesai',exact:true}).isVisible());
            if (name === 'verify') assert.ok(await page.getByRole('button', {name:'Selesaikan tiket',exact:true}).isVisible());
            if (name === 'closed') {
                assert.equal(await page.getByRole('button', {name:'Simpan disposisi',exact:true}).count(), 0);
                assert.equal(await page.getByRole('button', {name:'Selesaikan tiket',exact:true}).count(), 0);
            }
            if (['assigned','handler'].includes(name)) {
                await page.locator('.publikasi-history summary').last().click();
                assert.ok(await page.locator('.publikasi-history details[open] .publikasi-snapshot').isVisible());
                assert.ok(await page.locator('.pengaduan-page').evaluate(el => el.scrollWidth <= el.clientWidth + 2), `Riwayat melebar @${width}`);
            }
            if ([390,1366].includes(width) && ['index','form','assigned','handler','verify'].includes(name)) {
                await page.evaluate(() => {document.querySelector('.app-content').scrollTop = 0; window.scrollTo(0, 0);});
                await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
            }
        }
    }
    await page.goto('http://localhost/audit/index');
    await page.locator('#kata_kunci').fill('sarana');
    await page.locator('#status').selectOption('verifikasi');
    await page.locator('#jenis').selectOption('aspirasi');
    await page.locator('#kategori').selectOption('sarpras');
    await page.locator('#prioritas').selectOption('tinggi');
    await page.locator('#tugas').selectOption('saya');
    await page.locator('#batas').selectOption('lewat');
    await page.locator('#mulai').fill('2026-10-01');
    await page.locator('#sampai').fill('2026-10-05');
    const filtered = page.waitForRequest(req => req.isNavigationRequest() && req.url().includes('kata_kunci=sarana'));
    const filterNavigation = page.waitForNavigation({waitUntil:'load'});
    await page.getByRole('button', {name:'Tampilkan',exact:true}).click();
    assert.deepEqual(Object.fromEntries(new URL((await filtered).url()).searchParams), {kata_kunci:'sarana',status:'verifikasi',jenis:'aspirasi',kategori:'sarpras',prioritas:'tinggi',tugas:'saya',batas:'lewat',mulai:'2026-10-01',sampai:'2026-10-05'});
    await filterNavigation;
    // Native submitter formaction must survive the busy-state button disabling.
    for (const [fixture,button,textarea,action] of [
        ['handler','Ajukan selesai','#catatan_petugas','usulkan-selesai'],
        ['handler','Menunggu informasi','#catatan_petugas','menunggu'],
        ['verify','Tutup dengan alasan','#catatan_hasil','tutup'],
        ['verify','Selesaikan tiket','#catatan_hasil','selesaikan'],
    ]) {
        await page.goto(`http://localhost/audit/${fixture}`);
        await page.locator(textarea).fill('Catatan penanganan tiket untuk pengujian.');
        const sent = page.waitForRequest(req => req.method() === 'POST' && req.url().includes(`/tindakan/${action}`));
        const navigation = page.waitForNavigation({waitUntil:'load'});
        await page.getByRole('button', {name:button,exact:true}).click();
        const body = new URLSearchParams((await sent).postData());
        assert.equal(body.get('catatan'), 'Catatan penanganan tiket untuk pengujian.');
        assert.ok(body.has('versi'));
        await navigation;
        assert.ok(page.url().endsWith(`/tindakan/${action}`));
    }
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
    await page.locator('#judul').fill('Tiket pengujian layanan sekolah');
    await page.locator('#isi').fill('Mohon tindak lanjut perbaikan layanan sekolah.');
    await page.locator('#nama_pelapor').fill('Identitas yang tidak boleh terkirim saat anonim');
    await page.locator('#kontak_pelapor').fill('081234567890');
    await page.getByRole('checkbox', {name:'Tanpa identitas pelapor',exact:true}).check();
    const foto = {name:'bukti.jpg',mimeType:'image/jpeg',buffer:await readFile('public/images/login-sekolah.jpg')};
    await page.locator('#lampiran').setInputFiles([foto,foto,foto,foto]);
    assert.equal(await page.locator('#lampiran').evaluate(el => el.checkValidity()), false);
    await page.locator('#lampiran').setInputFiles(foto);
    assert.match(await page.locator('[data-file-list]').textContent(), /bukti.jpg/);
    await page.getByRole('button', {name:'Simpan tiket',exact:true}).click();
    assert.equal(await page.locator('[data-publikasi-upload]').getAttribute('aria-busy'), 'true');
    assert.ok(await page.getByRole('button', {name:'Menyimpan...',exact:true}).isDisabled());
    assert.equal(await page.evaluate(() => window.dispatchEvent(new Event('beforeunload', {cancelable:true}))), false);
    const sent = await page.evaluate(() => window.uploadRequests[0]);
    assert.ok(!sent.some(([key]) => ['nama_pelapor','kontak_pelapor'].includes(key)));
    assert.ok(sent.some(([key,value]) => key === 'lampiran[]' && value === 'bukti.jpg'));
    await page.evaluate(() => window.uploadMock.upload.dispatchEvent(new ProgressEvent('progress', {lengthComputable:true,loaded:50,total:100})));
    assert.match(await page.locator('[data-upload-label]').textContent(), /tiket: 50%/);
    await page.evaluate(() => window.uploadMock.upload.dispatchEvent(new ProgressEvent('progress', {lengthComputable:true,loaded:100,total:100})));
    assert.match(await page.locator('[data-upload-label]').textContent(), /Sedang menyimpan tiket/);
    await page.evaluate(() => {
        window.uploadMock.status = 422;
        window.uploadMock.responseText = JSON.stringify({errors:{lampiran:['Lampiran tidak sesuai.']}});
        window.uploadMock.dispatchEvent(new Event('load'));
    });
    assert.ok(await page.getByRole('button', {name:'Simpan tiket',exact:true}).isEnabled());
    assert.match(await page.locator('[data-upload-label]').textContent(), /Lampiran tidak sesuai/);
    assert.ok(await page.locator('#nama_pelapor').isDisabled());
    assert.equal(await page.evaluate(() => window.dispatchEvent(new Event('beforeunload', {cancelable:true}))), true);
    await page.getByRole('button', {name:'Simpan tiket',exact:true}).click();
    await page.evaluate(() => window.uploadMock.dispatchEvent(new Event('timeout')));
    assert.ok(await page.getByRole('button', {name:'Simpan tiket',exact:true}).isEnabled());
    await page.getByRole('button', {name:'Simpan tiket',exact:true}).click();
    assert.equal(await page.evaluate(() => new Set(window.uploadRequests.map(data => data.find(([key]) => key === 'token_pembuatan')[1])).size), 1);
    await page.evaluate(() => {
        window.uploadMock.status = 200;
        window.uploadMock.responseText = JSON.stringify({redirect:'http://localhost/audit/new',pesan:'Tiket tersimpan.'});
        window.uploadMock.dispatchEvent(new Event('load'));
    });
    await page.waitForURL('**/audit/new');
    assert.deepEqual(errors, []);
    console.log('PASS: 10 tampilan x 4 ukuran, akses privat, anonim, filter, riwayat, tombol penanganan, upload, timeout, retry.');
} finally {await browser.close();}
