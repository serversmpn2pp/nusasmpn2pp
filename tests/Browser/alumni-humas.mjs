// Fixtures: NUSA_CAPTURE_ALUMNI_UI=1 php artisan test --filter=AlumniHumasTest
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (playwright.chromium || playwright.default.chromium).launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const folder = resolve('storage/logs/alumni-humas-audit');
await mkdir(folder, {recursive:true});
try {
    const page = await browser.newPage(), errors = [];
    page.on('pageerror', error => errors.push(error.message));
    let failSearch = false, loadMore = false;
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({contentType:'text/html',body:await readFile(`storage/framework/testing/alumni-humas/${name}.html`, 'utf8')});
        if (url.pathname.endsWith('/alumni-humas/pilihan-siswa')) {
            if (failSearch) return route.fulfill({status:500,contentType:'application/json',body:'{}'});
            const payload = JSON.parse(await readFile('storage/framework/testing/alumni-humas/siswa.json', 'utf8'));
            payload.next_page = loadMore && !url.searchParams.has('page') ? 2 : loadMore && url.searchParams.get('page') === '1' ? 2 : null;
            return route.fulfill({contentType:'application/json',body:JSON.stringify(payload)});
        }
        const root = resolve('public'), path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try { return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'}); }
        catch { return route.fulfill({status:404,body:''}); }
    });
    for (const [width,height] of [[320,700],[390,844],[768,900],[1366,900]]) {
        await page.setViewportSize({width,height});
        for (const name of ['index','empty','statistik','form','edit','linked','show','readonly']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Halaman melebar ${name}@${width}`);
            assert.ok(await page.locator('.alumni-page').evaluate(el => el.scrollWidth <= el.clientWidth + 2), `Konten melebar ${name}@${width}`);
            const clipped = await page.locator('.button:visible, .agenda-metric, .alumni-tag, .agenda-tabs a').evaluateAll(els => els.filter(el => el.scrollWidth > el.clientWidth + 2 || el.scrollHeight > el.clientHeight + 2).map(el => el.textContent.trim()));
            assert.deepEqual(clipped, [], `Teks terpotong ${name}@${width}`);
            if (['index','statistik','empty','readonly'].includes(name)) assert.ok(!(await page.content()).includes('081234567890'));
            if (name === 'readonly') assert.equal(await page.getByRole('link',{name:'Edit alumni',exact:true}).count(), 0);
            if (['form','edit','linked'].includes(name)) {
                await page.locator('#status_penelusuran').selectOption('melanjutkan');
                assert.ok(await page.locator('#nama_sekolah').isVisible());
                assert.ok(await page.locator('#nama_sekolah').evaluate(el => el.required));
                await page.locator('#status_penelusuran').selectOption('tidak_melanjutkan');
                assert.equal(await page.locator('#nama_sekolah').isVisible(), false);
                assert.ok(await page.locator('#catatan_penelusuran').evaluate(el => el.required));
                await page.locator('#status_penelusuran').selectOption('belum_terdata');
                assert.equal(await page.locator('#tanggal_penelusuran').isVisible(), false);
                assert.equal(await page.locator('#tanggal_penelusuran').isEnabled(), false);
                await page.locator('#tahun_lulus').fill('2025');
                await page.locator('#tahun_lulus').dispatchEvent('change');
                assert.equal(await page.locator('#tanggal_lulus').getAttribute('max'), '2025-12-31');
                if (name === 'linked') {
                    assert.ok(await page.locator('#nama_lengkap').evaluate(el => el.readOnly));
                    assert.ok(await page.locator('#kelas_terakhir').evaluate(el => el.readOnly));
                }
            }
            if (name === 'show') {
                await page.locator('.publikasi-history summary').first().click();
                assert.ok(await page.locator('.publikasi-snapshot').first().isVisible());
            }
            if ([390,1366].includes(width) && ['index','statistik','form','show','readonly'].includes(name)) {
                await page.evaluate(() => {document.querySelector('.app-content').scrollTop = 0;window.scrollTo(0,0);});
                await page.screenshot({path:`${folder}/${name}-${width}.png`,fullPage:true});
            }
        }
    }
    await page.goto('http://localhost/audit/form');
    await page.getByLabel('Pilih siswa NUSA', {exact:true}).check();
    assert.ok(await page.locator('#cari_siswa').evaluate(el => !el.checkValidity()));
    failSearch = true;
    await page.getByRole('button', {name:'Cari siswa',exact:true}).click();
    await page.getByText('Gagal memuat siswa. Coba cari kembali.',{exact:true}).waitFor();
    failSearch = false; loadMore = true;
    await page.getByRole('button', {name:'Cari siswa',exact:true}).click();
    await page.locator('[data-source-results] button').first().waitFor();
    await page.getByRole('button', {name:'Muat berikutnya',exact:true}).click();
    await page.waitForFunction(() => document.querySelector('[data-source-results]').children.length === 2);
    await page.locator('[data-source-results] button').first().click();
    assert.equal(await page.locator('#nama_lengkap').inputValue(), 'Siswa NUSA untuk dipilih');
    assert.equal(await page.locator('#nomor_wa').inputValue(), '');
    assert.ok(await page.locator('#cari_siswa').evaluate(el => el.checkValidity()));
    await page.locator('#anggota_kelas_id').selectOption({index:1});
    assert.equal(await page.locator('#kelas_terakhir').inputValue(), 'IX.A');
    assert.equal(await page.locator('#tahun_lulus').inputValue(), '2026');
    await page.getByLabel('Input alumni lama',{exact:true}).check();
    assert.equal(await page.locator('#siswa_id').inputValue(), '');
    assert.equal(await page.locator('#nama_lengkap').evaluate(el => el.readOnly), false);
    assert.equal(await page.locator('#anggota_kelas_id').isEnabled(), false);
    await page.locator('#nama_lengkap').fill('Alumni manual baru');
    await page.locator('[name="konfirmasi_lulus"]').check();
    await page.locator('[data-alumni-form]').evaluate(form => form.addEventListener('submit', e => e.preventDefault()));
    await page.getByRole('button', {name:'Simpan alumni',exact:true}).click();
    assert.ok(await page.getByRole('button',{name:'Menyimpan...',exact:true}).isDisabled());
    await page.evaluate(() => window.dispatchEvent(new Event('pageshow')));
    assert.ok(await page.getByRole('button',{name:'Simpan alumni',exact:true}).isEnabled());
    await page.goto('http://localhost/audit/cetak');
    assert.ok(await page.locator('.print-header img').evaluateAll(els => els.every(el => el.complete && el.naturalWidth > 0)));
    assert.ok(!(await page.content()).includes('081234567890'));
    await page.emulateMedia({media:'print'});
    await page.screenshot({path:`${folder}/cetak.png`,fullPage:true});
    await page.pdf({path:`${folder}/rekap-alumni.pdf`,preferCSSPageSize:true,printBackground:true});
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({result:'passed',pages:9,widths:[320,390,768,1366],checks:['layout','private-contact','source-picker','error-retry','pagination','trace-validation','date-range','history','submit-state','print-logo']}));
} finally { await browser.close(); }
