(() => {
    'use strict';
    window.lucide?.createIcons();
    const form=document.getElementById('visitor-form'),button=document.getElementById('visitor-submit');
    if(form){
        form.addEventListener('submit',event=>{
            if(form.dataset.preview==='true'){event.preventDefault();return;}
            if(!form.querySelector('[name="minat[]"]:checked')){
                event.preventDefault();const first=form.querySelector('[name="minat[]"]');first.setCustomValidity('Pilih sedikitnya satu hal yang menarik perhatian Anda.');first.reportValidity();return;
            }
            if(!form.checkValidity())return;
            button.disabled=true;button.querySelector('span').textContent='Mengirim kunjungan...';
        });
        form.querySelectorAll('[name="minat[]"]').forEach(input=>input.addEventListener('change',()=>form.querySelector('[name="minat[]"]').setCustomValidity('')));
        window.addEventListener('pageshow',()=>{button.disabled=form.dataset.preview==='true';button.querySelector('span').textContent='Kirim kunjungan & kesan';});
    }
    document.querySelector('.visitor-errors')?.focus({preventScroll:false});
})();
