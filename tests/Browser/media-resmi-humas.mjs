// Capture isolated fixtures: NUSA_CAPTURE_MEDIA_UI=1 php artisan test --filter=MediaResmiHumasTest
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await (playwright.chromium || playwright.default.chromium).launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/media-resmi-humas-audit');
await mkdir(output, {recursive:true});
try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if (name) return route.fulfill({contentType:'text/html',body:await readFile(`storage/framework/testing/media-resmi-humas/${name}.html`, 'utf8')});
        if (url.pathname === '/media-resmi-humas') return route.fulfill({contentType:'text/html',body:await readFile('storage/framework/testing/media-resmi-humas/all.html', 'utf8')});
        const root = resolve('public');
        const path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try {return route.fulfill({body:await readFile(path),contentType:({'.js':'text/javascript','.css':'text/css','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2'})[extname(path)] || 'application/octet-stream'});}
        catch {return route.fulfill({status:404,body:''});}
    });
    for (const [width,height] of [[320,700],[390,844],[768,900],[1366,900]]) {
        await page.setViewportSize({width,height});
        for (const name of ['index','all','form','edit','show','history','empty','readonly']) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Halaman melebar ${name}@${width}`);
            assert.ok(await page.locator('.media-page').evaluate(el => el.scrollWidth <= el.clientWidth + 2), `Konten melebar ${name}@${width}`);
            const clipped = await page.locator('.media-page .button, .agenda-metric, .media-type, .media-status, .media-row h2, .media-pic strong').evaluateAll(elements => elements.filter(el => el.scrollHeight > el.clientHeight + 2 || el.scrollWidth > el.clientWidth + 2).map(el => el.textContent.trim()));
            assert.deepEqual(clipped, [], `Teks terpotong ${name}@${width}`);
            const links = await page.locator('.media-page a[target="_blank"]').evaluateAll(elements => elements.map(el => ({href:el.href,rel:el.rel})));
            for (const link of links) {
                assert.ok(/^https?:\/\//.test(link.href));
                assert.match(link.rel, /noopener/);
                assert.match(link.rel, /noreferrer/);
            }
            assert.equal(await page.locator('.media-page input[type="password"]').count(), 0);
            assert.equal(await page.locator('.media-page input[name="access_token"], .media-page input[name="api_key"], .media-page input[name="kata_sandi"]').count(), 0);
            if (name === 'history') {
                await page.locator('.publikasi-history details').first().locator('summary').click();
                assert.ok(await page.locator('.publikasi-history details').first().locator('.publikasi-snapshot').isVisible());
            }
            if (name === 'readonly') assert.equal(await page.getByRole('link', {name:'Edit media',exact:true}).count(), 0);
            if ([390,1366].includes(width) && ['index','form','history'].includes(name)) await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
        }
    }
    await page.goto('http://localhost/audit/index');
    const request = page.waitForRequest(req => req.url().includes('/audit/index?'));
    await page.evaluate(() => {
        document.getElementById('kata_kunci').value = 'Instagram';
        document.getElementById('jenis').value = 'instagram';
        const status = document.getElementById('status');
        status.value = 'semua';
        status.dispatchEvent(new Event('change', {bubbles:true}));
    });
    const filterUrl = new URL((await request).url());
    assert.equal(filterUrl.searchParams.get('kata_kunci'), 'Instagram');
    assert.equal(filterUrl.searchParams.get('jenis'), 'instagram');
    assert.equal(filterUrl.searchParams.get('status'), 'semua');
    await page.waitForLoadState();
    await page.goto('http://localhost/audit/form');
    await page.locator('[data-publikasi-submit]').evaluate(form => form.addEventListener('submit', event => event.preventDefault()));
    await page.getByRole('button', {name:'Simpan media',exact:true}).click();
    assert.ok(await page.getByRole('button', {name:'Simpan media',exact:true}).isEnabled(), 'Form tidak lengkap tidak dikunci');
    await page.locator('#nama').fill('Instagram sekolah');
    await page.locator('#jenis').selectOption('instagram');
    await page.locator('#identitas_akun').fill('@smpn2padangpanjang');
    await page.locator('#tautan').fill('https://instagram.com/smpn2padangpanjang');
    await page.locator('#penanggung_jawab').fill('Petugas Humas');
    await page.getByRole('button', {name:'Simpan media',exact:true}).click();
    assert.equal(await page.locator('[data-publikasi-submit]').getAttribute('aria-busy'), 'true');
    assert.ok(await page.getByRole('button', {name:'Memproses...',exact:true}).isDisabled());
    assert.equal(await page.evaluate(() => window.dispatchEvent(new Event('beforeunload', {cancelable:true}))), true);
    await page.goto('http://localhost/audit/edit');
    assert.equal(await page.locator('#catatan_perubahan').getAttribute('required'), '');
    assert.ok(await page.locator('[name="versi"]').count());
    assert.deepEqual(errors, []);
    console.log('PASS: 8 tampilan x 4 ukuran, tautan aman, tanpa kolom kredensial, filter, riwayat, baca-saja dan indikator proses.');
} finally {await browser.close();}
