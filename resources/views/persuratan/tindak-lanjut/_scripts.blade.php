<script>
(() => {
    document.querySelectorAll('[data-cari-penerima]').forEach(input => input.addEventListener('input', () => {
        const cari = input.value.trim().toLocaleLowerCase('id-ID');
        input.closest('form').querySelectorAll('[data-penerima]').forEach(item => item.hidden = !item.textContent.toLocaleLowerCase('id-ID').includes(cari));
    }));
    let sibuk = false;
    document.querySelectorAll('[data-tindak-upload]').forEach(form => {
        form.addEventListener('submit', event => {
            if (sibuk) { event.preventDefault(); return; }
            sibuk = true;
            if (event.submitter?.name) {
                const aksi = document.createElement('input');
                aksi.type = 'hidden'; aksi.name = event.submitter.name; aksi.value = event.submitter.value;
                aksi.dataset.aksiSementara = '1'; form.appendChild(aksi);
            }
            form.querySelectorAll('button[type="submit"]').forEach(button => button.disabled = true);
            form.querySelector('[data-tindak-progress]').hidden = false;
            form.setAttribute('aria-busy', 'true');
        });
    });
    document.addEventListener('click', event => { if (sibuk && event.target.closest('a')) event.preventDefault(); });
    window.addEventListener('pageshow', () => {
        sibuk = false;
        document.querySelectorAll('[data-tindak-upload]').forEach(form => {
            form.removeAttribute('aria-busy');
            form.querySelectorAll('[data-aksi-sementara]').forEach(input => input.remove());
            form.querySelectorAll('button[type="submit"]').forEach(button => button.disabled = false);
            form.querySelector('[data-tindak-progress]').hidden = true;
        });
    });
})();
</script>
