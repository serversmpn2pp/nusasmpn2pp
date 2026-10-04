// Fixtures: NUSA_CAPTURE_PRESTASI_UI=1 php artisan test --filter=PrestasiSekolahTest
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (playwright.chromium || playwright.default.chromium).launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/prestasi-sekolah-audit');
await mkdir(output, {recursive:true});
try {
    const page = await browser.newPage(), errors = [];
    page.on('pageerror', error => errors.push(error.message));
    let failSearch = false;
    await page.route('**/*', async route => {
        const url = new URL(route.request().url()), name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({contentType:'text/html',body:await readFile(`storage/framework/testing/prestasi-sekolah/${name}.html`,'utf8')});
        if (url.pathname.endsWith('/prestasi-sekolah/pilihan-penerima')) {
            if (failSearch) return route.fulfill({status:500,body:'{}',contentType:'application/json'});
            const payload = JSON.parse(await readFile('storage/framework/testing/prestasi-sekolah/pilihan.json','utf8'));
            payload.next_page = url.searchParams.get('page') === '1' ? 2 : null;
            return route.fulfill({contentType:'application/json',body:JSON.stringify(payload)});
        }
        if (/\/prestasi-sekolah\/\d+\/berkas\/\d+$/.test(url.pathname)) return route.fulfill({body:await readFile('public/images/login-sekolah.jpg'),contentType:'image/jpeg'});
        const root = resolve('public'), path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try { return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'}); }
        catch { return route.fulfill({status:404,body:''}); }
    });
    for (const [width,height] of [[320,700],[390,844],[768,900],[1366,900]]) {
        await page.setViewportSize({width,height});
        for (const name of ['index','empty','statistik','form','edit','show','readonly']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Halaman melebar ${name}@${width}`);
            assert.ok(await page.locator('.prestasi-page').evaluate(el => el.scrollWidth <= el.clientWidth + 2), `Konten melebar ${name}@${width}`);
            const clipped = await page.locator('.button:visible, .agenda-metric, .prestasi-badge, .agenda-tabs a').evaluateAll(els => els.filter(el => el.scrollWidth > el.clientWidth + 2 || el.scrollHeight > el.clientHeight + 2).map(el => el.textContent.trim()));
            assert.deepEqual(clipped, [], `Teks terpotong ${name}@${width}`);
            assert.ok(!(await page.content()).includes('081299999999'));
            if (name === 'readonly') {
                assert.equal(await page.getByRole('link',{name:'Edit prestasi',exact:true}).count(), 0);
                assert.equal(await page.getByRole('link',{name:'Unduh bukti',exact:true}).count(), 0);
            }
            if (['form','edit'].includes(name)) {
                await page.locator('#status').selectOption('draf');
                assert.equal(await page.locator('#tautan').evaluate(el => el.required), false);
                await page.locator('#status').selectOption('terverifikasi');
                assert.ok(await page.locator('[name="konfirmasi_prestasi"]').isVisible());
                assert.ok(await page.locator('[name="konfirmasi_prestasi"]').evaluate(el => el.required));
                if (name === 'form') assert.ok(await page.locator('#tautan').evaluate(el => el.required));
                await page.locator('#status').selectOption('draf');
                await page.locator('#bentuk').selectOption('tim');
                assert.ok(await page.locator('#nama_tim').isVisible());
                assert.ok(await page.locator('#nama_tim').evaluate(el => el.required));
                await page.locator('#bentuk').selectOption('individu');
                assert.equal(await page.locator('#nama_tim').isEnabled(), false);
            }
            if (name === 'show') {
                await page.locator('.publikasi-history summary').first().click();
                assert.ok(await page.locator('.publikasi-snapshot').first().isVisible());
                assert.ok(await page.locator('.kliping-preview').evaluate(el => el.complete && el.naturalWidth > 0));
            }
            if ([390,1366].includes(width) && ['index','statistik','form','show','readonly'].includes(name)) {
                await page.evaluate(() => {document.querySelector('.app-content').scrollTop = 0;window.scrollTo(0,0);});
                await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
            }
        }
    }
    await page.goto('http://localhost/audit/form');
    assert.ok(await page.locator('#cari_penerima').evaluate(el => !el.checkValidity()));
    failSearch = true;
    await page.getByRole('button',{name:'Cari penerima',exact:true}).click();
    await page.getByText('Pencarian gagal. Coba kembali.',{exact:true}).waitFor();
    failSearch = false;
    await page.getByRole('button',{name:'Cari penerima',exact:true}).click();
    await page.locator('[data-results] button').first().waitFor();
    await page.getByRole('button',{name:'Muat berikutnya',exact:true}).click();
    await page.waitForFunction(() => document.querySelector('[data-results]').children.length === 2);
    await page.locator('[data-results] button').first().click();
    assert.equal(await page.locator('[data-selected] li').count(), 1);
    assert.ok(await page.locator('#cari_penerima').evaluate(el => el.checkValidity()));
    await page.locator('[data-results] button').first().click();
    assert.equal(await page.locator('[data-selected] li').count(), 1);
    await page.locator('#bentuk').selectOption('tim');
    await page.getByText('Input nama lama secara manual',{exact:true}).click();
    await page.locator('#nama_manual').fill('<script>alert(1)</script> Nama manual');
    await page.getByRole('button',{name:'Tambahkan penerima',exact:true}).click();
    assert.equal(await page.locator('[data-selected] li').count(), 2);
    assert.equal(await page.evaluate(() => window.__injected), undefined);
    page.once('dialog', dialog => dialog.dismiss());
    await page.locator('#penerima').selectOption('sekolah');
    assert.equal(await page.locator('#penerima').inputValue(), 'siswa');
    page.once('dialog', dialog => dialog.accept());
    await page.locator('#penerima').selectOption('sekolah');
    assert.equal(await page.locator('[data-selected] li').count(), 0);
    assert.equal(await page.locator('#bentuk').inputValue(), 'sekolah');
    assert.equal(await page.locator('[data-participants]').isVisible(), false);
    await page.locator('#nama_kegiatan').fill('Penghargaan sekolah');
    await page.locator('#capaian').fill('Sekolah berprestasi');
    await page.locator('#perolehan').selectOption('penghargaan');
    await page.locator('[name="metode"][value="unggah"]').check();
    await page.locator('#berkas').setInputFiles('public/images/login-sekolah.jpg');
    assert.ok(await page.locator('[data-kliping-preview] img').evaluate(el => el.complete && el.naturalWidth > 0));
    const invalid = await page.locator('[data-prestasi-form]').evaluate(form => [...form.elements].filter(el => el.willValidate && !el.checkValidity()).map(el => el.name || el.id));
    assert.deepEqual(invalid, []);
    await page.evaluate(() => {
        const original = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function(...args) { window.auditUpload = this; return original.apply(this,args); };
    });
    let finishUpload;
    const responseReady = new Promise(resolve => {finishUpload = resolve;});
    await page.route('**/prestasi-sekolah', async route => {
        if (route.request().method() !== 'POST') return route.continue();
        assert.ok(route.request().headers()['content-type'].startsWith('multipart/form-data'));
        assert.ok(route.request().postData().includes('name="berkas"'));
        await responseReady;
        return route.fulfill({status:422,contentType:'application/json',body:JSON.stringify({errors:{berkas:['Pengujian gagal: bukti belum dapat disimpan.']}})});
    });
    await page.getByRole('button',{name:'Simpan prestasi',exact:true}).click();
    assert.ok(await page.getByRole('button',{name:'Menyimpan...',exact:true}).isDisabled());
    assert.ok(await page.locator('[data-upload-state]').isVisible());
    await page.evaluate(() => window.auditUpload.upload.dispatchEvent(new ProgressEvent('progress',{lengthComputable:true,loaded:100,total:100})));
    await page.getByText('Data terkirim. Sedang menyimpan prestasi...', {exact:true}).waitFor();
    finishUpload();
    await page.getByText('Pengujian gagal: bukti belum dapat disimpan.',{exact:true}).waitFor();
    assert.ok(await page.getByRole('button',{name:'Simpan prestasi',exact:true}).isEnabled());
    await page.goto('http://localhost/audit/cetak');
    assert.ok(await page.locator('.print-header img').evaluateAll(els => els.every(el => el.complete && el.naturalWidth > 0)));
    await page.emulateMedia({media:'print'});
    await page.pdf({path:`${output}/rekap-prestasi.pdf`,preferCSSPageSize:true,printBackground:true});
    await page.screenshot({path:`${output}/cetak.png`,fullPage:true});
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({result:'passed',pages:8,widths:[320,390,768,1366],checks:['layout','source-picker','manual-name','type-change-confirmation','verification','file-preview','upload-state','retry','history','private-proof','print']}));
} finally { await browser.close(); }
