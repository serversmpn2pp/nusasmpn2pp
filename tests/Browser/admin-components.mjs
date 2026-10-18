import assert from 'node:assert/strict';
import {readFile, mkdir, writeFile} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const fixtures = resolve('storage/framework/testing/admin-components');
const output = resolve('storage/logs/admin-components-qa');
const publicRoot = resolve('public');
const browser = await chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const errors = [], missing = [], writes = [], checks = [];
await mkdir(output, {recursive: true});

function contrast(a, b) {
  const luminance = rgb => rgb.match(/[\d.]+/g).slice(0, 3).map(Number).map(x => x / 255)
    .map(x => x <= .04045 ? x / 12.92 : ((x + .055) / 1.055) ** 2.4)
    .reduce((sum, x, i) => sum + x * [.2126, .7152, .0722][i], 0);
  const x = luminance(a), y = luminance(b);
  return (Math.max(x, y) + .05) / (Math.min(x, y) + .05);
}

try {
  const context = await browser.newContext({serviceWorkers: 'block', reducedMotion: 'reduce'});
  const page = await context.newPage();
  page.on('pageerror', error => errors.push(error.message));
  await page.route('**/*', async route => {
    const request = route.request(), url = new URL(request.url());
    assert.ok(['localhost', '127.0.0.1'].includes(url.hostname));
    if (request.method() !== 'GET') { writes.push(url.pathname); return route.fulfill({status: 405, body: ''}); }
    if (['/components', '/validation', '/success'].includes(url.pathname)) {
      return route.fulfill({contentType: 'text/html', body: await readFile(resolve(fixtures, url.pathname.slice(1) + '.html'), 'utf8')});
    }
    if (url.pathname === '/notifikasi/ringkasan') return route.fulfill({json: {jumlah_belum_dibaca: 0, notifikasi: []}});
    const path = resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
    assert.ok(path.startsWith(publicRoot + sep));
    try {
      return route.fulfill({body: await readFile(path), contentType: ({'.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.webmanifest': 'application/manifest+json'})[extname(path)] || 'application/octet-stream'});
    } catch { missing.push(url.pathname); return route.fulfill({status: 404, body: ''}); }
  });

  for (const [width, height] of [[320, 740], [360, 740], [390, 844], [768, 1024], [1024, 768], [1280, 900], [1440, 960], [1920, 1080], [844, 390]]) {
    await page.setViewportSize({width, height});
    for (const fixture of ['components', 'validation', 'success']) {
      await page.goto('http://localhost/' + fixture);
      await page.waitForFunction(() => !!document.querySelector('[data-admin-sidebar-toggle] svg.lucide'));
      const result = await page.evaluate(() => {
        const root = document.querySelector('.content-shell');
        const visible = e => !!e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden';
        const nodes = [...root.querySelectorAll('.button,.input,.select,.textarea,.badge,.surat-status,.alert')].filter(visible);
        const overflow = nodes.filter(e => e.clientWidth > 0 && e.scrollWidth > e.clientWidth + 2 && e.tagName !== 'SELECT' && !['INPUT', 'TEXTAREA'].includes(e.tagName))
          .map(e => ({tag: e.tagName, text: e.textContent.trim(), width: e.clientWidth, scroll: e.scrollWidth}));
        return {
          rootWidth: document.documentElement.scrollWidth, rootHeight: document.documentElement.scrollHeight, overflow,
          controls: [...root.querySelectorAll('.input,.select,.textarea')].filter(visible).map(e => ({height: e.getBoundingClientRect().height, font: parseFloat(getComputedStyle(e).fontSize)})),
          contrasts: [...root.querySelectorAll('.badge,.surat-status,.alert,.button:not(:disabled):not([aria-disabled="true"])')].filter(visible).map(e => {
            const css = getComputedStyle(e);
            return {text: e.textContent.trim(), color: css.color, background: css.backgroundColor};
          }),
          componentAssets: document.querySelectorAll('link[href*="admin-components.css"]').length,
          focusOutlines: [...root.querySelectorAll('.button')].filter(visible).every(e => parseFloat(getComputedStyle(e).borderRadius) <= 8),
        };
      });
      assert.ok(result.rootWidth <= width + 1, `Horizontal page overflow ${fixture} ${width}`);
      if (width > 760) assert.ok(result.rootHeight <= height + 1, `Double scroll ${fixture} ${width}`);
      assert.deepEqual(result.overflow, [], `Clipped labels ${fixture} ${width}`);
      assert.equal(result.componentAssets, 1);
      assert.ok(result.focusOutlines);
      assert.ok(result.controls.every(e => e.height >= 43 && e.font >= 16), `Input size ${fixture} ${width}`);
      for (const item of result.contrasts) {
        assert.ok(contrast(item.color, item.background) >= 4.5, `Low contrast ${item.text}: ${item.color} / ${item.background}`);
      }
      checks.push({fixture, width, height, passed: true});

      if (fixture === 'components') {
        const input = page.locator('#normal');
        await input.focus();
        assert.equal(await input.evaluate(e => getComputedStyle(e).borderColor), 'rgb(15, 118, 110)');
        assert.notEqual(await input.evaluate(e => getComputedStyle(e).boxShadow), 'none');
        await input.fill('Dokumen pemeriksaan');
        assert.equal(await input.inputValue(), 'Dokumen pemeriksaan');
        assert.ok(await page.locator('#readonly').evaluate(e => e.readOnly));
        assert.ok(await page.locator('#disabled').isDisabled());
        assert.equal(await page.locator('#invalid').getAttribute('aria-describedby'), 'invalid-error');
        assert.equal(await page.locator('#invalid').evaluate(e => getComputedStyle(e).borderColor), 'rgb(180, 35, 50)');
        await page.locator('.status-toggle input').uncheck();
        assert.equal(await page.locator('.status-toggle').evaluate(e => getComputedStyle(e).backgroundColor), 'rgb(245, 249, 252)');
        await page.locator('.status-toggle input').check();
        assert.equal(await page.locator('.status-toggle').evaluate(e => getComputedStyle(e).backgroundColor), 'rgb(232, 245, 241)');
        const trigger = page.locator('.page-header [data-ui-dialog-open]');
        await trigger.click();
        const dialog = page.locator('#contoh-dialog');
        assert.ok(await dialog.isVisible());
        assert.ok(await dialog.evaluate(e => e.matches(':modal')));
        assert.ok(await page.locator('.ui-dialog-close').evaluate(e => e === document.activeElement));
        const rect = await dialog.boundingBox();
        assert.ok(rect.x >= 0 && rect.x + rect.width <= width + 1 && rect.y >= 0 && rect.y + rect.height <= height + 1, `Dialog clipped ${width}`);
        for (let i = 0; i < 7; i++) {
          await page.keyboard.press('Tab');
          assert.ok(await dialog.evaluate(e => e.contains(document.activeElement)), 'Focus escaped modal');
        }
        await page.locator('.ui-dialog-close').focus();
        await page.keyboard.press('Shift+Tab');
        assert.ok(await page.locator('.ui-dialog-footer button').last().evaluate(e => e === document.activeElement));
        await page.mouse.click(2, 2);
        assert.ok(await dialog.isVisible(), 'Backdrop must not discard dialog input');
        if ([390, 1440].includes(width)) await page.screenshot({path: resolve(output, `dialog-${width}.png`)});
        await page.keyboard.press('Escape');
        assert.ok(await dialog.isHidden());
        assert.ok(await trigger.evaluate(e => e === document.activeElement));
        const tableTrigger = page.locator('.ui-table [data-ui-dialog-open]');
        await tableTrigger.click();
        assert.ok(await dialog.isVisible());
        await page.locator('.ui-dialog-close').click();
        assert.ok(await tableTrigger.evaluate(e => e === document.activeElement));
        await trigger.click();
        await page.locator('.ui-dialog-footer [data-ui-dialog-close]').first().click();
        assert.ok(await dialog.isHidden());
        assert.ok(await trigger.evaluate(e => e === document.activeElement));
        await page.locator('button[aria-label="Unduh dokumen"]').scrollIntoViewIfNeeded();
        await page.locator('button[aria-label="Unduh dokumen"]').hover();
        await page.waitForFunction(() => !document.getElementById('admin-tooltip').hidden);
        assert.equal(await page.locator('#admin-tooltip').textContent(), 'Unduh dokumen');
      } else if (fixture === 'validation') {
        assert.ok(await page.locator('#nama').evaluate(e => e.getAttribute('aria-invalid') === 'true'));
        assert.ok(await page.locator('[role="alert"]').isVisible());
        assert.equal(await page.locator('#keterangan').inputValue(), 'Catatan yang tetap tersimpan pada formulir');
      } else {
        assert.ok(await page.locator('.alert-success[role="status"]').isVisible());
        assert.equal(await page.locator('input[name="kata_kunci"]').getAttribute('type'), 'search');
      }
      if ([390, 1440].includes(width)) {
        await page.evaluate(() => { document.querySelector('.app-content').scrollTop = 0; window.scrollTo(0, 0); document.activeElement?.blur(); });
        await page.screenshot({path: resolve(output, `${fixture}-${width}.png`), fullPage: width <= 760});
      }
    }
  }
  assert.deepEqual(errors, []);
  assert.deepEqual(missing, []);
  assert.deepEqual(writes, []);
  await writeFile(resolve(output, 'audit.json'), JSON.stringify({checks, errors, missing, writes}, null, 2));
  console.log(JSON.stringify({result: 'passed', states: checks.length, dialogs: 27, output}));
} finally { await browser.close(); }
