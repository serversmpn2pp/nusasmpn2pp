// CBT_PHONE_FIXTURE=1 php artisan test --filter=test_paket_simulasi
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { extname, resolve, sep } from 'node:path';
import { pathToFileURL } from 'node:url';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE
    ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const html = await readFile('storage/logs/cbt-phone-audit.html', 'utf8');
const browser = await chromium.launch({ headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || undefined });

try {
    const context = await browser.newContext({ viewport:{ width:390, height:844 }, isMobile:true, hasTouch:true });
    const events = [];
    const errors = [];
    let connected = true;
    await context.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (route.request().method() === 'POST' && url.pathname === '/cbt/ujian/aktivitas-keamanan') {
            if (!connected) return route.abort('internetdisconnected').catch(() => {});
            const payload = route.request().postDataJSON();
            events.push(payload);
            return route.fulfill({ json:{ data:{
                mode:'pengerjaan', kejadian_dihitung:false,
                keamanan:{ jumlah_kejadian:0, batas_kejadian:3, sisa_kejadian:3, ditahan:false },
            } } }).catch(() => {});
        }
        if (route.request().method() === 'POST') {
            if (!connected) return route.abort('internetdisconnected').catch(() => {});
            return route.fulfill({ json:{ terjawab:true, ragu:false, tersimpan_pada:'10:00:00' } }).catch(() => {});
        }
        if (url.pathname === '/audit') return route.fulfill({ contentType:'text/html', body:html });
        const root = resolve('public');
        const file = resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(root + sep)) return route.abort();
        try {
            return route.fulfill({
                body:await readFile(file),
                contentType:({ '.js':'text/javascript', '.css':'text/css', '.png':'image/png', '.jpg':'image/jpeg', '.woff2':'font/woff2' })[extname(file).toLowerCase()] || 'application/octet-stream',
            });
        } catch { return route.fulfill({ status:404, body:'' }); }
    });

    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    page.on('dialog', dialog => dialog.accept());
    await page.goto('http://localhost/audit');
    await page.waitForTimeout(1200);
    assert.ok(events.some(event => event.peristiwa === 'heartbeat'), 'Heartbeat awal tidak terkirim');

    const beforeFocusGuard = events.length;
    await page.evaluate(() => {
        Object.defineProperty(document, 'hasFocus', { configurable:true, value:() => false });
    });
    await page.waitForTimeout(2200);
    const focusGuardAwayEvents = events.slice(beforeFocusGuard).filter(event => event.peristiwa === 'keluar');
    assert.equal(focusGuardAwayEvents.length, 1, 'Focus guard harus mencatat tepat satu kejadian keluar selama fokus hilang');
    assert.equal(focusGuardAwayEvents[0].metadata.pemicu, 'focus-guard', 'Kejadian harus ditandai berasal dari focus guard');

    const beforeFocusReturn = events.length;
    await page.evaluate(() => {
        Object.defineProperty(document, 'hasFocus', { configurable:true, value:() => true });
    });
    await page.waitForTimeout(1200);
    const focusGuardReturnEvents = events.slice(beforeFocusReturn).filter(event => event.peristiwa === 'kembali');
    assert.equal(focusGuardReturnEvents.length, 1, 'Focus guard harus mencatat tepat satu kejadian kembali');
    assert.equal(focusGuardReturnEvents[0].metadata.pemicu, 'focus-guard', 'Kejadian kembali harus ditandai berasal dari focus guard');

    const setVisibility = hidden => page.evaluate(value => {
        Object.defineProperty(document, 'hidden', { configurable:true, get:() => value });
        Object.defineProperty(document, 'visibilityState', { configurable:true, get:() => value ? 'hidden' : 'visible' });
        document.dispatchEvent(new Event('visibilitychange'));
    }, hidden);

    const beforeSwitch = events.length;
    await setVisibility(true);
    await page.waitForTimeout(400);
    await setVisibility(false);
    await page.waitForTimeout(400);
    assert.ok(events.slice(beforeSwitch).some(event => event.peristiwa === 'keluar'), 'Pindah tab tidak tercatat');
    assert.ok(events.slice(beforeSwitch).some(event => event.peristiwa === 'kembali'), 'Kembali ke tab tidak tercatat');
    assert.equal(await page.locator('#securityWarningDialog').evaluate(dialog => dialog.open), false);

    const beforeOffline = events.length;
    connected = false;
    await context.setOffline(true);
    await setVisibility(true);
    await page.waitForTimeout(200);
    await setVisibility(false);
    await page.waitForTimeout(200);
    assert.equal(events.length, beforeOffline, 'Kejadian tidak boleh dianggap terkirim saat jaringan putus');
    connected = true;
    await context.setOffline(false);
    await page.evaluate(() => window.dispatchEvent(new Event('online')));
    await page.waitForTimeout(500);
    assert.ok(events.slice(beforeOffline).some(event => event.peristiwa === 'heartbeat'), 'Heartbeat tidak pulih sesudah online');
    assert.equal(await page.locator('#formUjian').evaluate(form => form.inert), false);

    const beforeReload = events.length;
    await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pagehide')));
    await page.reload();
    await page.waitForTimeout(1300);
    assert.ok(events.slice(beforeReload).some(event => event.peristiwa === 'keluar'), 'Pemicu pagehide tidak mengirim catatan keluar');
    assert.ok(events.slice(beforeReload).some(event => event.peristiwa === 'kembali'), 'Muat ulang tidak menutup catatan keluar');
    assert.deepEqual(errors, []);
    console.log('PASS: focus guard, visibilitychange/pagehide, jaringan putus/pulih, muat ulang, heartbeat, tanpa duplikasi atau JS error.');
} finally {
    await browser.close();
}
