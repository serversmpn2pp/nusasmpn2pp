import {readFile,mkdir,writeFile} from 'node:fs/promises';
import path from 'node:path';
import {pathToFileURL} from 'node:url';
import {execFileSync} from 'node:child_process';
import nodeAssert from 'node:assert/strict';
import jsQR from 'jsqr';
import sharp from 'sharp';

let checks=0;
const assert=new Proxy(nodeAssert,{get(target,key){return (...args)=>{checks++;return target[key](...args);};}});
const {chromium}=await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE).href);
const root=process.cwd(),folder=path.join(root,'storage/framework/testing/acara-buku-tamu'),out=path.join(root,'storage/logs/acara-buku-tamu');
await mkdir(out,{recursive:true});
const meta=JSON.parse(await readFile(path.join(folder,'meta.json'),'utf8'));
const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||undefined});
const results=[],errors=[];
try{
    const page=await browser.newPage();page.on('pageerror',e=>errors.push(e.message));
    await page.route('http://localhost/**',async route=>{
        const url=new URL(route.request().url()),match=url.pathname.match(/^\/audit-acara\/([a-z0-9-]+)$/);
        if(match)return route.fulfill({contentType:'text/html',body:await readFile(path.join(folder,match[1]+'.html'),'utf8')});
        if(url.pathname.startsWith('/kunjungi/')&&route.request().method()==='POST')return route.fulfill({status:200,contentType:'text/html',body:await readFile(path.join(folder,'sukses.html'),'utf8')});
        const file=path.resolve(root,'public','.'+decodeURIComponent(url.pathname));
        if(!file.startsWith(path.join(root,'public')+path.sep))return route.abort();
        try{const types={'.js':'application/javascript','.css':'text/css','.jpg':'image/jpeg','.png':'image/png','.webp':'image/webp','.woff2':'font/woff2'};return route.fulfill({body:await readFile(file),contentType:types[path.extname(file)]||'application/octet-stream'});}catch{return route.fulfill({status:404,body:''});}
    });
    for(const [width,height] of [[320,640],[390,844],[768,1024],[1440,1000]]){
        await page.setViewportSize({width,height});
        for(const name of ['publik','publik-dinamis','publik-utuh','sukses','index','form','form-edit','show','poster','a5','poster-utuh','cetak']){
            await page.goto('http://localhost/audit-acara/'+name);await page.evaluate(()=>document.fonts.ready);
            assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Horizontal overflow ${name} at ${width}`);
            if(name==='publik'){
                assert.equal(await page.locator('.app-sidebar').count(),0);
                assert.ok(await page.locator('.visitor-cover img').evaluate(e=>e.complete&&e.naturalWidth>0));
                assert.equal(await page.locator('.visitor-cover img').evaluate(e=>getComputedStyle(e).objectPosition),width<=650?'50% 74%':'50% 66%');
                assert.ok((await page.locator('.cover-caption').textContent()).includes('SMP NEGERI 2 PADANG PANJANG'));
                assert.equal(await page.locator('.satisfaction-choices svg').count(),5);
                await page.locator('[name="kepuasan"][value="5"]').check({force:true});
                await page.locator('[name="kepuasan"][value="2"]').check({force:true});
                assert.equal(await page.locator('[name="kepuasan"]:checked').count(),1);
                assert.equal(await page.locator('[name="kepuasan"]:checked').inputValue(),'2');
                await page.locator('[name="minat[]"][value="nusa"]').check({force:true});
                await page.locator('[name="minat[]"][value="literasi"]').check({force:true});
                assert.equal(await page.locator('[name="minat[]"]:checked').count(),2);
                assert.equal(await page.locator('#nilai_nusa').inputValue(),'0');
                const overlaps=await page.locator('.rating-content strong').evaluateAll(elements=>elements.filter(e=>e.scrollWidth>e.clientWidth+1).map(e=>e.textContent));
                assert.deepEqual(overlaps,[],`Rating labels overflow ${width}`);
                await page.locator('#nama').fill('Pengunjung Uji Browser');await page.locator('#kategori').selectOption('umum');
                await page.locator('[name="persetujuan"]').check();
                await page.locator('#visitor-submit').click();
                await page.waitForFunction(()=>document.body.textContent.includes('Terima kasih sudah hadir.'));
                assert.ok(!(await page.locator('body').textContent()).includes('Pengunjung Uji Browser'));
                await page.goto('http://localhost/audit-acara/publik');
            }
            if(name==='publik-dinamis'){
                assert.equal(await page.locator('[name="minat[]"]').first().inputValue(),'robotika');
                assert.equal(await page.locator('[name="minat[]"][value="literasi"]').count(),0);
                assert.equal(await page.locator('.interest-choice').first().locator('svg.lucide-flask-conical').count(),1);
                assert.deepEqual(await page.locator('.interest-choice>span').evaluateAll(els=>els.filter(e=>e.scrollWidth>e.clientWidth+1).map(e=>e.textContent)),[],'Dynamic interest labels clipped');
            }
            if(name==='publik-utuh'){
                assert.equal(await page.locator('.visitor-cover img').evaluate(e=>getComputedStyle(e).objectFit),'contain');
                assert.equal(await page.locator('.cover-caption').evaluate(e=>getComputedStyle(e).position),'static');
                assert.ok(await page.locator('.visitor-cover img').evaluate(e=>e.complete&&e.naturalWidth>0));
            }
            if(name==='form'){
                const banner=page.locator('[data-banner-editor]');
                assert.ok(await banner.locator('[data-banner-preview]').first().evaluate(e=>e.complete&&e.naturalWidth>0));
                assert.equal(await banner.getAttribute('data-banner-mode'),'potong');
                await banner.locator('#banner_fokus_desktop').focus();await page.keyboard.press('Home');await page.keyboard.press('ArrowRight');
                assert.equal(await banner.locator('output[for="banner_fokus_desktop"]').textContent(),'1%');
                await banner.locator('label:has([name="banner_tampilan"][value="utuh"])').click();
                assert.ok(await banner.locator('#banner_fokus_desktop').isDisabled());
                assert.ok(await banner.locator('#banner_fokus_mobile').isDisabled());
                assert.equal(await banner.locator('[data-banner-preview]').first().evaluate(e=>getComputedStyle(e).objectFit),'contain');
                await banner.locator('label:has([name="banner_tampilan"][value="potong"])').click();
                assert.ok(await banner.locator('#banner_fokus_desktop').isEnabled());
                await banner.locator('#banner').setInputFiles(path.join(root,'public/images/acara-buku-tamu-sekolah.webp'));
                await page.waitForFunction(()=>document.querySelector('[data-banner-info]').textContent.includes('1122 x 1402'));
                assert.ok((await banner.locator('[data-banner-preview]').first().getAttribute('src')).startsWith('blob:'));
                await banner.locator('#banner').setInputFiles({name:'kecil.png',mimeType:'image/png',buffer:await sharp({create:{width:10,height:10,channels:3,background:'#ffffff'}}).png().toBuffer()});
                await banner.locator('#banner-error').waitFor({state:'visible'});
                assert.ok((await banner.locator('#banner-error').textContent()).includes('1000 x 600'));
                assert.equal(await banner.locator('#banner').evaluate(e=>e.checkValidity()),false);
                await banner.locator('#banner').setInputFiles([]);
                assert.ok(await banner.locator('#banner-error').isHidden());
                assert.equal(await banner.locator('#banner').evaluate(e=>e.checkValidity()),true);
                const list=page.locator('[data-interest-list]'),rows=list.locator('[data-interest-row]');
                assert.equal(await rows.count(),5);
                assert.ok(await rows.first().locator('[data-interest-action="up"]').isDisabled());
                await page.locator('[data-interest-add]').click();
                assert.equal(await rows.count(),6);
                await rows.last().locator('[data-interest-field="nama"]').fill('Robotika siswa');
                await rows.last().locator('[data-interest-field="ikon"]').selectOption('flask-conical');
                assert.equal(await rows.last().locator('[data-interest-preview] svg.lucide-flask-conical').count(),1);
                const kode=await rows.last().locator('[data-interest-field="kode"]').inputValue();
                await rows.last().locator('[data-interest-action="up"]').click();
                assert.equal(await rows.nth(4).locator('[data-interest-field="kode"]').inputValue(),kode);
                await rows.nth(4).locator('[data-interest-action="up"]').click();
                await rows.nth(3).locator('[data-interest-action="down"]').click();
                assert.equal(await rows.nth(4).locator('[data-interest-field="nama"]').inputValue(),'Robotika siswa');
                await rows.nth(4).locator('input[type="checkbox"]').uncheck();
                assert.equal(await rows.nth(4).locator('input[type="checkbox"]').getAttribute('name'),'pilihan_minat[4][aktif]');
                assert.deepEqual(await page.locator('.event-admin form').evaluate(form=>new FormData(form).getAll('pilihan_minat[4][aktif]')),['0']);
                await rows.first().locator('[data-interest-action="remove"]').click();
                assert.equal(await rows.count(),5);
                assert.equal(await rows.nth(3).locator('[data-interest-field="kode"]').inputValue(),kode);
                assert.equal(await rows.nth(3).locator('[data-interest-field="nama"]').getAttribute('name'),'pilihan_minat[3][nama]');
                const duplicateIds=await page.locator('[id]').evaluateAll(els=>els.map(e=>e.id).filter((id,i,a)=>a.indexOf(id)!==i));
                assert.deepEqual(duplicateIds,[]);
                assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
                await page.screenshot({path:path.join(out,`minat-editor-${width}.png`),fullPage:true});
                if(width===390){
                    for(let n=5;n<30;n++)await page.locator('[data-interest-add]').click();
                    assert.equal(await rows.count(),30);
                    assert.ok(await page.locator('[data-interest-add]').isDisabled());
                }
            }
            if(name==='form-edit'){
                const used=page.locator('[data-interest-row][data-used="true"]');
                assert.equal(await used.count(),2);
                for(const row of await used.all())assert.ok(await row.locator('[data-interest-action="remove"]').isDisabled());
                assert.ok(await used.first().locator('input[type="checkbox"]').isEnabled());
                await page.screenshot({path:path.join(out,`minat-edit-${width}.png`),fullPage:true});
            }
            if(name==='show'){
                assert.equal(await page.locator('.event-chart canvas').count(),4);
                for(const id of ['event-daily','event-hourly','event-satisfaction','event-categories'])assert.ok(await page.locator('#'+id).evaluate(c=>{
                    const d=c.getContext('2d').getImageData(0,0,c.width,c.height).data;let n=0;for(let i=3;i<d.length;i+=4)if(d[i])n++;return n>100;
                }),`Blank chart ${id}`);
                await page.locator('[data-ui-dialog-open]').first().click();
                assert.ok(await page.locator('dialog[open]').isVisible());
                await page.keyboard.press('Escape');assert.equal(await page.locator('dialog[open]').count(),0);
            }
            if(name==='poster'||name==='a5'||name==='poster-utuh'){
                const pixels=await sharp(await page.locator('.poster-qr').screenshot()).ensureAlpha().raw().toBuffer({resolveWithObject:true});
                const decoded=jsQR(new Uint8ClampedArray(pixels.data),pixels.info.width,pixels.info.height);
                assert.equal(decoded?.data,meta.url,`QR does not decode ${name} at ${width}`);
                assert.equal(await page.locator('.poster-photo img').evaluate(e=>getComputedStyle(e).objectPosition),name==='poster-utuh'?'50% 50%':'50% 66%');
                if(name==='poster-utuh')assert.ok(await page.locator('.poster-photo').evaluate(e=>e.querySelector('img').getBoundingClientRect().bottom<=e.querySelector('div').getBoundingClientRect().top+1),'Poster caption overlaps full image');
            }
            if((width===390||width===1440)&&name!=='cetak')await page.screenshot({path:path.join(out,`${name}-${width}.png`),fullPage:true});
            results.push({name,width,height});
        }
    }
    await page.setViewportSize({width:1440,height:1100});
    for(const name of ['poster','a5','poster-panjang','poster-utuh','cetak']){
        await page.goto('http://localhost/audit-acara/'+name);await page.evaluate(()=>document.fonts.ready);await page.emulateMedia({media:'print'});
        if(name!=='cetak'){
            assert.equal(await page.locator('.poster-tools').isVisible(),false);
            const overflow=await page.evaluate(()=>{
                const sheet=document.querySelector('.poster-sheet').getBoundingClientRect();
                return [...document.querySelectorAll('.poster-sheet h1,.poster-sheet h2,.poster-sheet p,.poster-qr,.poster-bottom')].filter(e=>{const r=e.getBoundingClientRect();return r.bottom>sheet.bottom+1||r.right>sheet.right+1||r.left<sheet.left-1;}).map(e=>e.textContent.slice(0,100));
            });
            assert.deepEqual(overflow,[],`Print clipping ${name}`);
        }
        const pdf=path.join(out,name+'.pdf');await page.pdf({path:pdf,printBackground:true,preferCSSPageSize:true});
        const info=execFileSync(process.env.PDFINFO||'pdfinfo',[pdf],{encoding:'utf8'});
        if(name!=='cetak')assert.match(info,/Pages:\s+1\b/,`Poster must be one page: ${name}`);
        const prefix=path.join(out,name+'-print');
        execFileSync(process.env.PDFTOPPM||'pdftoppm',['-f','1','-singlefile','-scale-to','1600','-png',pdf,prefix]);
        if(name!=='cetak'){
            const pixels=await sharp(prefix+'.png').ensureAlpha().raw().toBuffer({resolveWithObject:true});
            assert.equal(jsQR(new Uint8ClampedArray(pixels.data),pixels.info.width,pixels.info.height)?.data,meta.url,`Printed QR does not decode ${name}`);
        }
        await page.emulateMedia({media:'screen'});
    }
    assert.deepEqual(errors,[]);
}finally{await browser.close();}
await writeFile(path.join(out,'results.json'),JSON.stringify({checks,viewports:4,pages:results.length,errors,results},null,2));
console.log(JSON.stringify({checks,viewports:4,pages:results.length,errors}));
