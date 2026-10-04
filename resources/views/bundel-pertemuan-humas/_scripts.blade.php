<script>
document.addEventListener('DOMContentLoaded',()=>{
    const form=document.querySelector('#bp-form'),status=document.querySelector('#bp-status'),progress=document.querySelector('#bp-processing');
    let busy=false;
    const choices=name=>[...form.querySelectorAll(`input[name="${name}"]:not(:disabled)`)];
    const sync=()=>{
        document.querySelector('#bp-selection').textContent=`${choices('dokumen_ids[]').filter(el=>el.checked).length} lampiran \u00b7 ${choices('formulir_ids[]').filter(el=>el.checked).length} rekap dipilih`;
        form.querySelectorAll('[data-all]').forEach(all=>{const items=choices(all.dataset.all),n=items.filter(el=>el.checked).length;all.checked=items.length>0&&n===items.length;all.indeterminate=n>0&&n<items.length;all.disabled=!items.length||busy;});
    };
    form.querySelectorAll('[data-all]').forEach(all=>all.addEventListener('change',()=>{choices(all.dataset.all).forEach(el=>el.checked=all.checked);sync();}));
    form.querySelectorAll('input[type="checkbox"][name]').forEach(el=>el.addEventListener('change',sync));sync();
    window.addEventListener('beforeunload',event=>{if(busy){event.preventDefault();event.returnValue='';}});
    form.addEventListener('submit',async event=>{
        if(event.submitter?.hasAttribute('data-print'))return;
        event.preventDefault(); if(busy)return;
        const data=new FormData(form),button=form.querySelector('[data-download]');if(!button||button.disabled)return;
        busy=true;button.disabled=true;form.setAttribute('aria-busy','true');progress.hidden=false;status.hidden=true;
        const controls=[...form.querySelectorAll('input[type="checkbox"],button[data-print]')];
        const disabled=controls.map(el=>el.disabled);controls.forEach(el=>el.disabled=true);
        try {
            const response=await fetch(form.action,{method:'POST',body:data,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
            if(!response.ok){
                let error=null;try{error=await response.json();}catch{}
                throw new Error(Object.values(error?.errors||{}).flat().join(' ')||({403:'Akses unduhan tidak diizinkan.',419:'Sesi berakhir. Muat ulang halaman sebelum mengunduh.',429:'Terlalu banyak permintaan. Coba lagi sebentar.'})[response.status]||'Bundel gagal diproses. Coba lagi atau hubungi administrator.');
            }
            if(!response.headers.get('Content-Type')?.includes('application/zip'))throw new Error('Sesi atau respons unduhan tidak sesuai. Muat ulang halaman.');
            const blob=await response.blob();if(!blob.size)throw new Error('Berkas bundel kosong. Coba lagi.');
            const disposition=response.headers.get('Content-Disposition')||'';
            const match=disposition.match(/filename="?([^";]+)"?/i);
            const filename=(match?.[1]||'bundel-pertemuan.zip').replace(/[\\/]/g,'-');
            const url=URL.createObjectURL(blob),link=document.createElement('a');link.href=url;link.download=filename;document.body.appendChild(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(url),60000);
            status.textContent='Bundel siap. Unduhan telah dikirim ke browser.';status.classList.remove('bp-status--error');
        } catch(error){status.textContent=error.message;status.classList.add('bp-status--error');}
        finally{busy=false;progress.hidden=true;status.hidden=false;button.disabled=false;form.removeAttribute('aria-busy');controls.forEach((el,i)=>el.disabled=disabled[i]);sync();}
    });
});
</script>
