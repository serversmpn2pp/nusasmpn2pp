import assert from 'node:assert/strict';
import {readFile, mkdir, writeFile} from 'node:fs/promises';
import {resolve, extname, sep} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const fixtures = resolve('storage/framework/testing/pemeriksaan-sts');
const output = resolve('storage/logs/pemeriksaan-sts-qa');
const publicRoot = resolve('public');
const issues = [], errors = [], missing = [], checks = [], submissions = [];
const browser = await chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
await mkdir(output, {recursive: true});
try {
    const page = await browser.newPage({serviceWorkers: 'block', reducedMotion: 'reduce'});
    page.on('pageerror', e => errors.push(e.message));
    await page.route('**/*', async route => {
        const req = route.request(), url = new URL(req.url());
        if (!['localhost', '127.0.0.1'].includes(url.hostname)) return route.abort();
        if (req.method() === 'POST') {
            const params = new URLSearchParams(req.postData());
            assert.equal(params.get('_method'), 'PUT');
            const reviewed = [...new Set([...params.keys()].filter(key => /^siswa\[\d+\]\[diperiksa\]$/.test(key)))];
            assert.equal(reviewed.length, 32);
            for (const key of reviewed) assert.equal(params.getAll(key).at(-1), '1');
            const notes = [...params].filter(([key]) => key.endsWith('[catatan_koreksi]'));
            assert.equal(notes.length, 32);
            assert.ok(notes.every(([, value]) => value === ''), 'Refreshed automatic summaries do not require invented correction notes');
            submissions.push({rows: reviewed.length, fields: [...params].length});
            return route.fulfill({contentType: 'text/html', body: await readFile(resolve(fixtures, 'tersimpan.html'), 'utf8')});
        }
        if (url.pathname.startsWith('/sts-audit/')) return route.fulfill({contentType: 'text/html', body: await readFile(resolve(fixtures, url.pathname.split('/').pop() + '.html'), 'utf8')});
        if (url.pathname === '/notifikasi/ringkasan') return route.fulfill({json: {jumlah_belum_dibaca: 0, notifikasi: []}});
        const file = resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(publicRoot + sep)) return route.abort();
        try {
            return route.fulfill({body: await readFile(file), contentType: ({'.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.svg': 'image/svg+xml', '.woff2': 'font/woff2'})[extname(file)] || 'application/octet-stream'});
        } catch {
            missing.push(url.pathname);
            return route.fulfill({status: 404, body: ''});
        }
    });
    async function ready(state) {
        await page.goto('http://localhost/sts-audit/' + state);
        await page.waitForFunction(() => !!document.querySelector('[data-admin-sidebar-toggle] svg.lucide'));
        await page.evaluate(() => document.fonts.ready);
    }
    for (const [width, height] of [[390, 844], [1024, 768], [1440, 960]]) {
        await page.setViewportSize({width, height});
        for (const state of ['rekap-baru', 'tersimpan', 'gagal-simpan']) {
            await ready(state);
            if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) issues.push({width, state, issue: 'Page overflows horizontally'});
            if (state === 'gagal-simpan') {
                await page.waitForFunction(() => document.activeElement?.id === 'sts-feedback');
                assert.ok(await page.locator('#sts-feedback').evaluate(e => {
                    const box = e.getBoundingClientRect();
                    return box.top >= 0 && box.bottom <= innerHeight;
                }));
                assert.equal(await page.locator('[aria-invalid="true"]').count(), 1);
                assert.equal(await page.locator('#sts-sakit-1').inputValue(), '2');
                assert.match(await page.locator('#sts-error-catatan-1').textContent(), /Isi alasan koreksi/);
            } else {
                assert.equal(await page.locator('[data-sts-reviewed]').count(), 32);
                if (state === 'tersimpan') assert.match(await page.locator('#sts-feedback').textContent(), /Pemeriksaan kehadiran rapor disimpan/);
            }
            await page.screenshot({path: resolve(output, `${state}-${width}.png`)});
            checks.push({state, width});
        }
        for (const submitter of ['button[form="sts-kehadiran-form"]', '#sts-kehadiran-form .sts-band-footer button']) {
            await ready('rekap-baru');
            await page.locator('#sts-periksa-semua').check();
            assert.equal(await page.locator('[data-sts-reviewed]:checked').count(), 32);
            await page.locator(submitter).click();
            await page.waitForFunction(() => document.getElementById('sts-feedback')?.textContent.includes('Pemeriksaan kehadiran rapor disimpan'));
            assert.equal(await page.locator('[data-sts-reviewed]:checked').count(), 32);
        }
    }
    await writeFile(resolve(output, 'audit.json'), JSON.stringify({checks, submissions, issues, errors, missing}, null, 2));
    console.log(JSON.stringify({states: checks.length, submissions, issues, errors, missing, output}));
    assert.deepEqual(issues, []);
    assert.deepEqual(errors, []);
    assert.deepEqual(missing, []);
} finally {
    await browser.close();
}
