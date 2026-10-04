// Fixtures: NUSA_CAPTURE_PROGRAM_KOMITE_UI=1 php artisan test --filter=ProgramKomiteHumasTest
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (playwright.chromium || playwright.default.chromium).launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/program-komite-humas-audit');
await mkdir(output, {recursive:true});
try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({contentType:'text/html',body:await readFile(`storage/framework/testing/program-komite-humas/${name}.html`, 'utf8')});
        const root = resolve('public');
        const path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try { return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'}); }
        catch { return route.fulfill({status:404,body:''}); }
    });
    for (const [width,height] of [[320,700],[390,844],[768,900],[1366,900]]) {
        await page.setViewportSize({width,height});
        for (const name of ['index','empty','form','show','edit','agenda','readonly','archive']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Halaman melebar ${name}@${width}`);
            if (name !== 'agenda') assert.ok(await page.locator('.komite-page').evaluate(el => el.scrollWidth <= el.clientWidth + 2), `Konten melebar ${name}@${width}`);
            const clipped = await page.locator('.button:visible, .agenda-metric, .program-badge').evaluateAll(els => els.filter(el => el.scrollWidth > el.clientWidth + 2 || el.scrollHeight > el.clientHeight + 2).map(el => el.textContent.trim()));
            assert.deepEqual(clipped, [], `Teks terpotong ${name}@${width}`);
            assert.ok(!(await page.content()).includes('081234567890'));
            if (['readonly','archive'].includes(name)) {
                assert.equal(await page.getByRole('link',{name:'Edit program',exact:true}).count(), 0);
                assert.equal(await page.getByRole('link',{name:'Buat rapat baru',exact:true}).count(), 0);
            }
            if (['form','edit'].includes(name)) {
                await page.locator('#status_program').selectOption('selesai');
                assert.ok(await page.locator('#capaian').evaluate(el => el.required));
                assert.ok(await page.locator('#pengurus_komite_humas_id').evaluate(el => el.required));
                await page.locator('#status_program').selectOption('dibatalkan');
                assert.ok(await page.locator('#catatan_evaluasi').evaluate(el => el.required));
                assert.equal(await page.locator('#capaian').evaluate(el => el.required), false);
                await page.locator('#status_program').selectOption('rencana');
                await page.locator('#tanggal_mulai').fill('2026-10-20');
                await page.locator('#tanggal_mulai').dispatchEvent('change');
                assert.equal(await page.locator('#tanggal_selesai').getAttribute('min'), '2026-10-20');
            }
            if (name === 'show') {
                await page.locator('.publikasi-history summary').first().click();
                assert.ok(await page.locator('.publikasi-history details[open] .publikasi-snapshot').isVisible());
                await page.locator('.program-unlink summary').click();
                assert.ok(await page.getByLabel('Alasan pelepasan').isVisible());
                assert.ok(await page.locator('.komite-page').evaluate(el => el.scrollWidth <= el.clientWidth + 2));
            }
            if ([390,1366].includes(width) && ['index','show','form','readonly'].includes(name)) {
                await page.evaluate(() => {document.querySelector('.app-content').scrollTop = 0;window.scrollTo(0,0);});
                await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
                if (name === 'show') {
                    await page.getByRole('button',{name:'Hubungkan rapat',exact:true}).scrollIntoViewIfNeeded();
                    await page.screenshot({path:`${output}/show-rapat-${width}.png`,fullPage:true});
                }
            }
        }
    }
    await page.goto('http://localhost/audit/show');
    await page.locator('.program-unlink summary').click();
    await page.getByLabel('Alasan pelepasan').fill('Hubungan rapat salah dipilih.');
    page.once('dialog', dialog => dialog.dismiss());
    await page.getByRole('button',{name:'Lepas hubungan',exact:true}).click();
    assert.ok(await page.getByRole('button',{name:'Lepas hubungan',exact:true}).isEnabled());
    assert.ok(page.url().endsWith('/audit/show'));
    await page.goto('http://localhost/audit/form');
    await page.locator('#nama').fill('Program kerja baru');
    await page.locator('#tujuan').fill('Meningkatkan keterlibatan orang tua');
    await page.locator('#target_hasil').fill('Dua pertemuan terlaksana');
    await page.locator('form[data-program-form]').evaluate(form => form.addEventListener('submit', event => event.preventDefault()));
    await page.getByRole('button',{name:'Simpan program',exact:true}).click();
    assert.ok(await page.getByRole('button',{name:'Menyimpan...',exact:true}).isDisabled());
    await page.evaluate(() => window.dispatchEvent(new Event('pageshow')));
    assert.ok(await page.getByRole('button',{name:'Simpan program',exact:true}).isEnabled());
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({result:'passed',pages:8,widths:[320,390,768,1366],checks:['layout','privacy','status-validation','date-range','history','unlink-cancel','submit-state']}));
} finally {
    await browser.close();
}
