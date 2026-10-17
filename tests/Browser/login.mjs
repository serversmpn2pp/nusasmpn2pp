import assert from 'node:assert/strict';
import {readFile, mkdir, writeFile} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/login-qa');
const fixtures = resolve('storage/framework/testing/login');
const publicRoot = resolve('public');
await mkdir(output, {recursive: true});
const errors = [], missing = [], posts = [];
const checks = [];

async function prepare(page) {
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const request = route.request(), url = new URL(request.url());
        const fixture = url.pathname.match(/^\/_login-qa\/([a-z-]+)$/)?.[1];
        if (request.method() === 'POST') {
            assert.equal(url.pathname, '/login');
            posts.push(new URLSearchParams(request.postData()));
            return route.fulfill({contentType: 'text/html', body: await readFile(resolve(fixtures, 'username-error.html'), 'utf8')});
        }
        if (fixture) return route.fulfill({contentType: 'text/html', body: await readFile(resolve(fixtures, `${fixture}.html`), 'utf8')});
        assert.ok(['localhost', '127.0.0.1'].includes(url.hostname), `Unexpected remote asset ${url.hostname}`);
        const path = resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
        assert.ok(path.startsWith(publicRoot + sep), 'Asset escaped public directory');
        try {
            return route.fulfill({body: await readFile(path), contentType: ({'.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.webmanifest': 'application/manifest+json'})[extname(path)] || 'application/octet-stream'});
        } catch {
            missing.push(url.pathname);
            return route.fulfill({status: 404, body: ''});
        }
    });
}

async function assertLayout(page, width) {
    await page.evaluate(() => Promise.all([...document.images].map(image => image.decode())));
    const translation = await page.evaluate(() => ({
        html: document.documentElement.translate,
        body: document.body.translate,
        google: document.querySelector('meta[name="google"]')?.content,
        inherited: [...document.querySelectorAll('h1, label, input, button, a, .tagline')].every(element => element.translate === false),
    }));
    assert.deepEqual(translation, {html: false, body: false, google: 'notranslate', inherited: true}, 'Login translation opt-out');
    const layout = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        images: [...document.images].every(image => image.naturalWidth > 0),
        clipped: [...document.querySelectorAll('.brand, .login-content, .support p, .tagline, .legal, .page-footer, .error-text, .login-alert')]
            .filter(element => element.scrollWidth > element.clientWidth + 1).map(element => element.className),
        controls: [...document.querySelectorAll('button, .remember, .legal a')].filter(element => element.getClientRects().length)
            .map(element => ({name: element.getAttribute('aria-label') || element.textContent.trim(), height: element.getBoundingClientRect().height})),
    }));
    assert.ok(layout.scrollWidth <= width + 1, `Horizontal overflow at ${width}`);
    assert.ok(layout.images, 'Missing logo or school image');
    assert.deepEqual(layout.clipped, [], `Clipped text at ${width}`);
    assert.ok(layout.controls.every(control => control.height >= 44), `Small touch target at ${width}`);
    for (const id of ['username', 'password']) {
        assert.equal(await page.locator(`#${id}`).evaluate(element => getComputedStyle(element).borderTopWidth), '2px', 'Input boundaries must be prominent');
    }
    assert.equal(await page.locator('#username').getAttribute('placeholder'), 'NIP/NISN/ORT-NISN');
    const form = await page.locator('[data-login-form]').boundingBox();
    const heading = await page.locator('.welcome').boundingBox();
    const support = await page.locator('.support p').boundingBox();
    const footer = await page.locator('.page-footer').boundingBox();
    const legal = await page.locator('.legal').boundingBox();
    assert.ok(heading.y + heading.height <= form.y + 1, 'Heading overlaps form');
    assert.ok(form.y + form.height <= support.y + 1, 'Form overlaps support');
    assert.ok(legal.y + legal.height <= footer.y + 1, 'Footer overlaps links');
    if (await page.locator('[data-pwa-install]').isVisible()) {
        const install = await page.locator('[data-pwa-install]').boundingBox();
        assert.ok(legal.y + legal.height <= install.y + 1, 'Install action must follow legal links');
        assert.ok(install.y + install.height <= footer.y + 1, 'Install action overlaps footer');
        assert.ok(Math.abs(install.x + install.width / 2 - width / 2) <= 1, 'Install action must be centered');
        assert.ok(install.width < 180, 'Install action must remain compact');
        assert.deepEqual(await page.locator('[data-pwa-install]').evaluate(button => {
            const css = getComputedStyle(button);
            return {border: css.borderTopWidth, background: css.backgroundColor, shadow: css.boxShadow};
        }), {border: '0px', background: 'rgba(0, 0, 0, 0)', shadow: 'none'});
    }
    assert.ok(Math.abs(support.x + support.width / 2 - width / 2) <= 1, 'Help must be centered');
    assert.equal(await page.locator('.support p').evaluate(element => getComputedStyle(element).textAlign), 'center');
    assert.equal(await page.locator('.tagline').evaluate(element => getComputedStyle(element).fontSize), width > 720 ? '24px' : '12px');
    assert.notEqual(await page.locator('.tagline span').evaluate(element => getComputedStyle(element).boxShadow), 'none');
}

