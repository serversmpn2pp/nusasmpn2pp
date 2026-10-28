(() => {
    'use strict';
    const editor = document.querySelector('[data-banner-editor]');
    if (!editor) return;
    const rules = JSON.parse(editor.dataset.bannerRules);
    const input = editor.querySelector('#banner');
    const error = editor.querySelector('#banner-error');
    const info = editor.querySelector('[data-banner-info]');
    const previews = [...editor.querySelectorAll('[data-banner-preview]')];
    const original = previews[0].dataset.bannerOriginal;
    let objectURL = null;
    let revision = 0;

    function apply() {
        const mode = editor.querySelector('[name="banner_tampilan"]:checked').value;
        editor.dataset.bannerMode = mode;
        editor.querySelectorAll('input[type="range"]').forEach(slider => {
            const device = slider.name === 'banner_fokus_desktop' ? 'desktop' : 'mobile';
            editor.style.setProperty('--banner-fokus-' + device, slider.value + '%');
            editor.querySelector('output[for="' + slider.id + '"]').textContent = slider.value + '%';
            slider.disabled = mode === 'utuh';
        });
    }

    function message(text) {
        input.setCustomValidity(text);
        input.setAttribute('aria-invalid', text ? 'true' : 'false');
        error.textContent = text;
        error.hidden = !text;
    }

    function show(source) {
        previews.forEach(image => { image.src = source; });
    }

    input.addEventListener('change', () => {
        const current = ++revision;
        const file = input.files[0];
        message('');
        info.textContent = '';
        show(original);
        if (objectURL) { URL.revokeObjectURL(objectURL); objectURL = null; }
        if (!file) return;
        if (file.type && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            message('Format gambar harus JPG, PNG, atau WebP.');
            return;
        }
        if (file.size > rules.maksimal_kb * 1024) {
            message('Ukuran gambar maksimal 4 MB.');
            return;
        }
        const source = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            if (current !== revision) { URL.revokeObjectURL(source); return; }
            const w = image.naturalWidth, h = image.naturalHeight;
            let text = '';
            if (w < rules.minimal_lebar || h < rules.minimal_tinggi || w > rules.maksimal_sisi || h > rules.maksimal_sisi) {
                text = 'Gambar minimal 1000 x 600 px dan maksimal 6000 px pada setiap sisi.';
            } else if (w * h > rules.maksimal_piksel) {
                text = 'Resolusi gambar maksimal 24 megapiksel.';
            }
            if (text) {
                message(text);
                URL.revokeObjectURL(source);
                return;
            }
            objectURL = source;
            show(source);
            info.textContent = w + ' x ' + h + ' px · ' + (file.size / 1024 / 1024).toFixed(2) + ' MB';
        };
        image.onerror = () => {
            URL.revokeObjectURL(source);
            if (current === revision) message('Gambar tidak dapat dibaca. Pilih JPG, PNG, atau WebP yang valid.');
        };
        image.src = source;
    });
    editor.addEventListener('input', apply);
    apply();
})();
