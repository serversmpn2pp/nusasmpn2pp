// Fixtures: NUSA_CAPTURE_UMPAN_UI=1 php artisan test --filter=UmpanBalikHumasTest
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const pw = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (pw.chromium || pw.default.chromium).launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/umpan-balik-humas-audit');
await mkdir(output,{recursive:true});
try {
    const page = await browser.newPage(), errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url()), name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({contentType:'text/html',body:await readFile(`storage/framework/testing/umpan-balik-humas/${name}.html`,'utf8')});
        if (route.request().method() === 'POST') return route.fulfill({contentType:'text/html',body:'<p>Permintaan diterima</p>'});
        const root = resolve('public'), path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try { return route.fulfill({body:await readFile(path),contentType:({'.png':'image/png','.jpg':'image/jpeg','.js':'text/javascript','.css':'text/css','.woff2':'font/woff2','.woff':'font/woff'})[extname(path)] || 'application/octet-stream'}); }
        catch { return route.fulfill({status:404,body:''}); }
    });
    const names = ['empty','index','form','draft','show','comments','parent-index','parent-form','parent-sent','parent-closed'];
    for (const width of [320,390,600,768,981,1366,1920]) {
        await page.setViewportSize({width,height:900});
        for (const name of names) {
            await page.goto(`http://localhost/audit/${name}`);
            if (['show','comments'].includes(name)) await page.locator('.uf-disclosure').evaluateAll(els => els.forEach(el => el.open = true));
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),`${name}@${width}: page overflow`);
            const clipped = await page.locator('.uf-head, .uf-row, .uf-page .button, .uf-facts dd, .uf-page h1, .uf-option, .uf-question legend, .agenda-metric').evaluateAll(els => els.filter(el => el.scrollWidth > el.clientWidth + 2 || el.scrollHeight > el.clientHeight + 2).map(el => el.textContent.trim().slice(0,80)));
            assert.deepEqual(clipped,[],`${name}@${width}: clipped content`);
            assert.equal(await page.evaluate(() => window.injected),undefined);
            const main = await page.locator('main.uf-page').innerText();
            if (!name.startsWith('parent')) assert.ok(!main.includes('IDENTITAS PRIVAT'));
            else assert.ok(!main.includes('CATATAN INTERNAL PRIVAT'));
            if ([390,1366].includes(width) && ['form','show','parent-form','parent-sent'].includes(name)) await page.screenshot({path:`${output}/${name}-${width}.png`});
        }
    }
    await page.setViewportSize({width:1366,height:900});
    await page.goto('http://localhost/audit/form');
    const questions = page.locator('#daftar-pertanyaan > fieldset');
    assert.equal(await questions.count(),5);
    assert.ok(await questions.first().locator('[data-up]').isDisabled());
    assert.ok(await questions.last().locator('[data-down]').isDisabled());
    await page.getByRole('button',{name:'Tambah pertanyaan',exact:true}).click();
    assert.equal(await questions.count(),6);
    await questions.last().locator('[data-field="teks"]').fill('Pertanyaan tambahan yang dipindahkan ke urutan sebelumnya.');
    await questions.last().locator('[data-up]').click();
    assert.equal(await questions.nth(4).locator('[data-field="teks"]').inputValue(),'Pertanyaan tambahan yang dipindahkan ke urutan sebelumnya.');
    await questions.nth(4).locator('[data-wajib]').uncheck();
    assert.equal(await questions.nth(4).locator('[data-field="wajib"]').inputValue(),'0');
    page.once('dialog',d => d.accept());
    await questions.nth(4).locator('[data-remove]').click();
    assert.equal(await questions.count(),5);
    await page.locator('#cakupan').selectOption('kelas');
    assert.ok(await page.locator('[data-cakupan="kelas"]').isVisible());
    assert.ok(await page.locator('#tingkat').isDisabled());
    await page.locator('[data-cakupan="kelas"] input').first().check();
    await page.locator('#cakupan').selectOption('tingkat');
    assert.ok(await page.locator('[data-cakupan="kelas"] input').first().isDisabled());
    assert.ok(await page.locator('#tingkat').isEnabled());
    await page.locator('#tingkat').selectOption('7');
    await page.locator('#judul').fill('Evaluasi semester ganjil');
    const editor = page.locator('#uf-editor');
    assert.equal(await editor.evaluate(el => el.checkValidity()),true);
    assert.equal(await editor.evaluate(el => new FormData(el).get('pertanyaan[0][wajib]')),'1');
    assert.equal(await editor.evaluate(el => new FormData(el).getAll('kelas_ids[]').length),0);
    const request = page.waitForRequest(r => r.method() === 'POST');
    await Promise.all([page.waitForURL('**/umpan-balik-humas'),page.getByRole('button',{name:'Simpan draf',exact:true}).click()]);
    assert.ok((await request).postData().includes('cakupan=tingkat'));
    await page.goto('http://localhost/audit/show');
    await page.getByText('Tambah tindak lanjut',{exact:true}).click();
    const follow = page.locator('form').filter({has:page.locator('#uraian-baru')});
    assert.ok(await follow.locator('[data-share]').isDisabled());
    await follow.locator('[data-follow-status]').selectOption('selesai');
    assert.ok(await follow.locator('[data-follow-result]').evaluate(el => el.required));
    await follow.locator('[data-share]').check();
    assert.ok(await follow.locator('[data-public]').isVisible());
    assert.equal(await follow.evaluate(el => el.checkValidity()),false);
    await follow.locator('[data-follow-status]').selectOption('diproses');
    assert.ok(!(await follow.locator('[data-share]').isChecked()));
    assert.ok(await follow.locator('[data-public] textarea').isDisabled());
    await page.getByText('Status & periode pengisian',{exact:true}).click();
    assert.ok(!(await page.locator('#batas-baru-field').isVisible()));
    await page.locator('#status_baru').selectOption('aktif');
    assert.ok(await page.locator('#batas-baru-field').isVisible());
    await page.locator('#status_baru').selectOption('ditutup');
    await page.locator('#alasan_status').fill('Periode evaluasi telah selesai.');
    const dialog = page.waitForEvent('dialog');
    const click = page.getByRole('button',{name:'Simpan status',exact:true}).click();
    await (await dialog).dismiss(); await click;
    assert.ok(await page.getByRole('button',{name:'Simpan status',exact:true}).isEnabled());
    page.once('dialog',d => d.accept());
    await Promise.all([page.waitForURL('**/status'),page.getByRole('button',{name:'Simpan status',exact:true}).click()]);
    await page.goto('http://localhost/audit/parent-form');
    const answer = page.locator('main form');
    assert.equal(await answer.evaluate(el => el.checkValidity()),false);
    await answer.getByLabel('Tidak menilai',{exact:true}).check();
    assert.equal(await answer.evaluate(el => el.checkValidity()),true);
    await answer.locator('textarea').fill('Masukan orang tua untuk sekolah.');
    page.once('dialog',d => d.accept());
    await Promise.all([page.waitForURL('**/umpan-balik-saya/*'),page.getByRole('button',{name:'Kirim jawaban',exact:true}).click()]);
    await page.goto('http://localhost/audit/cetak');
    assert.ok(await page.locator('.print-header img').evaluateAll(els => els.every(el => el.complete && el.naturalWidth > 0)));
    await page.emulateMedia({media:'print'});
    assert.equal(await page.locator('.print-toolbar').isVisible(),false);
    await page.pdf({path:`${output}/rekap-umpan-balik.pdf`,preferCSSPageSize:true,printBackground:true});
    await page.screenshot({path:`${output}/cetak.png`,fullPage:true});
    assert.deepEqual(errors,[]);
    console.log(JSON.stringify({result:'passed',pages:11,widths:[320,390,600,768,981,1366,1920],checks:['responsive','no-clipping','question-edit-reorder','scope-controls','form-payload','follow-up-sharing','deadline-controls','confirmation','parent-required-rating','privacy','xss','print-logos','A4-print']}));
} finally { await browser.close(); }
