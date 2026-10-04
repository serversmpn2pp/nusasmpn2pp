// Fixtures: NUSA_CAPTURE_AKREDITASI_UI=1 php artisan test --filter=PortofolioAkreditasiHumasTest
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const pw = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (pw.chromium || pw.default.chromium).launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/akreditasi-humas-audit');
await mkdir(output,{recursive:true});
try {
    const page = await browser.newPage(), errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url()), name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({contentType:'text/html',body:await readFile(`storage/framework/testing/akreditasi-humas/${name}.html`,'utf8')});
        if (route.request().method() === 'POST') return route.fulfill({contentType:'text/html',body:'<p>Permintaan diterima</p>'});
        const root = resolve('public'), path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try { return route.fulfill({body:await readFile(path),contentType:({'.png':'image/png','.jpg':'image/jpeg','.js':'text/javascript','.css':'text/css','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'}); }
        catch { return route.fulfill({status:404,body:''}); }
    });
    const names = ['empty','index','form','show','butir','new-butir','ready','limited'];
    for (const width of [320,390,600,768,981,1366,1920]) {
        await page.setViewportSize({width,height:900});
        for (const name of names) {
            await page.goto(`http://localhost/audit/${name}`);
            if (name === 'butir') {
                await page.getByText('Tambah bukti dari Pusat Dokumen Humas',{exact:true}).click();
                await page.getByText('Edit identitas butir',{exact:true}).click();
            }
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),`${name}@${width}: page overflow`);
            const clipped = await page.locator('.ak-head, .ak-row, .ak-doc, .ak-page .button, .ak-facts dd, .ak-page h1, .agenda-metric').evaluateAll(els => els.filter(el => el.scrollWidth > el.clientWidth + 2 || el.scrollHeight > el.clientHeight + 2).map(el => el.textContent.trim().slice(0,80)));
            assert.deepEqual(clipped,[],`${name}@${width}: clipped content`);
            assert.equal(await page.evaluate(() => window.injected),undefined);
            assert.ok(!(await page.content()).includes('BERKAS-PRIVAT.pdf'));
            if (name === 'limited') assert.equal(await page.getByRole('link',{name:'Unduh bukti',exact:true}).count(),0);
            if ([390,1366].includes(width) && ['index','show','butir','ready'].includes(name)) await page.screenshot({path:`${output}/${name}-${width}.png`});
        }
    }
    await page.goto('http://localhost/audit/butir');
    await page.getByText('Tambah bukti dari Pusat Dokumen Humas',{exact:true}).click();
    const radio = page.locator('input[type="radio"]:not(:disabled)').first();
    assert.ok(await page.getByRole('button',{name:'Tautkan bukti',exact:true}).isDisabled());
    await radio.check();
    assert.ok(await radio.isChecked());
    assert.ok(await page.getByRole('button',{name:'Tautkan bukti',exact:true}).isEnabled());
    await page.locator('#catatan_bukti').fill('Bukti kegiatan sekolah');
    const form = radio.locator('xpath=ancestor::form');
    assert.equal(await form.evaluate(el => el.checkValidity()),true);
    await Promise.all([page.waitForURL('**/bukti'),page.getByRole('button',{name:'Tautkan bukti',exact:true}).click()]);
    await page.goto('http://localhost/audit/ready');
    await page.getByText('Revisi & arsip',{exact:true}).click();
    await page.locator('#alasan_status').fill('Revisi dokumen pendukung');
    const dialog = page.waitForEvent('dialog');
    const click = page.getByRole('button',{name:'Simpan status',exact:true}).click();
    await (await dialog).dismiss();
    await click;
    assert.ok(await page.getByRole('button',{name:'Simpan status',exact:true}).isEnabled());
    page.once('dialog', d => d.accept());
    await Promise.all([page.waitForURL('**/status'),page.getByRole('button',{name:'Simpan status',exact:true}).click()]);
    await page.goto('http://localhost/audit/cetak');
    assert.ok(await page.locator('.print-header img').evaluateAll(els => els.every(el => el.complete && el.naturalWidth > 0)));
    await page.emulateMedia({media:'print'});
    assert.equal(await page.locator('.print-toolbar').isVisible(),false);
    await page.pdf({path:`${output}/portofolio-akreditasi.pdf`,preferCSSPageSize:true,printBackground:true});
    await page.screenshot({path:`${output}/cetak.png`,fullPage:true});
    await page.goto('http://localhost/audit/manifest');
    assert.equal(await page.evaluate(() => window.injected),undefined);
    assert.ok(await page.locator('a').count() > 0);
    await page.pdf({path:`${output}/indeks-bundel.pdf`,preferCSSPageSize:true,printBackground:true});
    assert.deepEqual(errors,[]);
    console.log(JSON.stringify({result:'passed',pages:10,widths:[320,390,600,768,981,1366,1920],checks:['responsive','no-clipping','evidence-selection','form-submit','confirmation-cancel-accept','privacy','xss','print-logos','print-toolbar','bundle-index']}));
} finally { await browser.close(); }
