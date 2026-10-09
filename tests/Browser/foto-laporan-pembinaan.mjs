// Fixtures: NUSA_CAPTURE_FOTO_LAPORAN=1 php vendor/phpunit/phpunit/phpunit tests/Feature/FotoLaporanPembinaanTest.php
import assert from 'node:assert/strict';
import {mkdir, readFile} from 'node:fs/promises';
import {extname, resolve, sep} from 'node:path';
import {pathToFileURL} from 'node:url';

const playwright = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await playwright.chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/foto-laporan-audit');
await mkdir(output, {recursive: true});

try {
    const page = await browser.newPage(), errors = [];
    let failPhoto = false;
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const name = url.pathname.match(/^\/audit\/(foto|inisial)$/)?.[1];
        if (name) return route.fulfill({contentType: 'text/html', body: await readFile(`storage/framework/testing/foto-laporan/${name}.html`, 'utf8')});
        if (url.pathname === '/storage/siswa/foto/siswa-uji.png') {
            if (failPhoto) return route.fulfill({status: 404, body: ''});
            return route.fulfill({contentType: 'image/png', body: await readFile('storage/framework/testing/foto-laporan/foto.png')});
        }
        const root = resolve('public'), path = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!path.startsWith(root + sep)) return route.abort();
        try {
            return route.fulfill({body: await readFile(path), contentType: ({'.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.woff2': 'font/woff2'})[extname(path)] || 'application/octet-stream'});
        } catch {
            return route.fulfill({status: 404, body: ''});
        }
    });

    for (const width of [360, 768, 1366]) {
        await page.setViewportSize({width, height: 1000});
        failPhoto = false;
        await page.goto('http://localhost/audit/foto');
        const avatar = page.locator('[data-student-avatar]');
        const photo = page.locator('[data-student-photo]');
        const fallback = page.locator('[data-student-photo-fallback]');
        assert.ok(await photo.isVisible());
        assert.ok(await fallback.isHidden());
        assert.ok(await photo.evaluate(image => image.complete && image.naturalWidth > 0));
        assert.equal(await photo.evaluate(image => getComputedStyle(image).objectFit), 'contain');
        assert.ok(await photo.evaluate(image => {
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 32;
            const context = canvas.getContext('2d');
            context.drawImage(image, 0, 0, 32, 32);
            const pixels = context.getImageData(0, 0, 32, 32).data;
            const colors = new Set();
            for (let i = 0; i < pixels.length; i += 4) colors.add(Array.from(pixels.slice(i, i + 4)).join(','));
            return colors.size > 1;
        }), `Blank photo @${width}`);
        const loadedSize = await avatar.boundingBox();
        assert.ok(await avatar.evaluate(element => {
            const rect = element.getBoundingClientRect();
            const name = element.closest('.detail-profile').querySelector('h2').getBoundingClientRect();
            return rect.left >= 0 && rect.right <= innerWidth && rect.bottom <= name.top;
        }), `Photo overlaps student identity @${width}`);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Overflow @${width}`);
        await avatar.scrollIntoViewIfNeeded();
        await page.screenshot({path: `${output}/foto-${width}.png`});

        await photo.evaluate(image => image.src = '/foto-gagal.png');
        await fallback.waitFor({state: 'visible'});
        assert.ok(await photo.isHidden());
        const failedSize = await avatar.boundingBox();
        assert.equal(failedSize.width, loadedSize.width);
        assert.equal(failedSize.height, loadedSize.height);
        assert.equal(await fallback.textContent(), 'SI');

        failPhoto = true;
        await page.goto('http://localhost/audit/foto');
        await fallback.waitFor({state: 'visible'});
        assert.ok(await photo.isHidden());
        await avatar.scrollIntoViewIfNeeded();
        await page.screenshot({path: `${output}/foto-gagal-${width}.png`});

        await page.goto('http://localhost/audit/inisial');
        assert.equal(await photo.count(), 0);
        assert.ok(await fallback.isVisible());
        assert.equal(await fallback.textContent(), 'SI');
    }

    assert.deepEqual(errors, []);
    console.log(JSON.stringify({result: 'passed', checks: ['photo-rendered', 'nonblank-bitmap', 'desktop-mobile', 'initials-without-photo', 'failed-photo-fallback', 'stable-frame', 'no-overflow']}));
} finally {
    await browser.close();
}
