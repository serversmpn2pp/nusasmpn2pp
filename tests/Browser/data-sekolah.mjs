import assert from 'node:assert/strict';
import {readFile, mkdir, writeFile} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const fixtures = resolve('storage/framework/testing/data-sekolah');
const output = resolve('storage/logs/data-sekolah-qa');
const publicRoot = resolve('public');
const populated = JSON.parse(await readFile(resolve(fixtures, 'manifest.json'), 'utf8'));
const empty = JSON.parse(await readFile(resolve(fixtures, 'manifest-empty.json'), 'utf8'));
const browser = await chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const errors = [], missing = [], writes = [], checks = [];
await mkdir(output, {recursive: true});
const key = value => { const url = new URL(value, 'http://localhost'); url.searchParams.sort(); return url.pathname + url.search; };
let manifest = {};
try {
  const context = await browser.newContext({serviceWorkers: 'block', reducedMotion: 'reduce'});
  const page = await context.newPage();
  page.on('pageerror', error => errors.push(error.message));
  await page.route('**/*', async route => {
    const request = route.request(), url = new URL(request.url());
    assert.ok(['localhost', '127.0.0.1'].includes(url.hostname));
    if (request.method() !== 'GET') { writes.push(url.pathname); return route.fulfill({status: 405, body: ''}); }
    if (manifest[key(url.href)]) return route.fulfill({contentType: 'text/html', body: await readFile(resolve(fixtures, manifest[key(url.href)] + '.html'), 'utf8')});
    if (url.pathname === '/notifikasi/ringkasan') return route.fulfill({json: {jumlah_belum_dibaca: 0, notifikasi: []}});
    const path = resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
    assert.ok(path.startsWith(publicRoot + sep));
    try { return route.fulfill({body: await readFile(path), contentType: ({'.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.webmanifest': 'application/manifest+json', '.woff2': 'font/woff2'})[extname(path)] || 'application/octet-stream'}); }
    catch { missing.push(url.pathname); return route.fulfill({status: 404, body: ''}); }
  });
  async function ready(path) {
    await page.goto('http://localhost' + path);
    await page.waitForFunction(() => !!document.querySelector('[data-admin-sidebar-toggle] svg.lucide'));
    await page.evaluate(async () => { await document.fonts.ready; await Promise.all([...document.images].map(img => img.complete ? Promise.resolve() : new Promise(r => { img.onload = r; img.onerror = r; }))); });
  }
  const validation = {'/_ui-errors/pegawai': 'validation-pegawai', '/_ui-errors/siswa': 'validation-siswa'};
  for (const [state, routes] of [['populated', populated], ['empty', empty], ['validation', validation]]) {
    manifest = Object.fromEntries(Object.entries(routes).map(([path, file]) => [key(path), file]));
    for (const [width, height] of [[320, 740], [390, 844], [768, 1024], [1024, 768], [1280, 900], [1440, 960], [1920, 1080]]) {
      await page.setViewportSize({width, height});
      for (const [path, file] of Object.entries(routes)) {
        await ready(path);
        const result = await page.evaluate(() => {
          const root = document.querySelector('.content-shell');
          const visible = e => !!e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden' && !e.closest('.id-card,.employee-id-card');
          const clipped = [...root.querySelectorAll('.person-name,.person-meta,.page-title,.panel-title,.button,.identity-person h3,.identity-person-meta,.quick-facts dd')]
            .filter(visible).filter(e => e.clientWidth > 0 && e.scrollWidth > e.clientWidth + 2)
            .map(e => ({text: e.textContent.trim(), width: e.clientWidth, scroll: e.scrollWidth}));
          const tables = [...root.querySelectorAll('.table-wrap,.available-list')].filter(visible).map(e => ({width: e.clientWidth, scroll: e.scrollWidth}));
          const profile = root.querySelector('.detail-shell > aside'), sections = root.querySelector('.detail-shell > .section-stack');
          const pb = profile?.getBoundingClientRect(), sb = sections?.getBoundingClientRect();
          return {rootWidth: document.documentElement.scrollWidth, rootHeight: document.documentElement.scrollHeight, clipped, tables,
            assets: document.querySelectorAll('link[href*="admin-data-sekolah.css"]').length,
            images: [...root.querySelectorAll('img[src]')].filter(e => e.getAttribute('src') && e.getClientRects().length).every(e => e.complete && e.naturalWidth > 0),
            profileAbove: !pb || !sb || (pb.bottom <= sb.top + 1 && Math.abs(pb.left - sb.left) < 1),
            cardSizes: [...root.querySelectorAll('[data-card-side]')].map(e => { const r = e.getBoundingClientRect(); return {width: r.width, height: r.height}; }),
            smallText: [...root.querySelectorAll('.person-name,.person-meta,.identity-person-meta,.stat-label,.identity-tab')].filter(visible).filter(e => parseFloat(getComputedStyle(e).fontSize) < 14).map(e => e.className),
          };
        });
        assert.equal(result.assets, 1);
        assert.ok(result.rootWidth <= width + 1, `Page overflow ${state} ${path} ${width}: ${result.rootWidth}`);
        if (width > 760) assert.ok(result.rootHeight <= height + 1, `Double scroll ${path} ${width}`);
        assert.deepEqual(result.clipped, [], `Clipped content ${path} ${width}`);
        assert.ok(result.tables.every(t => t.scroll <= t.width + 2), `Table requires horizontal scroll ${path} ${width}: ${JSON.stringify(result.tables)}`);
        assert.ok(result.profileAbove, `Identity takes table width ${path}`);
        assert.ok(result.images, `Missing image ${path}`);
        assert.deepEqual(result.smallText, [], `Small labels ${path}`);
        for (const card of result.cardSizes) {
          assert.ok(Math.abs(card.width - 53.98 * 96 / 25.4) < 1 && Math.abs(card.height - 85.6 * 96 / 25.4) < 1, 'Physical card dimensions changed');
        }
        if ([390, 1440].includes(width)) {
          const wanted = state !== 'populated' || /^(00|02|04|06|09|10|14|16|18|19|20|21|22|23)-/.test(file);
          if (wanted) await page.screenshot({path: resolve(output, `${state}-${file}-${width}.png`), fullPage: width <= 760});
        }
        if (state === 'populated' && path.startsWith('/penempatan-siswa')) {
          const boxes = page.locator('[data-placement-checkbox]');
          const count = await boxes.count();
          assert.ok(count > 1);
          await page.locator('[data-placement-select-all]').click();
          assert.equal(await page.locator('[data-placement-checkbox]:checked').count(), count);
          assert.equal(await page.locator('[data-placement-count]').textContent(), `${count} siswa dipilih`);
          await page.locator('[data-placement-clear]').click();
          assert.equal(await page.locator('[data-placement-checkbox]:checked').count(), 0);
          await boxes.first().check();
          assert.equal(await page.locator('[data-placement-count]').textContent(), '1 siswa dipilih');
          assert.ok(await boxes.first().evaluate(e => e.closest('.available-check').classList.contains('is-selected')));
        }
        if (state === 'validation') {
          assert.ok(await page.locator('#nama_lengkap').evaluate(e => e.getAttribute('aria-describedby') === 'nama_lengkap-error'));
          assert.ok(await page.locator('[role="alert"]').isVisible());
          assert.equal(await page.locator('#nama_lengkap').evaluate(e => getComputedStyle(e).borderColor), 'rgb(180, 35, 50)');
        }
        if (state === 'populated' && path.startsWith('/kartu-')) {
          assert.equal(await page.locator('[data-card-export-open]').count(), 1);
          await page.locator('[data-card-export-open]').click();
          await page.locator('[data-card-export-dialog]').waitFor({state: 'visible'});
          await page.keyboard.press('Escape');
          assert.ok(await page.locator('[data-card-export-dialog]').isHidden());
          await page.emulateMedia({media: 'print'});
          const print = await page.evaluate(() => ({hidden: getComputedStyle(document.querySelector('.school-card-name')).display === 'none', overflow: getComputedStyle(document.querySelector('.app-content')).overflowY,
            sizes: [...document.querySelectorAll('[data-card-side]')].map(e => { const r = e.getBoundingClientRect(); return {width: r.width, height: r.height}; })}));
          assert.ok(print.hidden);
          assert.equal(print.overflow, 'visible', 'Printed cards must not be clipped by the admin scroll container');
          for (const card of print.sizes) assert.ok(Math.abs(card.width - 53.98 * 96 / 25.4) < 1 && Math.abs(card.height - 85.6 * 96 / 25.4) < 1);
          await page.emulateMedia({media: 'screen'});
        }
        checks.push({state, path, width, height, passed: true});
      }
    }
  }
  assert.deepEqual(errors, []);
  assert.deepEqual(missing, []);
  assert.deepEqual(writes, []);
  await writeFile(resolve(output, 'audit.json'), JSON.stringify({checks, errors, missing, writes}, null, 2));
  console.log(JSON.stringify({result: 'passed', pages: 39, states: checks.length, errors, missing, writes, output}));
} finally { await browser.close(); }
