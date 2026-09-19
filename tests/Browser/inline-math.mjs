import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { resolve, extname } from 'node:path';
import { pathToFileURL } from 'node:url';
import assert from 'node:assert/strict';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE
    ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const build = resolve('public/build');
const manifest = JSON.parse(await readFile(resolve(build, 'manifest.json'), 'utf8'));
const entry = manifest['resources/js/soal-editor.js'];
const html = `<!doctype html><html><head>${entry.css.map(file => `<link rel="stylesheet" href="/${file}">`).join('')}
<style>body{font:16px Arial;margin:30px;max-width:900px}.textarea{display:block;border:1px solid #bbb;padding:12px;width:100%;box-sizing:border-box}button{padding:10px}label{display:block;margin:16px 0 8px}</style></head><body>
<form><label for="stimulus">Stimulus</label><textarea id="stimulus" name="stimulus" required>Server memiliki kapasitas </textarea>
<label>Pilihan A</label><textarea name="opsi[A]">Ukuran \\(2^{8}\\) MB</textarea>
<label>Pasangan</label><textarea name="pasangan_kanan[]">\\(\\frac{1}{2}\\)</textarea>
<button type="submit">Simpan</button></form><div class="question-title">Data \\(2^{12}\\) MB</div>
<script type="module" src="/${entry.file}"></script></body></html>`;
const server = createServer(async (req, res) => {
    try {
        if (req.url === '/') { res.setHeader('Content-Type', 'text/html'); res.end(html); return; }
        const file = resolve(build, `.${decodeURIComponent(req.url.split('?')[0]).replace(/^\/build\//, '/')}`);
        if (!file.startsWith(build + '/'.replace('/', process.platform === 'win32' ? '\\' : '/'))) { res.writeHead(403).end(); return; }
        res.setHeader('Content-Type', ({ '.js':'text/javascript', '.css':'text/css', '.woff2':'font/woff2' })[extname(file)] || 'application/octet-stream');
        res.end(await readFile(file));
    } catch { res.writeHead(404).end(); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
let browser;
try {
    browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined });
    const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
    const errors = []; page.on('pageerror', error => { errors.push(error.message); console.error(error.message); });
    await page.goto(`http://127.0.0.1:${server.address().port}`);
    await page.locator('.inline-equation-editor').first().waitFor();
    assert.equal(await page.locator('.inline-equation-editor').count(), 3);
    assert.equal(await page.locator('.question-title .katex').count(), 1);
    const editor = page.locator('.inline-equation-editor').first();
    await editor.click(); await page.keyboard.press('End');
    await page.getByRole('button', { name: 'Sisipkan rumus', exact: true }).first().click();
    await page.locator('dialog math-field').waitFor();
    await page.locator('dialog math-field').evaluate(field => { field.value = '2^{12}'; });
    await page.locator('dialog').getByRole('button', { name:'Sisipkan rumus', exact:true }).click();
    await page.keyboard.insertText('MB dalam cerita.');
    assert.equal(await page.locator('#stimulus').inputValue(), 'Server memiliki kapasitas \\(2^{12}\\) MB dalam cerita.');
    await editor.locator('[data-equation]').click();
    await page.locator('dialog math-field').evaluate(field => { field.value = '\\frac{1}{2}'; });
    await page.getByLabel('Baris tersendiri', { exact:true }).check();
    await page.getByRole('button', { name:'Simpan rumus', exact:true }).click();
    assert.match(await page.locator('#stimulus').inputValue(), /\\\[\\frac\{1\}\{2\}\\\]/);
    assert.equal(await editor.locator('.is-display .katex').count(), 1);
    await editor.evaluate(node => {
        const range = document.createRange(); range.setStart(node.firstChild, 7); range.collapse(true);
        const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
        node.dispatchEvent(new MouseEvent('mouseup', { bubbles:true }));
    });
    await page.getByRole('button', { name:'Sisipkan rumus', exact:true }).first().click();
    await page.locator('dialog math-field').evaluate(field => { field.value = '\\sqrt{4}'; });
    await page.locator('dialog').getByRole('button', { name:'Sisipkan rumus', exact:true }).click();
    assert.match(await page.locator('#stimulus').inputValue(), /^Server \\\(\\sqrt\{4\}\\\) memiliki/);
    await editor.evaluate(node => {
        const transfer = new DataTransfer(); transfer.setData('text/plain', '<img src=x onerror=alert(1)>');
        node.dispatchEvent(new ClipboardEvent('paste', { clipboardData:transfer, bubbles:true, cancelable:true }));
    });
    assert.equal(await editor.locator('img').count(), 0);
    await page.evaluate(() => {
        const row = document.createElement('div');
        row.innerHTML = '<textarea name="pengecoh_menjodohkan[]">\\(2^9\\)</textarea>';
        document.querySelector('form').append(row); window.initializeQuestionMediaEditors(row);
    });
    assert.equal(await page.locator('.inline-equation-editor').count(), 4);
    assert.equal(await page.locator('.inline-equation-editor').last().locator('.katex').count(), 1);
    await page.screenshot({ path:'storage/logs/inline-math-desktop.png', fullPage:true });
    assert.deepEqual(errors, []);
    console.log('PASS: inline insertion, cursor continuation, formula editing, display mode, existing equations, student rendering; no JS errors.');
} finally {
    await browser?.close();
    await new Promise(resolve => server.close(resolve));
}
