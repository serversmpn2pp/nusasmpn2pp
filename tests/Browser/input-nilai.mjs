// Fixtures: NUSA_CAPTURE_NILAI_UI=1 php artisan test --filter=InputNilaiFilterTest
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (playwright.chromium || playwright.default.chromium).launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/input-nilai-audit');
await mkdir(output,{recursive:true});
try {
    const page = await browser.newPage(), errors = [], submissions = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const request = route.request(), url = new URL(request.url());
        let name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (url.pathname === '/input-nilai') {
            submissions.push({method:request.method(),params:Object.fromEntries(url.searchParams),body:request.postData()});
            name = request.method() === 'POST' || url.searchParams.get('komponen_nilai_id') ? 'selected' : url.searchParams.get('jenis_komponen') === 'sas_saj' ? 'empty' : 'filtered';
        }
        if (url.pathname.startsWith('/publikasi-nilai/')) {
            submissions.push({method:request.method(),body:request.postData()});
            name = 'selected';
        }
        if (name) return route.fulfill({contentType:'text/html',body:await readFile(`storage/framework/testing/input-nilai/${name}.html`,'utf8')});
        const root = resolve('public'), path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try { return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'}); }
        catch { return route.fulfill({status:404,body:''}); }
    });
    for (const [width,height] of [[320,740],[390,844],[768,900],[1366,900]]) {
        await page.setViewportSize({width,height});
        for (const name of ['index','filtered','empty','selected','published','unsaved']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Halaman melebar ${name}@${width}`);
            assert.ok(await page.locator('.grade-filter').evaluate(el => el.scrollWidth <= el.clientWidth + 1), `Filter melebar ${name}@${width}`);
            assert.ok(await page.getByRole('button',{name:'Buka nilai',exact:true}).isVisible());
            assert.equal(await page.locator('[data-grade-filter]').count(),4);
            assert.equal(await page.locator('#komponen_nilai_id').evaluate(el => el.required),false);
            const clipped = await page.locator('.grade-filter .button:visible, .grade-filter label, .grade-component-count').evaluateAll(els => els.filter(el => el.scrollWidth > el.clientWidth + 2 || el.scrollHeight > el.clientHeight + 2).map(el => el.textContent.trim()));
            assert.deepEqual(clipped,[],`Teks terpotong ${name}@${width}`);
            if (['selected','published','unsaved'].includes(name)) {
                assert.ok(await page.locator('[data-grade-form]').isVisible());
                assert.equal(await page.locator('[data-grade-form] [name^="filter["]').count(),4);
                assert.equal(await page.locator('[data-grade-unsaved]').isVisible(),name === 'unsaved');
            }
            if ([390,1366].includes(width) && ['index','selected','empty'].includes(name)) {
                await page.evaluate(() => document.querySelector('.app-content').scrollTop = 0);
                await page.screenshot({path:`${output}/${name}-${width}.png`});
            }
        }
    }
    await page.goto('http://localhost/audit/index');
    let navigation = page.waitForURL('**/input-nilai?**');
    await page.locator('#kelas_id').selectOption({label:'VIII.A Bilingual'});
    await navigation;
    assert.equal(submissions.at(-1).method,'GET');
    assert.ok(submissions.at(-1).params.kelas_id);
    assert.equal(submissions.at(-1).params.komponen_nilai_id,'');
    await page.locator('#komponen_nilai_id').selectOption({index:1});
    assert.equal(await page.locator('[data-grade-form]').count(),0);
    navigation = page.waitForURL('**/input-nilai?**');
    await page.getByRole('button',{name:'Buka nilai',exact:true}).click();
    await navigation;
    assert.ok(submissions.at(-1).params.komponen_nilai_id);
    await page.goto('http://localhost/audit/selected');
    navigation = page.waitForURL('**/input-nilai?**');
    await page.locator('#jenis_komponen').selectOption('sts');
    await navigation;
    assert.equal(submissions.at(-1).params.komponen_nilai_id,'');
    assert.equal(submissions.at(-1).params.jenis_komponen,'sts');
    await page.goto('http://localhost/audit/selected');
    navigation = page.waitForURL('**/input-nilai?**');
    await page.locator('#tahun_pelajaran_id').selectOption({label:'2025/2026'});
    await navigation;
    assert.equal(submissions.at(-1).params.kelas_id,'');
    assert.equal(submissions.at(-1).params.komponen_nilai_id,'');
    await page.goto('http://localhost/audit/selected');
    const values = await page.locator('#filter-input-nilai select').evaluateAll(els => els.map(el => el.value));
    const grade = page.locator('[name^="nilai["]').first();
    await grade.fill('87,50');
    await page.locator('[data-grade-unsaved]').waitFor({state:'visible'});
    assert.equal(await page.locator('[data-grade-unsaved]').innerText(),'1 perubahan belum disimpan');
    const lastCount = submissions.length;
    page.once('dialog', dialog => dialog.dismiss());
    await page.locator('#kelas_id').selectOption({label:'VIII.D'});
    assert.equal(submissions.length,lastCount);
    assert.deepEqual(await page.locator('#filter-input-nilai select').evaluateAll(els => els.map(el => el.value)),values);
    assert.equal(await grade.inputValue(),'87,50');
    page.once('dialog', dialog => dialog.dismiss());
    await page.getByRole('link',{name:'Reset',exact:true}).click();
    assert.equal(submissions.length,lastCount);
    page.once('dialog', dialog => dialog.dismiss());
    await page.getByRole('button',{name:'Publikasikan nilai',exact:true}).click();
    assert.equal(submissions.length,lastCount);
    await grade.fill('80,00');
    assert.equal(await page.locator('[data-grade-unsaved]').isVisible(),false);
    await grade.fill('87,50');
    await page.locator('[name^="catatan["]').first().fill('Perubahan catatan');
    assert.equal(await page.locator('[data-grade-unsaved]').innerText(),'2 perubahan belum disimpan');
    const post = page.waitForRequest(request => request.method() === 'POST' && new URL(request.url()).pathname === '/input-nilai');
    await page.getByRole('button',{name:'Simpan sebagai draf',exact:true}).click();
    const request = await post;
    const body = new URLSearchParams(request.postData());
    assert.ok(body.get('filter[kelas_id]'));
    assert.equal(body.get('filter[jenis_komponen]'),'formatif');
    assert.ok([...body].some(([key,value]) => key.startsWith('nilai[') && value === '87,50'));
    await page.goto('http://localhost/audit/selected');
    await page.locator('[name^="nilai["]').first().fill('101');
    assert.ok(!(await page.locator('[data-grade-form]').evaluate(el => el.checkValidity())));
    await page.locator('[name^="nilai["]').first().fill('88,50');
    page.once('dialog', dialog => dialog.accept());
    navigation = page.waitForURL('**/input-nilai?**');
    await page.locator('#jenis_komponen').selectOption('sas_saj');
    await navigation;
    await page.getByText('Tidak ada komponen nilai yang sesuai',{exact:true}).waitFor();
    assert.deepEqual(errors,[]);
    console.log(JSON.stringify({result:'passed',widths:[320,390,768,1366],pages:6,checks:['filters','direct-component','empty-state','no-overflow','dependent-year-class','unsaved-cancel','unsaved-confirm','save-filters','decimal-input','publication-guard']}));
} finally { await browser.close(); }
