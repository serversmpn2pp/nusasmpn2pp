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
    const sizes = [[320,740],[390,844],[768,900],[901,900],[981,900],[1024,900],[1280,900],[1366,900],[1920,1080]];
    for (const [width,height] of sizes) {
        await page.setViewportSize({width,height});
        for (const name of ['index','filtered','empty','selected','published','unsaved','wide','wide-published','predikat','no-students']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Halaman melebar ${name}@${width}`);
            assert.ok(await page.locator('.grade-filter').evaluate(el => el.scrollWidth <= el.clientWidth + 1), `Filter melebar ${name}@${width}`);
            assert.ok(await page.getByRole('button',{name:'Buka nilai',exact:true}).isVisible());
            assert.equal(await page.locator('[data-grade-filter]').count(),4);
            assert.equal(await page.locator('#komponen_nilai_id').evaluate(el => el.required),false);
            const clipped = await page.locator('.grade-filter .button:visible, .grade-filter label, .grade-component-count').evaluateAll(els => els.filter(el => el.scrollWidth > el.clientWidth + 2 || el.scrollHeight > el.clientHeight + 2).map(el => el.textContent.trim()));
            assert.deepEqual(clipped,[],`Teks terpotong ${name}@${width}`);
            if (['selected','published','unsaved','wide','wide-published','predikat'].includes(name)) {
                assert.ok(await page.locator('[data-grade-form]').isVisible());
                assert.equal(await page.locator('[data-grade-form] [name^="filter["]').count(),4);
                assert.equal(await page.locator('[data-grade-unsaved]').isVisible(),name === 'unsaved');
                const layout = await page.locator('.grade-workspace').evaluate(workspace => {
                    const overview = workspace.querySelector('.grade-overview'), entry = workspace.querySelector('.grade-entry');
                    const overviewBox = overview.getBoundingClientRect(), entryBox = entry.getBoundingClientRect();
                    const wrap = entry.querySelector('.table-wrap');
                    return {below:entryBox.top >= overviewBox.bottom,fullWidth:Math.abs(entryBox.width - workspace.clientWidth) <= 2,noHorizontalScroll:wrap.scrollWidth <= wrap.clientWidth + 1};
                });
                assert.ok(layout.below && layout.fullWidth, `Tabel harus di bawah dan selebar halaman ${name}@${width}`);
                assert.ok(layout.noHorizontalScroll, `Tabel masih perlu gulir ke kanan ${name}@${width}`);
                const borders = await page.locator('.grade-overview').evaluate(overview => {
                    const head = getComputedStyle(overview.querySelector('.grade-overview-head'));
                    const facts = getComputedStyle(overview.querySelector('.grade-facts'));
                    const second = getComputedStyle(overview.querySelector('.grade-facts > div:nth-child(2)'));
                    return {accent:head.borderLeftWidth,top:facts.borderTopWidth,bottom:facts.borderBottomWidth,separator:second.borderLeftWidth};
                });
                assert.equal(borders.accent,'3px');
                assert.equal(borders.top,'1px');
                assert.equal(borders.bottom,'1px');
                if (width > 1100) assert.equal(borders.separator,'1px');
                const clippedGrade = await page.locator('.grade-overview, .grade-entry-head, .grade-table td, .grade-table th, .grade-save-actions .button:visible').evaluateAll(els => els.filter(el => el.scrollWidth > el.clientWidth + 2 || el.scrollHeight > el.clientHeight + 2).map(el => el.textContent.trim().slice(0,80)));
                assert.deepEqual(clippedGrade,[],`Teks/kolom terpotong ${name}@${width}`);
                if (width > 900) {
                    const bounds = await page.locator('.grade-table').evaluate(table => {
                        const row = table.querySelector('tbody tr'), value = row.querySelector('[data-grade-value]'), note = row.querySelector('[name^="catatan["]');
                        const tableRect = table.getBoundingClientRect();
                        return [value,note].every(el => {const rect = el.getBoundingClientRect();return rect.width >= 65 && rect.left >= tableRect.left && rect.right <= tableRect.right;});
                    });
                    assert.ok(bounds,`Nilai dan catatan harus tampak penuh ${name}@${width}`);
                }
            }
            if ([390,1366].includes(width) && ['index','selected','empty'].includes(name)) {
                await page.evaluate(() => document.querySelector('.app-content').scrollTop = 0);
                await page.screenshot({path:`${output}/${name}-${width}.png`});
            }
            if ([390,981,1366].includes(width) && ['wide','wide-published','predikat'].includes(name)) {
                await page.evaluate(() => {
                    const parent = document.querySelector('.app-content'), summary = document.querySelector('.grade-overview'), topbar = document.querySelector('.app-topbar');
                    const distance = summary.getBoundingClientRect().top - topbar.getBoundingClientRect().height - 16;
                    if (getComputedStyle(parent).overflowY === 'auto') parent.scrollTop += distance;
                    else window.scrollBy(0,distance);
                });
                await page.screenshot({path:`${output}/${name}-${width}.png`});
                if (width === 390) {
                    await page.evaluate(() => {
                        const entry = document.querySelector('.grade-entry'), topbar = document.querySelector('.app-topbar');
                        window.scrollBy(0,entry.getBoundingClientRect().top - topbar.getBoundingClientRect().height - 16);
                    });
                    await page.screenshot({path:`${output}/${name}-table-${width}.png`});
                }
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
    await page.goto('http://localhost/audit/predikat');
    await page.locator('[name^="predikat["]').first().selectOption('SB');
    assert.equal(await page.locator('[data-grade-unsaved]').innerText(),'1 perubahan belum disimpan');
    const predikatPost = page.waitForRequest(request => request.method() === 'POST' && new URL(request.url()).pathname === '/input-nilai');
    await page.getByRole('button',{name:'Simpan sebagai draf',exact:true}).click();
    assert.ok([...new URLSearchParams((await predikatPost).postData())].some(([key,value]) => key.startsWith('predikat[') && value === 'SB'));
    assert.deepEqual(errors,[]);
    console.log(JSON.stringify({result:'passed',widths:sizes.map(([width]) => width),pages:10,checks:['filters','direct-component','empty-state','no-overflow','horizontal-overview','full-width-table','no-table-horizontal-scroll','32-students','predikat-input','dependent-year-class','unsaved-cancel','unsaved-confirm','save-filters','decimal-input','publication-guard']}));
} finally { await browser.close(); }
