// Export the isolated Blade fixture with CBT_PHONE_FIXTURE=1 php artisan test --filter=test_paket_simulasi.
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve, extname, sep } from 'node:path';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const html = await readFile('storage/logs/cbt-phone-audit.html', 'utf8');
const browser = await chromium.launch({ headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || undefined });
try {
    const page = await browser.newPage({ viewport:{ width:390, height:844 }, isMobile:true, hasTouch:true });
    let mode = 'normal', securityResponseMode = 'normal', unblock;
    const errors = [];
    const savedPayloads = [];
    const securityPayloads = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        if (route.request().method() === 'POST') {
            const pathname = new URL(route.request().url()).pathname;
            if (pathname === '/cbt/ujian/aktivitas-keamanan') {
                let payload = {};
                try {
                    payload = route.request().postDataJSON();
                    securityPayloads.push(payload);
                } catch {}
                let data = {
                    mode:'pengerjaan',
                    kejadian_dihitung:false,
                    durasi_kejadian_detik:0,
                    pesan:null,
                    keamanan:{ jumlah_kejadian:0, batas_kejadian:3, sisa_kejadian:3, ditahan:false },
                };
                if (securityResponseMode === 'warning' && payload.peristiwa === 'kembali') {
                    data = {
                        mode:'pengerjaan',
                        kejadian_dihitung:true,
                        durasi_kejadian_detik:4,
                        pesan:'Peringatan 1 dari 3: Anda terdeteksi keluar dari NUSA.',
                        keamanan:{ jumlah_kejadian:1, batas_kejadian:3, sisa_kejadian:2, ditahan:false },
                    };
                }
                if (securityResponseMode === 'held' && ['kembali', 'heartbeat'].includes(payload.peristiwa)) {
                    data = {
                        mode:'ditahan',
                        kejadian_dihitung:true,
                        durasi_kejadian_detik:5,
                        pesan:'Ujian ditahan karena batas keluar aplikasi tercapai. Minta pengawas membuka ujian.',
                        keamanan:{ jumlah_kejadian:3, batas_kejadian:3, sisa_kejadian:0, ditahan:true },
                    };
                }
                return route.fulfill({
                    json:{ data },
                }).catch(() => {});
            }
            if (mode === 'offline') return route.abort('internetdisconnected');
            if (mode === 'slow') await new Promise(resolve => { unblock = resolve; });
            try { savedPayloads.push(route.request().postDataJSON()); } catch {}
            return route.fulfill({ json:{ terjawab:true, ragu:false, tersimpan_pada:'10:00:00' } }).catch(() => {});
        }
        if (new URL(route.request().url()).pathname === '/audit') return route.fulfill({ contentType:'text/html', body:html });
        const pathname = decodeURIComponent(new URL(route.request().url()).pathname);
        const roots = pathname.startsWith('/storage/')
            ? [resolve('storage/framework/testing/disks/public'), resolve('public')]
            : [resolve('public')];
        let file;
        for (const root of roots) {
            const relative = pathname.startsWith('/storage/') && root.endsWith(resolve('storage/framework/testing/disks/public'))
                ? pathname.slice('/storage'.length)
                : pathname;
            const candidate = resolve(root, '.' + relative);
            if (!candidate.startsWith(root + sep)) return route.abort();
            try {
                const body = await readFile(candidate);
                file = candidate;
                return route.fulfill({ body, contentType:({ '.js':'text/javascript', '.css':'text/css', '.jpg':'image/jpeg', '.jpeg':'image/jpeg', '.png':'image/png', '.webp':'image/webp', '.woff2':'font/woff2' })[extname(file).toLowerCase()] || 'application/octet-stream' });
            } catch {}
        }
        try {
            await route.fulfill({ body:await readFile(file) });
        } catch { await route.fulfill({ status:404, body:'' }); }
    });
    await page.goto('http://localhost/audit');
    await page.waitForTimeout(1200);
    assert.equal(securityPayloads.filter(payload => payload.peristiwa === 'kembali').length, 1, 'Halaman baru harus menutup catatan keluar yang mungkin masih terbuka');
    assert.equal(securityPayloads.filter(payload => payload.peristiwa === 'heartbeat').length, 1, 'Halaman ujian harus mengirim heartbeat awal');
    const go = async index => {
        await page.locator('button[data-question-index]').nth(index).evaluate(button => button.click());
        await page.waitForTimeout(100);
    };
    for (const width of [320, 360, 390, 412, 768, 1366]) {
        await page.setViewportSize({ width, height:844 });
        for (let index = 0; index < 12; index++) {
            await go(index);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Overflow: width ${width}, question ${index + 1}`);
        }
    }
    await page.setViewportSize({ width:320, height:568 });
    for (let index = 0; index < 12; index++) {
        await go(index);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Overflow: viewport 320x568, question ${index + 1}`);
    }
    await page.setViewportSize({ width:390, height:844 });
    const activeCard = () => page.locator('.question-card:not([hidden])');
    assert.equal(await page.locator('.save-bar.js-only').evaluate(node => getComputedStyle(node).position), 'static', 'Bilah navigasi HP tidak boleh menutupi isi soal');

    for (const index of [0, 1]) {
        await go(index);
        assert.equal(await activeCard().locator('.option-card input[type=radio]').count(), 4, `Pilihan ganda ${index + 1} harus memiliki empat radio`);
        assert.equal(await activeCard().locator('.option-card input[type=checkbox]').count(), 0, `Pilihan ganda ${index + 1} tidak boleh memakai checkbox`);
    }
    await go(1);
    const simulationImage = activeCard().locator('.question-media-figure img');
    await simulationImage.waitFor({ state:'visible' });
    assert.ok(await simulationImage.evaluate(image => image.complete && image.naturalWidth > 0), 'Gambar simulasi harus berhasil dimuat');
    assert.equal((await activeCard().locator('.question-media-figure figcaption').textContent()).trim(), 'Lingkungan sekolah yang perlu dijaga bersama.');
    await page.screenshot({ path:'storage/logs/cbt-phone-image.png', fullPage:true });

    for (const index of [2, 3]) {
        await go(index);
        assert.equal(await activeCard().locator('.option-card input[type=checkbox]').count(), 4, `Pilihan ganda kompleks ${index + 1} harus memiliki empat checkbox`);
        assert.equal(await activeCard().locator('.option-card input[type=radio]').count(), 0, `Pilihan ganda kompleks ${index + 1} tidak boleh memakai radio tunggal`);
    }
    for (const index of [4, 5]) {
        await go(index);
        assert.equal(await activeCard().locator('.statement-row').count(), 2, `Benar-salah ${index + 1} harus memiliki dua pernyataan`);
        assert.equal(await activeCard().locator('.statement-row input[type=radio]').count(), 4, `Benar-salah ${index + 1} harus memiliki pasangan pilihan Benar/Salah`);
    }
    for (const index of [6, 7]) {
        await go(index);
        assert.equal(await activeCard().locator('.matching-row').count(), 2, `Menjodohkan ${index + 1} harus memiliki dua pernyataan`);
        assert.equal(await activeCard().locator('.matching-answer-option').count(), 3, `Menjodohkan ${index + 1} harus menampilkan dua pasangan dan satu pengecoh`);
        assert.equal(await activeCard().locator('.matching-select').count(), 2, `Menjodohkan ${index + 1} harus memiliki dua kotak pilihan`);
        assert.equal(await activeCard().locator('.matching-select').first().locator('option').count(), 4, `Pilihan menjodohkan ${index + 1} harus memuat placeholder dan tiga jawaban`);
    }
    for (const index of [8, 9]) {
        await go(index);
        assert.equal(await activeCard().locator('input[type=text]:not([inputmode=decimal])').count(), 1, `Isian singkat ${index + 1} harus memiliki satu input teks`);
    }
    for (const index of [10, 11]) {
        await go(index);
        assert.equal(await activeCard().locator('input[inputmode=decimal]').count(), 1, `Numerik ${index + 1} harus memiliki input angka`);
    }
    await go(10);
    assert.ok(await activeCard().locator('.stimulus').textContent().then(text => text.includes('2^{3}') && text.includes('\\frac{1}{2}')), 'Stimulus numerik harus memuat kedua rumus');
    await activeCard().locator('.stimulus .katex').first().waitFor({ state:'visible' });
    assert.equal(await activeCard().locator('.stimulus .katex').count(), 2, 'Kedua rumus inline harus dirender oleh KaTeX');
    await go(11);
    assert.equal(await activeCard().locator('.question-media-table thead tr').count(), 1, 'Tabel harus memiliki kepala tabel');
    assert.equal(await activeCard().locator('.question-media-table tbody tr').count(), 2, 'Tabel harus memiliki dua baris data');
    assert.equal((await activeCard().locator('.question-media-table th').nth(1).textContent()).trim(), 'Jumlah', 'Judul kolom tabel tidak boleh terpotong');
    assert.ok(await page.locator('.question-card:not([hidden]) .question-media-table-scroll').evaluate(node => node.scrollWidth <= node.clientWidth + 1), 'Tabel sederhana harus muat tanpa geser horizontal');
    await page.screenshot({ path:'storage/logs/cbt-phone-fixed.png', fullPage:true });
    await go(0);
    const singleChoices = page.locator('.question-card:not([hidden]) .option-card input[type=radio]');
    for (let index = 0; index < await singleChoices.count(); index++) {
        await singleChoices.nth(index).check();
        assert.equal(await page.locator('.question-card:not([hidden]) .option-card input:checked').count(), 1);
        assert.ok(await singleChoices.nth(index).isChecked());
    }
    await page.waitForTimeout(500);
    mode = 'slow';
    await page.locator('.question-card:not([hidden]) input[type=radio]').first().evaluate(input => input.click());
    await page.waitForTimeout(250);
    await page.locator('#nextQuestion').click();
    assert.equal(await page.locator('#questionPosition').textContent(), 'Soal 2 dari 12');
    await page.waitForTimeout(10200);
    assert.match(await page.locator('.answer-save-state').textContent(), /Koneksi lambat/);
    mode = 'normal'; unblock();
    await page.locator('#retrySave').click();
    await page.waitForTimeout(300);
    assert.equal(await page.locator('.question-card').first().getAttribute('data-dirty'), '0');
    for (const index of [4, 6]) {
        await go(index);
        const card = page.locator('.question-card:not([hidden])');
        if (index === 4) await card.locator('input[type=radio]').first().evaluate(input => input.click());
        else await card.locator('select').first().selectOption({ index:1 });
        await page.waitForTimeout(300);
        assert.equal(await card.getAttribute('data-answered'), '0');
        assert.equal(await card.getAttribute('data-partial'), '1');
        if (index === 4) await card.locator('.statement-row').nth(1).locator('input[type=radio]').first().evaluate(input => input.click());
        else await card.locator('select').nth(1).selectOption({ index:2 });
        await page.waitForTimeout(300);
        assert.equal(await card.getAttribute('data-answered'), '1');
        assert.equal(await card.getAttribute('data-partial'), '0');
    }
    await go(1);
    await activeCard().locator('input[type=radio]').first().check();
    await go(2);
    await activeCard().locator('.option-card input[type=checkbox]').nth(0).check();
    await activeCard().locator('.option-card input[type=checkbox]').nth(1).check();
    assert.equal(await activeCard().locator('.option-card input[type=checkbox]:checked').count(), 2, 'Pilihan ganda kompleks harus menerima lebih dari satu jawaban');
    await go(3);
    await activeCard().locator('.option-card input[type=checkbox]').first().check();
    await go(5);
    await activeCard().locator('.statement-row').nth(0).locator('input[type=radio]').first().check();
    await activeCard().locator('.statement-row').nth(1).locator('input[type=radio]').last().check();
    await go(7);
    await activeCard().locator('select').nth(0).selectOption({ index:1 });
    await activeCard().locator('select').nth(1).selectOption({ index:2 });
    await go(8);
    await activeCard().locator('input[type=text]').fill('Selasa');
    await go(9);
    await activeCard().locator('input[type=text]').fill('penggaris');
    await go(10);
    await activeCard().locator('input[inputmode=decimal]').fill('4');
    await go(11);
    await activeCard().locator('input[inputmode=decimal]').fill('10');
    await page.waitForFunction(() => [...document.querySelectorAll('.question-card')].every(card => card.dataset.dirty === '0'), null, { timeout:5000 });
    const unansweredIndices = await page.locator('.question-card').evaluateAll(cards => cards
        .map((card, index) => card.dataset.answered === '1' ? null : index + 1)
        .filter(Boolean));
    assert.deepEqual(unansweredIndices, [], `Semua nomor harus berubah menjadi terjawab; belum lengkap: ${unansweredIndices.join(', ')}`);
    assert.equal(new Set(savedPayloads.map(payload => payload.soal_ujian_cbt_id).filter(Boolean)).size, 12, 'Semua jawaban harus terkirim melalui autosave');
    const finishButton = page.locator('#openFinishDialog');
    assert.equal(await finishButton.isEnabled(), true, 'Tombol kumpulkan harus aktif setelah semua soal lengkap');
    await finishButton.click();
    assert.equal(await page.locator('#finishDialog').evaluate(dialog => dialog.open), true, 'Dialog konfirmasi harus terbuka');
    assert.equal((await page.locator('#finishAnswered').textContent()).trim(), '12');
    assert.equal((await page.locator('#finishUnanswered').textContent()).trim(), '0');
    await page.locator('#cancelFinish').click();
    await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pagehide')));
    await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pagehide')));
    await page.waitForTimeout(100);
    assert.equal(securityPayloads.filter(payload => payload.peristiwa === 'keluar').length, 1, 'Pagehide berulang hanya boleh mencatat satu kejadian keluar');
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    await page.waitForTimeout(100);
    assert.equal(securityPayloads.filter(payload => payload.peristiwa === 'kembali').length, 2, 'Fokus kembali harus menutup kejadian keluar');
    assert.equal(securityPayloads.find(payload => payload.peristiwa === 'keluar')?.metadata.pemicu, 'pagehide');
    assert.equal(securityPayloads.filter(payload => payload.peristiwa === 'kembali').at(-1)?.metadata.pemicu, 'focus');

    securityResponseMode = 'warning';
    await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pagehide')));
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    await page.locator('#securityWarningDialog').waitFor({ state:'visible' });
    assert.equal((await page.locator('#securityWarningCount').textContent()).trim(), '1 / 3');
    assert.equal((await page.locator('#securityWarningRemaining').textContent()).trim(), '2');
    assert.match(await page.locator('#securityWarningMessage').textContent(), /terdeteksi keluar dari NUSA/);
    await page.screenshot({ path:'storage/logs/cbt-phone-security-warning.png', fullPage:true });
    await page.locator('#securityWarningConfirm').click();

    securityResponseMode = 'held';
    await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pagehide')));
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    await page.locator('#securityHoldScreen').waitFor({ state:'visible' });
    assert.equal(await page.locator('#formUjian').evaluate(form => form.inert), true, 'Form ujian harus terkunci saat Mode Aman menahan peserta');
    assert.equal((await page.locator('#securityHoldCount').textContent()).trim(), '3 / 3');
    assert.match(await page.locator('#securityHoldStatus').textContent(), /Minta pengawas membuka ujian/);
    await page.setViewportSize({ width:320, height:568 });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Layar tahan Mode Aman tidak boleh melebar pada HP kecil');
    await page.screenshot({ path:'storage/logs/cbt-phone-security-held.png', fullPage:true });

    securityResponseMode = 'normal';
    await page.locator('#checkSecurityStatus').click();
    await page.locator('#securityHoldScreen').waitFor({ state:'hidden' });
    assert.equal(await page.locator('#formUjian').evaluate(form => form.inert), false, 'Form ujian harus aktif setelah dibuka pengawas');
    await page.setViewportSize({ width:390, height:844 });

    const exitsBeforeSubmit = securityPayloads.filter(payload => payload.peristiwa === 'keluar').length;
    await page.evaluate(() => document.getElementById('formUjian').addEventListener('submit', event => event.preventDefault(), { once:true }));
    await finishButton.click();
    await page.locator('#confirmFinish').click();
    await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pagehide')));
    await page.waitForTimeout(100);
    assert.equal(securityPayloads.filter(payload => payload.peristiwa === 'keluar').length, exitsBeforeSubmit, 'Pengumpulan ujian tidak boleh dianggap sebagai keluar dari halaman');
    assert.deepEqual(errors, []);
    console.log('PASS: 84 viewport/question checks; six question types; image and caption; distractor; formula and table; single/multiple-choice behavior; all-answer autosave; finish readiness; timeout and retry; partial/complete statements and matching; web security warning, hold, release, heartbeat, and lifecycle reporting.');
} finally { await browser.close(); }
