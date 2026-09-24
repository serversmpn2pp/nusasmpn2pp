// CBT_SUPERVISOR_FIXTURE=1 php artisan test --filter=UjianTerpusatPelaksanaanNilaiTest
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve, extname, sep } from 'node:path';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await chromium.launch({
    headless:true,
    channel:process.env.PLAYWRIGHT_CHANNEL || undefined,
    args:['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream'],
});
try {
    const page = await browser.newPage();
    await page.context().grantPermissions(['camera'], { origin:'http://localhost' });
    const errors = [];
    let offline = false;
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/tugas-pengawas-ujian') {
            return route.fulfill({ contentType:'text/html', body:await readFile('storage/logs/cbt-supervisor-index.html', 'utf8') });
        }
        if (url.pathname.endsWith('/riwayat-mode-aman')) {
            return route.fulfill({ contentType:'text/html', body:await readFile('storage/logs/cbt-supervisor-mode-aman-history.html', 'utf8') });
        }
        if (url.pathname.startsWith('/tugas-pengawas-ujian/')) {
            if (offline) return route.abort('internetdisconnected');
            const phase = url.searchParams.get('tahap') || 'persiapan';
            const held = phase === 'pantau' && url.searchParams.get('mode') === 'held' ? '-held' : '';
            return route.fulfill({ contentType:'text/html', body:await readFile(`storage/logs/cbt-supervisor-${phase}${held}.html`, 'utf8') });
        }
        if (url.pathname.startsWith('/presensi-ujian-cbt/')) {
            if (route.request().method() !== 'GET') {
                const participant = {
                    id:1,
                    nama_lengkap:'Alya',
                    nisn:'0130000000',
                    kelas:'VII.A',
                    nomor_meja:'1',
                    foto_url:'/images/kartu-pelajar/default-user.png',
                    status:'hadir',
                    waktu_scan:'21:00:00',
                };
                return route.fulfill({ json:{
                    berhasil:true,
                    baru:true,
                    pesan:'Presensi Alya berhasil dicatat.',
                    siswa:participant,
                    peserta:participant,
                    ringkasan:{ peserta:2, hadir:1, belum_absen:1 },
                    waktu_server:'21:00:00',
                } });
            }
            return route.fulfill({ contentType:'text/html', body:await readFile('storage/logs/cbt-supervisor-attendance.html', 'utf8') });
        }
        const root = resolve('public');
        const file = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(root + sep)) return route.abort();
        try {
            return route.fulfill({ body:await readFile(file), contentType:({ '.js':'text/javascript', '.css':'text/css', '.jpg':'image/jpeg', '.jpeg':'image/jpeg', '.png':'image/png', '.webp':'image/webp', '.woff2':'font/woff2' })[extname(file).toLowerCase()] || 'application/octet-stream' });
        } catch { return route.fulfill({ status:404, body:'' }); }
    });
    for (const [width, height] of [[320, 568], [360, 640], [390, 844], [768, 768], [1024, 768], [1366, 768]]) {
        await page.setViewportSize({ width, height });
        for (const phase of ['persiapan', 'pantau', 'bukti']) {
            await page.goto(`http://localhost/tugas-pengawas-ujian/1?tahap=${phase}`);
            assert.equal(await page.locator('.supervisor-tabs a[aria-current=page]').count(), 1);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Overflow ${width}x${height} ${phase}`);
            const bounds = await page.locator('.supervisor-tabs').boundingBox();
            assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width + 1);
            const clippedTabs = await page.locator('.supervisor-tabs a').evaluateAll(links => links
                .filter(link => link.scrollWidth > link.clientWidth + 1 || link.scrollHeight > link.clientHeight + 1)
                .map(link => link.textContent.trim()));
            assert.deepEqual(clippedTabs, [], `Tab terpotong pada ${width}x${height}: ${clippedTabs.join(', ')}`);
            if (phase === 'pantau' && width <= 560) {
                assert.equal(await page.locator('.supervisor-participants thead').evaluate(head => getComputedStyle(head).display), 'none');
                assert.equal(await page.locator('[data-supervisor-student]').first().evaluate(row => getComputedStyle(row).display), 'block');
                assert.equal(await page.locator('[data-supervisor-student]').first().locator('td[data-label]').count(), 5);
                assert.ok(await page.locator('.supervisor-participants').evaluate(node => node.scrollWidth <= node.clientWidth + 1), `Kartu peserta melebar pada ${width}x${height}`);
            }
            if (width === 320) await page.screenshot({ path:`storage/logs/supervisor-mobile-compact-${phase}.png`, fullPage:true });
            if (width === 390) await page.screenshot({ path:`storage/logs/supervisor-mobile-${phase}.png`, fullPage:true });
            if (width === 1366) await page.screenshot({ path:`storage/logs/supervisor-${phase}.png`, fullPage:true });
        }
    }
    for (const [width, height] of [[320, 568], [390, 844], [768, 768], [1366, 768]]) {
        await page.setViewportSize({ width, height });
        await page.goto('http://localhost/tugas-pengawas-ujian');
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Daftar tugas overflow ${width}x${height}`);
        assert.ok(await page.locator('.supervisor-task').first().isVisible());
        assert.ok(await page.locator('.supervisor-task-action .button').first().isVisible());
        if (width === 390) await page.screenshot({ path:'storage/logs/supervisor-mobile-index.png', fullPage:true });
        await page.goto('http://localhost/presensi-ujian-cbt/1/1');
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Presensi overflow ${width}x${height}`);
        assert.ok(await page.locator('.camera-wrap').isVisible());
        assert.ok(await page.locator('.manual-form').isVisible());
        assert.ok(await page.locator('.participant-panel').isVisible());
        if (width === 390) await page.screenshot({ path:'storage/logs/supervisor-mobile-attendance.png', fullPage:true });
        if (width === 1366) await page.screenshot({ path:'storage/logs/supervisor-attendance.png', fullPage:true });
    }
    await page.setViewportSize({ width:390, height:844 });
    await page.goto('http://localhost/tugas-pengawas-ujian/1?tahap=pantau&mode=held');
    assert.equal(await page.locator('[data-supervisor-student][data-status=terblokir]').count(), 1);
    assert.equal(await page.locator('a:has-text("Tinjau & buka")').count(), 1);
    await page.locator('#supervisor-status').selectOption('terblokir');
    assert.equal(await page.locator('[data-supervisor-student]:visible').count(), 1);
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    await page.screenshot({ path:'storage/logs/supervisor-mobile-mode-aman.png', fullPage:true });
    for (const [width, height] of [[320, 568], [390, 844], [768, 768], [1366, 768]]) {
        await page.setViewportSize({ width, height });
        await page.goto('http://localhost/tugas-pengawas-ujian/1/peserta/1/riwayat-mode-aman');
        assert.ok(await page.locator('.security-history').isVisible());
        assert.equal(await page.locator('.security-entry').count(), 2);
        assert.ok(await page.locator('#alasan_pembukaan').isVisible());
        assert.equal(await page.locator('#alasan_pembukaan').evaluate(input => input.checkValidity()), false);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Riwayat Mode Aman overflow ${width}x${height}`);
        if (width === 390) await page.screenshot({ path:'storage/logs/supervisor-mobile-history.png', fullPage:true });
        if (width === 1366) await page.screenshot({ path:'storage/logs/supervisor-history.png', fullPage:true });
    }
    await page.goto('http://localhost/presensi-ujian-cbt/1/1');
    await page.locator('#start-camera').click();
    await page.locator('#camera-wrap.camera-on').waitFor({ state:'visible' });
    assert.equal(await page.locator('#stop-camera').isEnabled(), true);
    assert.match(await page.locator('#camera-status-text').textContent(), /Kamera aktif/);
    await page.locator('#stop-camera').click();
    assert.equal(await page.locator('#camera-wrap').evaluate(node => node.classList.contains('camera-on')), false);
    await page.locator('#manual-nisn').fill('0130000000');
    await page.locator('#manual-form button').click();
    await page.locator('#scan-result.show').waitFor({ state:'visible' });
    assert.equal((await page.locator('#result-name').textContent()).trim(), 'Alya');
    assert.equal((await page.locator('#summary-present').textContent()).trim(), '1');
    await page.locator('#participant-search').fill('Bima');
    assert.equal(await page.locator('.participant-manual-form:visible').count(), 1);
    await page.locator('#participant-search').fill('');
    await page.goto('http://localhost/tugas-pengawas-ujian/1?tahap=pantau');
    await page.locator('#supervisor-status').selectOption('selesai');
    assert.ok(await page.locator('#supervisor-empty').isVisible());
    await page.locator('#supervisor-status').selectOption('');
    assert.equal(await page.locator('[data-supervisor-student]:visible').count(), 2);
    await page.screenshot({ path:'storage/logs/supervisor-monitor-final.png', fullPage:true });
    await page.locator('#supervisor-search').fill('nama tidak ada');
    await page.locator('#supervisor-refresh').click();
    await page.waitForFunction(() => !document.getElementById('supervisor-refresh').disabled);
    assert.equal(await page.locator('#supervisor-search').inputValue(), 'nama tidak ada');
    assert.ok(await page.locator('#supervisor-empty').isVisible());
    offline = true;
    await page.locator('#supervisor-refresh').click();
    await page.waitForFunction(() => !document.getElementById('supervisor-refresh').disabled);
    assert.match(await page.locator('#supervisor-live').textContent(), /Data belum diperbarui/);
    assert.ok(await page.locator('#supervisor-monitor').isVisible());
    offline = false;
    await page.locator('.supervisor-tabs a').nth(2).click();
    assert.ok(await page.locator('[data-proof-input]').first().isVisible());
    await page.locator('.supervisor-tabs a').first().click();
    assert.ok(await page.locator('#supervisor-token').isVisible());
    assert.deepEqual(errors, []);
    console.log('PASS: 18 viewport/phase checks plus task list and attendance at four viewports; unclipped tabs, navigation, search retained after refresh, offline recovery message, no JS errors.');
} finally { await browser.close(); }
