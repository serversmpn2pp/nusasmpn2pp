// Fixtures: NUSA_CAPTURE_DASHBOARD_HUMAS_UI=1 php artisan test --filter=DashboardHumasTest
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (playwright.chromium || playwright.default.chromium).launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/dashboard-humas-audit');
await mkdir(output,{recursive:true});
try {
    const page = await browser.newPage(), errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url()), name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({contentType:'text/html',body:await readFile(`storage/framework/testing/dashboard-humas/${name}.html`,'utf8')});
        if (url.pathname === '/dashboard-humas') return route.fulfill({contentType:'text/html',body:await readFile('storage/framework/testing/dashboard-humas/custom.html','utf8')});
        const root = resolve('public'), path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try { return route.fulfill({body:await readFile(path),contentType:({'.png':'image/png','.jpg':'image/jpeg','.js':'text/javascript','.css':'text/css','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'}); }
        catch { return route.fulfill({status:404,body:''}); }
    });
    for (const width of [320,390,600,768,981,1024,1366,1920]) {
        await page.setViewportSize({width,height:900});
        for (const name of ['index','empty','custom','limited']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),`${name}@${width}: document overflow`);
            const clipped = await page.locator('.humas-dashboard, .hd-metric, .hd-table td, .hd-status, .hd-agenda li, .hd-filter .button, .hd-head .button').evaluateAll(els => els.filter(el => el.scrollWidth > el.clientWidth + 2 || el.scrollHeight > el.clientHeight + 2).map(el => el.textContent.trim().slice(0,90)));
            assert.deepEqual(clipped,[],`${name}@${width}: clipped content`);
            for (const secret of ['IDENTITAS RAHASIA','ISI RAHASIA','JUDUL RAHASIA','NAMA TAMU PRIVAT','081299999999','BERKAS PRIVAT.pdf']) assert.ok(!(await page.content()).includes(secret));
            assert.equal(await page.evaluate(() => window.injected),undefined);
            if (name === 'limited') {
                assert.equal(await page.locator('[data-metric]').count(),1);
                assert.equal(await page.locator('[data-metric="prestasi"]').count(),1);
            }
            if (name === 'index') {
                assert.ok(await page.locator('#tanggal_mulai').isDisabled());
                await page.locator('#periode').selectOption('ganjil');
                assert.equal(await page.locator('#tanggal_mulai').inputValue(),'2026-07-01');
                assert.equal(await page.locator('#tanggal_selesai').inputValue(),'2026-12-31');
                await page.locator('#periode').selectOption('genap');
                assert.equal(await page.locator('#tanggal_mulai').inputValue(),'2027-01-01');
                assert.equal(await page.locator('#tanggal_selesai').inputValue(),'2027-06-30');
                await page.locator('#periode').selectOption('kustom');
                assert.ok(await page.locator('#tanggal_mulai').isEnabled());
                assert.ok(await page.locator('#tahun_pelajaran_id').isDisabled());
                await page.locator('#tanggal_mulai').fill('2026-09-01');
                await page.locator('#tanggal_selesai').fill('2026-08-31');
                assert.equal(await page.locator('#tanggal_selesai').evaluate(el => el.checkValidity()),false);
                await page.locator('#periode').selectOption('tahunan');
            }
            if ([390,1366].includes(width) && ['index','limited','empty'].includes(name)) {
                await page.screenshot({path:`${output}/${name}-${width}.png`});
                if (name === 'index') {
                    await page.evaluate(() => {
                        const parent = document.querySelector('.app-content'), section = [...document.querySelectorAll('.hd-section')].find(el => el.textContent.includes('Perlu perhatian saat ini'));
                        const offset = section.getBoundingClientRect().top - document.querySelector('.app-topbar').getBoundingClientRect().height - 16;
                        if (getComputedStyle(parent).overflowY === 'auto') parent.scrollTop += offset; else window.scrollBy(0,offset);
                    });
                    await page.screenshot({path:`${output}/attention-${width}.png`});
                }
            }
        }
    }
    await page.goto('http://localhost/audit/index');
    await page.locator('#periode').selectOption('kustom');
    await page.locator('#tanggal_mulai').fill('2026-09-01');
    await page.locator('#tanggal_selesai').fill('2026-09-30');
    await Promise.all([page.waitForURL('**/dashboard-humas?**'),page.getByRole('button',{name:'Terapkan',exact:true}).click()]);
    const params = new URL(page.url()).searchParams;
    assert.equal(params.get('periode'),'kustom');
    assert.equal(params.get('tanggal_mulai'),'2026-09-01');
    assert.equal(params.get('tanggal_selesai'),'2026-09-30');
    assert.equal(params.has('tahun_pelajaran_id'),false);
    const printLink = new URL(await page.getByRole('link',{name:'Cetak ringkasan',exact:true}).getAttribute('href'));
    assert.equal(printLink.searchParams.get('tanggal_mulai'),'2026-09-01');
    await page.goto('http://localhost/audit/cetak');
    assert.ok(await page.locator('.print-header img').evaluateAll(els => els.every(el => el.complete && el.naturalWidth > 0)));
    await page.emulateMedia({media:'print'});
    assert.ok(!(await page.locator('.print-toolbar').isVisible()));
    await page.pdf({path:`${output}/ringkasan-humas.pdf`,preferCSSPageSize:true,printBackground:true});
    await page.screenshot({path:`${output}/cetak.png`,fullPage:true});
    assert.deepEqual(errors,[]);
    console.log(JSON.stringify({result:'passed',pages:5,widths:[320,390,600,768,981,1024,1366,1920],checks:['permissions','no-overflow','long-titles','no-private-data','semester-dates','custom-dates','invalid-range','submit-filters','print-filter','print-logos']}));
} finally { await browser.close(); }
