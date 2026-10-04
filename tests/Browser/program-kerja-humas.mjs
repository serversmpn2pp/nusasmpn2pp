// Fixtures: NUSA_CAPTURE_PROGRAM_HUMAS_UI=1 php artisan test --filter=ProgramKerjaHumasTest
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (playwright.chromium || playwright.default.chromium).launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/program-kerja-humas-audit');
await mkdir(output, {recursive:true});
try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.addInitScript(() => {
        const send = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function(...args) { window.auditUpload = this; return send.apply(this, args); };
    });
    let mode = 'normal';
    let sent;
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (route.request().method() === 'POST' && url.pathname.endsWith('/bukti')) {
            sent = route.request().postDataBuffer().toString();
            if (mode === 'delay') {
                await new Promise(resolve => setTimeout(resolve, 3000));
                return route.fulfill({status:422,contentType:'application/json',body:JSON.stringify({errors:{berkas:['Konfirmasi belum tersedia. Coba kembali.']}})});
            }
        }
        const name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({contentType:'text/html',body:await readFile(`storage/framework/testing/program-kerja-humas/${name}.html`, 'utf8')});
        const root = resolve('public');
        const path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try {return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'});}
        catch {return route.fulfill({status:404,body:''});}
    });
    const views = ['index','form','edit','show','laporan-form','laporan-draf','laporan-final','readonly','cetak-program','cetak-laporan'];
    for (const [width,height] of [[320,700],[390,844],[768,900],[1366,900]]) {
        await page.setViewportSize({width,height});
        for (const name of views) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Halaman melebar ${name}@${width}`);
            const clipped = await page.locator('.button:visible, .agenda-metric, .program-badge').evaluateAll(els => els.filter(el => el.scrollWidth > el.clientWidth + 2 || el.scrollHeight > el.clientHeight + 2).map(el => el.textContent.trim()));
            assert.deepEqual(clipped, [], `Teks terpotong ${name}@${width}`);
            if (['form','edit'].includes(name)) {
                await page.locator('#status_program').selectOption('dibatalkan');
                assert.ok(await page.locator('#evaluasi').evaluate(el => el.required));
                await page.locator('#status_program').selectOption('rencana');
                assert.equal(await page.locator('#evaluasi').evaluate(el => el.required), false);
                await page.locator('#tanggal_mulai').fill('2026-10-20');
                await page.locator('#tanggal_mulai').dispatchEvent('change');
                assert.equal(await page.locator('#tanggal_selesai').getAttribute('min'), '2026-10-20');
            }
            if (name === 'readonly') {
                assert.equal(await page.getByRole('button',{name:'Buka revisi',exact:true}).count(), 0);
                assert.equal(await page.getByRole('link',{name:'Edit laporan',exact:true}).count(), 0);
            }
            if (name === 'laporan-final') {
                assert.equal(await page.getByRole('link',{name:'Edit laporan',exact:true}).count(), 0);
                assert.equal(await page.getByRole('button',{name:'Unggah bukti',exact:true}).count(), 0);
                assert.ok(await page.getByRole('button',{name:'Buka revisi',exact:true}).isVisible());
            }
            if (name.startsWith('cetak-')) {
                assert.equal(await page.locator('img').evaluateAll(els => els.filter(img => !img.complete || !img.naturalWidth).length), 0);
            }
            if ([390,1366].includes(width) && ['index','show','laporan-draf','laporan-final','cetak-laporan'].includes(name)) {
                await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
            }
        }
    }
    await page.goto('http://localhost/audit/form');
    for (const [key,value] of Object.entries({nama:'Program hubungan orang tua',tujuan:'Menguatkan komunikasi',sasaran:'Orang tua siswa',target_hasil:'Dua pertemuan terlaksana'})) await page.locator(`#${key}`).fill(value);
    await page.locator('form[data-humas-program-form]').evaluate(form => form.addEventListener('submit', event => event.preventDefault()));
    await page.getByRole('button',{name:'Simpan program',exact:true}).click();
    assert.ok(await page.getByRole('button',{name:'Menyimpan...',exact:true}).isDisabled());
    await page.evaluate(() => window.dispatchEvent(new Event('pageshow')));
    assert.ok(await page.getByRole('button',{name:'Simpan program',exact:true}).isEnabled());
    await page.goto('http://localhost/audit/laporan-draf');
    const token = await page.locator('[data-publikasi-upload] [name=token_pembuatan]').inputValue();
    await page.locator('#judul_bukti').fill('Foto kegiatan');
    await page.locator('#berkas').setInputFiles('public/images/login-sekolah.jpg');
    mode = 'delay';
    await page.getByRole('button',{name:'Unggah bukti',exact:true}).click();
    await page.waitForFunction(() => document.querySelector('[data-publikasi-upload]').getAttribute('aria-busy') === 'true');
    assert.ok(await page.locator('[data-publikasi-upload]').getAttribute('aria-busy'));
    assert.ok(await page.getByRole('button',{name:'Menyimpan...',exact:true}).isDisabled());
    await page.evaluate(() => window.auditUpload.upload.dispatchEvent(new ProgressEvent('progress', {lengthComputable:true,loaded:100,total:100})));
    assert.ok((await page.locator('[data-upload-label]').textContent()).includes('Sedang menyimpan'));
    await page.waitForFunction(() => document.querySelector('[data-upload-state]').classList.contains('publikasi-upload--error'));
    assert.ok(await page.getByRole('button',{name:'Unggah bukti',exact:true}).isEnabled());
    assert.equal(await page.locator('[data-publikasi-upload] [name=token_pembuatan]').inputValue(), token);
    assert.ok(sent.includes(token));
    await page.setViewportSize({width:1366,height:900});
    for (const name of ['cetak-program','cetak-laporan']) {
        await page.goto(`http://localhost/audit/${name}`);
        await page.emulateMedia({media:'print'});
        assert.equal(await page.locator('.print-toolbar').isVisible(), false);
        await page.pdf({path:`${output}/${name}.pdf`,preferCSSPageSize:true,printBackground:true});
        await page.emulateMedia({media:'screen'});
    }
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({result:'passed',pages:views.length,widths:[320,390,768,1366],checks:['layout','print-assets','readonly','final-lock','date-validation','submit-state','upload-processing-and-retry','A4-PDF']}));
} finally {
    await browser.close();
}
