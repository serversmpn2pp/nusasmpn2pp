// Fixtures: NUSA_CAPTURE_MAPEL_UI=1 php artisan test --filter=MapelRaporStsTest
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (playwright.chromium || playwright.default.chromium).launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/mapel-sts-audit');
await mkdir(output,{recursive:true});
try {
    const page = await browser.newPage(), errors = [], submissions = [];
    page.on('pageerror',error => errors.push(error.message));
    await page.route('**/*',async route => {
        const request = route.request(), url = new URL(request.url());
        let name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (/^\/rapor-sts\/\d+\/\d+\/mapel$/.test(url.pathname)) {
            submissions.push(request.postData()); name = 'selected';
        }
        if (name) return route.fulfill({contentType:'text/html',body:await readFile(`storage/framework/testing/mapel-sts/${name}.html`,'utf8')});
        const root = resolve('public'), path = resolve(root,'.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try { return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'}); }
        catch { return route.fulfill({status:404,body:''}); }
    });
    const sizes = [[320,740],[390,844],[768,900],[1024,900],[1366,950],[1920,1080]];
    for (const [width,height] of sizes) {
        await page.setViewportSize({width,height});
        for (const name of ['default','selected','readonly']) {
            await page.goto(`http://localhost/audit/${name}`);
            const details = page.locator('[data-sts-mapel-details]');
            await details.locator(':scope > summary').click();
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),`Luapan halaman ${name}@${width}`);
            assert.equal(await page.locator('[data-sts-mapel-choice]').count(),3);
            const clipped = await page.locator('.sts-subject-section label, .sts-subject-section button, .sts-subject-scope').evaluateAll(els => els.filter(el => el.scrollWidth > el.clientWidth + 1).map(el => el.textContent.trim()));
            assert.deepEqual(clipped,[],`Teks terpotong ${name}@${width}`);
            assert.equal(await page.locator('#sts-mapel-form').count(),name === 'readonly' ? 0 : 1);
            const check = page.getByLabel('Pendidikan Inklusi',{exact:false});
            assert.equal(await check.isChecked(),name === 'default');
            if (name === 'readonly') assert.ok(await check.isDisabled());
            assert.equal(await page.locator('[data-sts-mapel-choice]:disabled').count(),name === 'readonly' ? 3 : 2);
            if ([390,1366].includes(width)) {
                await page.locator('.sts-subject-section').evaluate(el => {
                    const parent = document.querySelector('.app-content'), top = document.querySelector('.app-topbar').getBoundingClientRect().height;
                    if (getComputedStyle(parent).overflowY === 'auto') parent.scrollTop += el.getBoundingClientRect().top - top - 16;
                    else window.scrollBy(0,el.getBoundingClientRect().top - top - 16);
                });
                await page.screenshot({path:`${output}/${name}-${width}.png`});
            }
        }
    }
    await page.setViewportSize({width:1366,height:950});
    await page.goto('http://localhost/audit/default');
    await page.locator('[data-sts-mapel-details] > summary').click();
    const inclusion = page.getByLabel('Pendidikan Inklusi',{exact:false});
    await inclusion.uncheck();
    assert.ok(await page.locator('[data-sts-mapel-unsaved]').isVisible());
    assert.equal(await page.locator('[data-sts-mapel-count]').innerText(),'2 mapel diikutkan · 1 dikecualikan');
    assert.equal(await page.locator('#sts-mapel-form').evaluate(form => form.checkValidity()),false);
    await page.locator('#sts-mapel-alasan').fill('Pendidikan Inklusi tidak menyelenggarakan STS.');
    page.once('dialog',dialog => dialog.dismiss());
    await page.getByRole('button',{name:'Simpan pilihan mapel',exact:true}).click();
    assert.equal(submissions.length,0);
    page.once('dialog',dialog => dialog.accept());
    const post = page.waitForRequest(request => /\/rapor-sts\/\d+\/\d+\/mapel$/.test(new URL(request.url()).pathname));
    await page.getByRole('button',{name:'Simpan pilihan mapel',exact:true}).click();
    const body = new URLSearchParams((await post).postData());
    assert.equal(body.get('_method'),'PUT');
    assert.equal(body.getAll('mapel_ids[]').length,2);
    assert.match(body.get('sidik_mapel'),/^[a-f0-9]{64}$/);
    assert.equal(body.get('versi_mapel'),'0');
    await page.goto('http://localhost/audit/selected');
    await page.locator('[data-sts-mapel-details] > summary').click();
    await page.getByLabel('Pendidikan Inklusi',{exact:false}).check();
    page.once('dialog',dialog => dialog.accept());
    let prevented = false;
    await page.locator('[data-sts-print]').first().evaluate(link => {
        window.printWasPrevented = false;
        link.addEventListener('click',event => {window.printWasPrevented = event.defaultPrevented; event.preventDefault();});
        link.click();
    });
    prevented = await page.evaluate(() => window.printWasPrevented);
    assert.equal(prevented,true,'Cetak harus diblokir jika pilihan belum disimpan');
    assert.deepEqual(errors,[]);
    console.log(JSON.stringify({result:'passed',widths:sizes.map(([w]) => w),pages:3,checks:['responsive','locked-sources','readonly-role','selection-counter','required-reason','cancel-confirmation','save-selection','unsaved-print-guard']}));
} finally { await browser.close(); }
