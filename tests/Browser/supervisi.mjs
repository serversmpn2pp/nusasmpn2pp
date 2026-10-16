import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {pathToFileURL} from 'node:url';

const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const browser = await chromium.launch({headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || undefined});
const output = resolve('storage/logs/supervisi-qa');
await mkdir(output,{recursive:true});
try {
    const page = await browser.newPage(), errors=[];
    page.on('pageerror',e=>errors.push(e.message));
    await page.route('**/*',async route=>{
        const url=new URL(route.request().url()), name=url.pathname.match(/^\/audit\/([a-z-]+)$/)?.[1];
        if(name) return route.fulfill({contentType:'text/html',body:await readFile(`storage/framework/testing/supervisi/${name}.html`,'utf8')});
        if(route.request().method()==='POST') return route.fulfill({contentType:'text/html',body:'<!doctype html><html><body>Form submission captured</body></html>'});
        const root=resolve('public'), path=resolve(root,'.'+decodeURIComponent(url.pathname));
        if(!path.startsWith(root+sep))return route.abort();
        try{return route.fulfill({body:await readFile(path),contentType:({'.css':'text/css','.js':'text/javascript','.png':'image/png','.woff2':'font/woff2'})[extname(path)]||'application/octet-stream'});}
        catch{return route.fulfill({status:404,body:''});}
    });
    const pages=['index','program','program-show','program-form','program-edit','jadwal-form','jadwal-edit','instrumen','instrumen-show','instrumen-form','instrumen-revisi','sesi','pra-guru','pra-supervisor','nilai-tertutup','nilai-supervisor','nilai-final','nilai-koreksi','pasca-guru','pasca-review','pasca-rencana','tindak-draf','tindak-guru','tindak-review','pasca-selesai','tindak-selesai','pasca-arsip','akses-kurikulum','akses-pimpinan','akses-guru','akses-supervisor','akses-tu','akses-delegasi','akses-instrumen'];
    for(const width of [320,390,768,1366,1920]) {
        await page.setViewportSize({width,height:1000});
        for(const name of pages) {
            await page.goto(`http://localhost/audit/${name}`);
            assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Page overflow ${name}@${width}`);
            const clipped=await page.locator('.sp-stat,.sp-head,.sp-footer,.sp-nav,.sp-context,.sp-savebar,.sp-stage-nav,.sp-score-group summary,.sp-score-row,.sp-file-row,.sp-task-top,.sp-task-body,.sp-bulk-plan').evaluateAll(els=>els.filter(e=>e.scrollWidth>e.clientWidth+1).map(e=>e.textContent.trim()));
            assert.deepEqual(clipped,[],`Clipped controls ${name}@${width}`);
            if([390,1366].includes(width)&&['index','program-show','jadwal-form','instrumen-form','instrumen-revisi','pra-guru','nilai-supervisor','nilai-final','pasca-guru','pasca-rencana','tindak-guru','tindak-review'].includes(name))await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
        }
    }
    const roleMenus={kurikulum:['/supervisi','/supervisi/program','/supervisi/rekap','/supervisi/instrumen'],pimpinan:['/supervisi','/supervisi/program','/supervisi/rekap'],guru:['/supervisi'],supervisor:['/supervisi'],tu:[],delegasi:['/supervisi','/supervisi/program','/supervisi/rekap'],instrumen:['/supervisi/instrumen']};
    for(const width of [390,1366]) {
        await page.setViewportSize({width,height:1000});
        for(const [role,expected] of Object.entries(roleMenus)) {
            await page.goto(`http://localhost/audit/akses-${role}`);
            const links=await page.locator('.app-sidebar a[href]').evaluateAll(els=>els.map(e=>new URL(e.href).pathname).filter(p=>p==='/supervisi'||p.startsWith('/supervisi/')));
            assert.deepEqual(links.sort(),[...expected].sort(),`Role sidebar ${role}@${width}`);
            assert.equal(await page.getByRole('link',{name:'Kelola program',exact:true}).count(),['kurikulum','delegasi'].includes(role)?1:0,`Management command ${role}`);
            if(width===390) {
                await page.getByLabel('Buka menu utama',{exact:true}).click();
                await page.waitForFunction(()=>Math.abs(document.querySelector('.app-sidebar').getBoundingClientRect().left)<1);
            }
            if(['kurikulum','guru','delegasi'].includes(role))await page.screenshot({path:`${output}/akses-${role}-${width}.png`});
        }
    }
    await page.setViewportSize({width:1366,height:1000});
    for(const width of [390,1366]) {
        await page.setViewportSize({width,height:1000});
        await page.goto('http://localhost/audit/nilai-supervisor');
        await page.locator('[data-sp-score-group]').first().screenshot({path:`${output}/nilai-indikator-${width}.png`});
    }
    await page.setViewportSize({width:1366,height:1000});
    await page.goto('http://localhost/audit/instrumen-revisi');
    assert.equal(await page.locator('[data-indicator]').count(),79);
    assert.equal(await page.locator('#sp-planning-count').innerText(),'30');
    assert.equal(await page.locator('#sp-execution-count').innerText(),'49');
    assert.equal(await page.locator('#sp-planning-max').innerText(),'Skor maksimal 120');
    assert.equal(await page.locator('#sp-execution-max').innerText(),'Skor maksimal 196');
    await page.locator('[data-indicator]').first().fill('Indikator versi revisi yang diperbarui');
    assert.equal(await page.locator('[data-indicator]').first().getAttribute('name'),'kelompok[0][indikator][0]');
    await page.goto('http://localhost/audit/instrumen-form');
    assert.equal(await page.locator('#sp-reflection-prompts textarea').count(),7);
    await page.getByRole('button',{name:'Tambah pertanyaan refleksi',exact:true}).click();
    assert.equal(await page.locator('#sp-reflection-prompts textarea').count(),8);
    await page.locator('#sp-reflection-prompts textarea').last().fill('Pertanyaan refleksi baru');
    await page.getByRole('button',{name:'Pindahkan pertanyaan ke atas',exact:true}).last().click();
    assert.equal(await page.locator('#sp-reflection-prompts textarea').nth(6).inputValue(),'Pertanyaan refleksi baru');
    await page.getByRole('button',{name:'Hapus pertanyaan refleksi',exact:true}).last().click();
    assert.equal(await page.locator('#sp-reflection-prompts textarea').count(),7);
    assert.equal(await page.locator('[data-group]').count(),2);
    assert.equal(await page.locator('#sp-planning-count').innerText(),'1');
    await page.getByRole('button',{name:'Tambah indikator',exact:true}).first().click();
    assert.equal(await page.locator('#sp-planning-count').innerText(),'2');
    assert.equal(await page.locator('#sp-planning-max').innerText(),'Skor maksimal 8');
    await page.getByRole('button',{name:'Tambah skor',exact:true}).click();
    assert.equal(await page.locator('#sp-max-score').innerText(),'5');
    assert.equal(await page.locator('#sp-planning-max').innerText(),'Skor maksimal 10');
    await page.getByRole('button',{name:'Hapus skor terakhir',exact:true}).click();
    assert.equal(await page.locator('#sp-max-score').innerText(),'4');
    await page.getByRole('button',{name:'Tambah kelompok indikator',exact:true}).click();
    assert.equal(await page.locator('[data-group]').count(),3);
    await page.locator('[data-stage]').last().selectOption('pelaksanaan');
    assert.equal(await page.locator('#sp-execution-count').innerText(),'2');
    const names=await page.locator('#sp-instrument-form').evaluate(f=>[...new FormData(f).keys()]);
    assert.ok(names.includes('kelompok[2][tahap]')&&names.includes('kelompok[0][indikator][1]'));
    page.once('dialog',d=>d.accept());
    await page.getByRole('button',{name:'Hapus kelompok',exact:true}).last().click();
    assert.equal(await page.locator('[data-group]').count(),2);
    await page.getByRole('button',{name:'Pindahkan kelompok ke atas',exact:true}).last().click();
    assert.equal(await page.locator('[data-stage]').first().inputValue(),'pelaksanaan');
    await page.goto('http://localhost/audit/jadwal-form');
    assert.equal(await page.locator('#sp-schedule-rows > section').count(),1);
    await page.getByRole('button',{name:'Tambah sesi',exact:true}).click();
    const tokens=await page.locator('[data-key=token_pembuatan]').evaluateAll(els=>els.map(e=>e.value));
    assert.equal(new Set(tokens).size,2);
    await page.getByRole('button',{name:'Hapus sesi',exact:true}).first().click();
    assert.equal(await page.locator('[data-key=token_pembuatan]').getAttribute('name'),'sesi[0][token_pembuatan]');
    await page.goto('http://localhost/audit/program-show');
    await page.getByRole('button',{name:'Pilih draf',exact:true}).click();
    assert.equal(await page.locator('[data-sp-select]:checked').count(),1);
    assert.equal(await page.locator('[data-sp-field]:enabled').count(),2);
    await page.getByRole('button',{name:'Pilih terjadwal',exact:true}).click();
    assert.equal(await page.locator('[data-sp-select]:checked').count(),0);
    await page.goto('http://localhost/audit/nilai-supervisor');
    assert.equal(await page.locator('[data-sp-score]').count(),79);
    await page.locator('[data-sp-score]').first().selectOption('');
    assert.equal(await page.locator('[data-sp-count="perencanaan"]').innerText(),'29 / 30');
    await page.locator('[data-sp-score]').first().selectOption('0');
    assert.equal(await page.locator('[data-sp-count="perencanaan"]').innerText(),'30 / 30');
    await page.locator('[data-sp-secondary] [name=konfirmasi]').check();
    let message;
    page.once('dialog',async dialog=>{message=dialog.message();await dialog.accept();});
    await page.getByRole('button',{name:'Finalisasi hasil',exact:true}).click();
    assert.ok(message?.includes('Simpan perubahan draf terlebih dahulu'));
    const posted=page.waitForRequest(request=>request.method()==='POST'&&new URL(request.url()).pathname.endsWith('/penilaian'));
    const navigation=page.waitForNavigation();
    await page.getByRole('button',{name:'Simpan draf penilaian',exact:true}).click();
    const request=await posted, body=new URLSearchParams(request.postData()), rows=JSON.parse(body.get('nilai_json'));
    assert.equal(rows.length,79);
    assert.equal(rows[0].skor,0);
    assert.ok([...body.keys()].length<20);
    assert.ok(![...body.keys()].some(key=>key.startsWith('nilai[')));
    await navigation;
    await page.goto('http://localhost/audit/nilai-final');
    assert.equal(await page.locator('[data-sp-score]').count(),0);
    assert.equal(await page.getByRole('button',{name:'Simpan draf penilaian',exact:true}).count(),0);
    await page.goto('http://localhost/audit/pasca-guru');
    assert.equal(await page.locator('.sp-reflection textarea').count(),7);
    await page.locator('.sp-reflection textarea').first().fill('Refleksi pembelajaran yang belum disimpan');
    assert.ok((await page.locator('[data-sp-save-state]').innerText()).includes('belum disimpan'));
    page.once('dialog',dialog=>dialog.accept());
    await page.goto('http://localhost/audit/pasca-rencana');
    await page.locator('[data-sp-plan-select]').first().check();
    assert.equal(await page.locator('#sp-publish-plans-button').isEnabled(),true);
    const planData=await page.locator('#sp-publish-plans').evaluate(form=>[...new FormData(form).keys()]);
    assert.ok(planData.includes('rencana[0][id]')&&planData.includes('rencana[0][versi]'));
    await page.locator('[data-sp-plan-select]').first().uncheck();
    assert.equal(await page.locator('#sp-publish-plans-button').isDisabled(),true);
    await page.goto('http://localhost/audit/tindak-guru');
    assert.equal(await page.getByRole('button',{name:'Simpan draf pelaksanaan',exact:true}).count(),1);
    assert.equal(await page.getByRole('button',{name:'Verifikasi selesai',exact:true}).count(),0);
    await page.goto('http://localhost/audit/tindak-review');
    assert.equal(await page.getByRole('button',{name:'Verifikasi selesai',exact:true}).count(),1);
    assert.equal(await page.getByRole('button',{name:'Simpan draf pelaksanaan',exact:true}).count(),0);
    assert.deepEqual(errors,[]);
    console.log(JSON.stringify({result:'passed',pages:pages.length,widths:[320,390,768,1366,1920],checks:['role-sidebar-matrix','management-commands','responsive','dynamic-indicators','dynamic-reflections','dynamic-scale-max','group-order','group-removal','batch-session-tokens','batch-selection','score-progress','unsaved-guard','large-form-json-submit','final-readonly','followup-bulk-selection','followup-role-controls','no-js-errors']}));
} finally {await browser.close();}
