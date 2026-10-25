import assert from 'node:assert/strict';
import {readFile, mkdir, writeFile} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const fixtures = resolve('storage/logs/scanner-device');
const publicRoot = resolve('public');
const output = resolve(fixtures, 'browser');
await mkdir(output, {recursive: true});
const browser = await chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const errors = [], checks = [];
try {
    const context = await browser.newContext({serviceWorkers: 'block', reducedMotion: 'reduce'});
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    let kind = 'siswa', mode = 'success', metadata = {}, posts = [], navigations = 0;
    await page.route('**/*', async route => {
        const request = route.request(), url = new URL(request.url());
        if (!['localhost', '127.0.0.1'].includes(url.hostname)) return route.abort();
        if (['/scan-absensi', '/scan-absensi-pegawai'].includes(url.pathname)) {
            if (request.method() === 'POST') {
                posts.push({headers: request.headers(), body: request.postDataJSON()});
                assert.ok(request.headers()['x-scanner-csrf']);
                assert.equal(request.headers()['x-csrf-token'], undefined);
                if (mode === 'offline') return route.abort('internetdisconnected');
                if (mode === 'refresh' && posts.length === 1) return route.fulfill({status: 419, json: {berhasil: false, status: 'token_scanner_berubah'}});
                if (mode === 'revoked') return route.fulfill({status: 401, json: {berhasil: false, pesan: 'Akses browser scanner telah dicabut.'}});
                return route.fulfill({json: {
                    berhasil: true, status: 'berhasil_masuk', pesan: 'Presensi berhasil dicatat.', jenis_scan: 'masuk', waktu_server: '06:30:00',
                    [kind]: {nama_lengkap: 'NAMA LENGKAP PEMILIK KARTU PENGUJIAN', nisn: '0011223344', nip: '199001012015011001', inisial: 'NP', foto_url: '/images/logo-nusa.png'},
                    absensi: {jam_masuk: '06:30:00', status_masuk: 'tepat_waktu', menit_terlambat: 0},
                }});
            }
            if ((request.headers().accept || '').includes('application/json')) {
                if (mode === 'revoked') return route.fulfill({status: 401, json: {berhasil: false, pesan: 'Akses browser scanner telah dicabut.'}});
                return route.fulfill({json: {...metadata, ...(mode === 'refresh' ? {scannerCsrf: 'csrf-refreshed'} : {})}});
            }
            navigations++;
            return route.fulfill({contentType: 'text/html', body: await readFile(resolve(fixtures, `${kind}.html`), 'utf8')});
        }
        if (url.pathname === '/perangkat-scan') return route.fulfill({contentType: 'text/html', body: await readFile(resolve(fixtures, 'admin.html'), 'utf8')});
        if (url.pathname === '/notifikasi/ringkasan') return route.fulfill({json: {jumlah_belum_dibaca: 0, notifikasi: []}});
        const path = resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
        assert.ok(path.startsWith(publicRoot + sep));
        try {
            return route.fulfill({contentType: {'.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.woff2': 'font/woff2', '.webmanifest': 'application/manifest+json'}[extname(path)] || 'application/octet-stream', body: await readFile(path)});
        } catch { return route.fulfill({status: 404, body: ''}); }
    });

    async function openScanner() {
        mode = 'success'; posts = [];
        await page.goto(`http://localhost/${kind === 'siswa' ? 'scan-absensi' : 'scan-absensi-pegawai'}`);
        await page.evaluate(() => document.fonts.ready);
        metadata = await page.evaluate(kind => ({scannerCsrf: document.querySelector('meta[name="scanner-csrf"]').content, tanggal_server: tanggalJadwal, waktu_server: new Date(serverAwal).toISOString(), ...(kind === 'siswa' ? {jadwal} : {jadwalJson: jadwalHariIni})}), kind);
    }
    async function scan() { await page.locator('#scanInput').fill('0011223344'); await page.locator('#scanInput').press('Enter'); }
    async function inspect(label, width, height) {
        const result = await page.evaluate(() => {
            const bounds = ['#scanInput', '#resultCard', '#clockText'].map(selector => {
                const r = document.querySelector(selector).getBoundingClientRect();
                return {selector, left: r.left, right: r.right, bottom: r.bottom};
            });
            return {width: document.documentElement.scrollWidth, bounds};
        });
        assert.ok(result.width <= width + 1, `${label}: horizontal overflow`);
        assert.ok(result.bounds.every(b => b.left >= 0 && b.right <= width + 1), `${label}: clipped controls`);
        if (width >= 1024) assert.ok(result.bounds.every(b => b.bottom <= height + 1), `${label}: scan result does not fit monitor`);
        checks.push({label, width, height});
    }

    for (kind of ['siswa', 'pegawai']) {
        for (const [width, height] of [[1024, 768], [1280, 720], [1920, 1080], [390, 844], [320, 568]]) {
            await page.setViewportSize({width, height});
            await openScanner();
            await inspect(`${kind}-idle`, width, height);
            await scan();
            await page.waitForFunction(() => document.querySelector('#resultName').textContent.includes('PEMILIK KARTU'));
            await inspect(`${kind}-result`, width, height);
            assert.equal(posts.length, 1);
            if (width === 1024) await page.screenshot({path: resolve(output, `${kind}-1024x768.png`), fullPage: true});
        }
        await openScanner();
        mode = 'refresh'; await scan();
        await page.waitForFunction(() => document.querySelector('#resultName').textContent.includes('PEMILIK KARTU'));
        assert.equal(posts.length, 2);
        assert.equal(posts[1].headers['x-scanner-csrf'], 'csrf-refreshed');
        checks.push({label: `${kind}: recover expired authorization without login or manual reload`});

        await openScanner();
        mode = 'offline'; await scan();
        await page.waitForFunction(() => document.querySelector('#resultMessage').textContent.includes('Gagal menghubungi server'));
        assert.equal(posts.length, 1);
        checks.push({label: `${kind}: no ambiguous automatic replay after a network error`});

        await openScanner();
        mode = 'revoked';
        await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
        await page.waitForFunction(() => document.querySelector('#scanInput').disabled);
        assert.ok(await page.locator('.scanner-access-alert').isVisible());
        assert.match(await page.locator('.scanner-access-alert').textContent(), /dicabut/);
        await page.evaluate(() => { const input = document.querySelector('#scanInput'); input.value = '0011223344'; input.dispatchEvent(new KeyboardEvent('keydown', {key: 'Enter'})); });
        assert.equal(posts.length, 0);
        checks.push({label: `${kind}: revoked device blocks old open tabs`});
    }
    for (const [width, height] of [[1280, 900], [1024, 768], [390, 844]]) {
        await page.setViewportSize({width, height});
        await page.goto('http://localhost/perangkat-scan');
        await page.evaluate(() => document.fonts.ready);
        assert.ok(await page.locator('#aktivasiScanner').isVisible());
        assert.ok(await page.evaluate(width => document.documentElement.scrollWidth <= width + 1, width));
        await page.getByRole('button', {name: 'Cabut akses', exact: true}).first().click();
        await page.locator('dialog[open]').waitFor();
        assert.ok(await page.locator('dialog[open] textarea[name="alasan"]').isVisible());
        await page.screenshot({path: resolve(output, `admin-dialog-${width}.png`), fullPage: true});
        checks.push({label: 'admin activation and revocation dialog', width, height});
    }
    assert.deepEqual(errors, []);
    await writeFile(resolve(output, 'results.json'), JSON.stringify({checks, errors}, null, 2));
    process.stdout.write(JSON.stringify({result: 'passed', checks: checks.length, errors: errors.length}) + '\n');
} finally { await browser.close(); }