try {
    const context = await browser.newContext({serviceWorkers: 'block'});
    const page = await context.newPage();
    await prepare(page);
    for (const [width, height] of [[320, 640], [360, 640], [390, 844], [768, 1024], [1024, 768], [1440, 960], [1920, 1080], [1366, 650], [844, 390]]) {
        await page.setViewportSize({width, height});
        for (const state of ['normal', 'username-error', 'password-error', 'session-error']) {
            await page.goto(`http://localhost/_login-qa/${state}`);
            await page.getByRole('heading', {name: 'Masuk ke NUSA'}).click();
            await assertLayout(page, width);
            assert.equal(await page.locator('[data-pwa-install-container]').isVisible(), false);
            if ([390, 1440].includes(width)) await page.screenshot({path: `${output}/${state}-${width}.png`, fullPage: true});
            checks.push({width, height, state});
        }
    }

    await page.setViewportSize({width: 390, height: 844});
    await page.goto('http://localhost/_login-qa/normal');
    await page.locator('#username').focus();
    const inputSize = await page.locator('#username').evaluate(input => ({width: input.offsetWidth, height: input.offsetHeight}));
    await page.locator('#username').blur();
    assert.deepEqual(await page.locator('#username').evaluate(input => ({width: input.offsetWidth, height: input.offsetHeight})), inputSize, 'Focus must not resize inputs');
    await page.locator('#username').focus();
    for (const id of ['password', 'toggle', 'ingat', 'submit']) {
        await page.keyboard.press('Tab');
        const focused = await page.evaluate(() => document.activeElement.matches('[data-password-toggle]') ? 'toggle' : document.activeElement.matches('[data-login-submit]') ? 'submit' : document.activeElement.id);
        assert.equal(focused, id, 'Keyboard tab order');
    }
    await page.locator('#password').fill('SandiUjiBrowser123');
    await page.getByRole('button', {name: 'Tampilkan kata sandi', exact: true}).click();
    assert.equal(await page.locator('#password').getAttribute('type'), 'text');
    assert.equal(await page.locator('[data-password-toggle]').getAttribute('aria-pressed'), 'true');
    await page.getByRole('button', {name: 'Sembunyikan kata sandi', exact: true}).click();
    assert.equal(await page.locator('#password').getAttribute('type'), 'password');
    await page.locator('#password').evaluate(input => {
        const event = new KeyboardEvent('keydown', {bubbles: true});
        Object.defineProperty(event, 'getModifierState', {value: name => name === 'CapsLock'});
        input.dispatchEvent(event);
    });
    assert.ok(await page.locator('[data-caps-lock-warning]').isVisible());
    await page.locator('#username').focus();
    assert.equal(await page.locator('[data-caps-lock-warning]').isVisible(), false);

    await page.locator('#username').fill('198001012010011001');
    await page.getByLabel('Ingat saya').check();
    const submitting = await page.evaluate(() => {
        const form = document.querySelector('[data-login-form]');
        const attempts = [];
        window.loginQaBlock = event => { attempts.push(event.defaultPrevented); event.preventDefault(); };
        form.addEventListener('submit', window.loginQaBlock);
        form.requestSubmit();
        form.requestSubmit();
        return {disabled: document.querySelector('[data-login-submit]').disabled, busy: form.getAttribute('aria-busy'), attempts};
    });
    assert.deepEqual(submitting, {disabled: true, busy: 'true', attempts: [false, true]});
    await page.screenshot({path: `${output}/loading-390.png`, fullPage: true});
    await page.evaluate(() => {
        document.querySelector('[data-login-form]').removeEventListener('submit', window.loginQaBlock);
        delete window.loginQaBlock;
        window.dispatchEvent(new PageTransitionEvent('pageshow', {persisted: true}));
    });
    const request = page.waitForRequest(request => request.method() === 'POST');
    await page.locator('#password').press('Enter');
    await request;
    await page.waitForURL('**/login');
    await page.locator('#username_error').waitFor();
    assert.equal(posts.length, 1, 'Double submission must be blocked');
    assert.equal(posts[0].get('username'), '198001012010011001');
    assert.equal(posts[0].get('password'), 'SandiUjiBrowser123');
    assert.equal(posts[0].get('ingat'), '1');
    assert.ok(posts[0].get('_token'), 'Native submission must contain CSRF token');
    assert.equal(await page.locator('[data-login-submit]').isDisabled(), false);
    assert.equal(await page.locator('#password').inputValue(), '');

    await page.goto('http://localhost/_login-qa/normal');
    await page.locator('[data-login-submit]').click();
    assert.equal(await page.locator('#username').evaluate(element => element.validity.valueMissing), true);
    assert.equal(posts.length, 1, 'Invalid form must not submit');
    const restored = await page.evaluate(() => {
        const form = document.querySelector('[data-login-form]');
        document.querySelector('#username').value = 'akun-uji-browser';
        document.querySelector('#password').value = 'sandi-uji-browser';
        form.addEventListener('submit', event => event.preventDefault(), {once: true});
        form.requestSubmit();
        const busy = document.querySelector('[data-login-submit]').disabled;
        window.dispatchEvent(new PageTransitionEvent('pageshow', {persisted: true}));
        return {busy, disabled: document.querySelector('[data-login-submit]').disabled, status: form.getAttribute('aria-busy')};
    });
    assert.deepEqual(restored, {busy: true, disabled: false, status: null});

    await page.goto('http://localhost/_login-qa/normal');
    await page.evaluate(() => {
        const event = new Event('beforeinstallprompt', {cancelable: true});
        Object.defineProperty(event, 'prompt', {value: async () => {}});
        Object.defineProperty(event, 'userChoice', {value: Promise.resolve({outcome: 'accepted'})});
        window.dispatchEvent(event);
    });
    assert.ok(await page.getByRole('button', {name: 'Pasang NUSA', exact: true}).isVisible());
    for (const [width, height] of [[320, 640], [390, 844], [768, 1024], [1440, 960], [1366, 650], [844, 390]]) {
        await page.setViewportSize({width, height});
        await assertLayout(page, width);
        if ([390, 1440].includes(width)) await page.screenshot({path: `${output}/install-${width}.png`, fullPage: true});
    }
    await page.setViewportSize({width: 390, height: 844});
    await page.getByRole('button', {name: 'Pasang NUSA', exact: true}).click();
    await page.getByText('NUSA sedang dipasang di perangkat ini.').waitFor();
    assert.equal(await page.locator('[data-pwa-install-container]').isVisible(), false);
    assert.equal(await page.evaluate(() => localStorage.length + sessionStorage.length), 0);

    const noScript = await browser.newContext({javaScriptEnabled: false, serviceWorkers: 'block'});
    const basicPage = await noScript.newPage();
    await prepare(basicPage);
    await basicPage.goto('http://localhost/_login-qa/normal');
    await basicPage.locator('#username').fill('akun-uji-tanpa-js');
    await basicPage.locator('#password').fill('sandi-uji-tanpa-js');
    await basicPage.getByRole('button', {name: 'Masuk', exact: true}).click();
    await basicPage.locator('#username_error').waitFor();
    assert.equal(posts.length, 2, 'Native form must also work without JavaScript');
    await noScript.close();

    assert.deepEqual(errors, [], 'JavaScript runtime errors');
    assert.deepEqual(missing, [], 'Missing assets');
    const liveChecks = [];
    if (process.env.LOGIN_LIVE_URL) {
        const liveContext = await browser.newContext({serviceWorkers: 'block'});
        const livePage = await liveContext.newPage();
        const failed = [], liveErrors = [];
        livePage.on('pageerror', error => liveErrors.push(error.message));
        livePage.on('response', response => { if (response.status() >= 400) failed.push({url: response.url(), status: response.status()}); });
        for (const [width, height] of [[390, 844], [1440, 960]]) {
            await livePage.setViewportSize({width, height});
            const response = await livePage.goto(process.env.LOGIN_LIVE_URL);
            assert.equal(response.status(), 200, 'Live login response');
            await livePage.getByRole('heading', {name: 'Masuk ke NUSA'}).click();
            await assertLayout(livePage, width);
            await livePage.screenshot({path: `${output}/live-${width}.png`, fullPage: true});
            liveChecks.push({width, height, status: response.status()});
        }
        assert.deepEqual(failed, [], 'Live assets must load');
        assert.deepEqual(liveErrors, [], 'Live JavaScript errors');
        await liveContext.close();
    }
    await writeFile(`${output}/audit.json`, JSON.stringify({checks, liveChecks, interactions: 'passed', errors, missing}, null, 2));
    console.log(JSON.stringify({responsivePages: checks.length, viewports: 9, liveChecks, interactions: 'passed', errors, missing}));
    await context.close();
} finally {
    await browser.close();
}
