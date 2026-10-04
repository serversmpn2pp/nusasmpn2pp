@push('scripts')
<script>
    document.querySelectorAll('[data-agenda-submit]').forEach(form => form.addEventListener('submit', () => {
        Array.from(form.elements).filter(element => element.tagName === 'BUTTON' && element.type === 'submit').forEach(button => {
            button.dataset.originalLabel = button.textContent;
            button.disabled = true;
            button.textContent = 'Menyimpan...';
        });
    }));
    window.addEventListener('pageshow', () => document.querySelectorAll('[data-original-label]').forEach(button => {
        button.disabled = false;
        button.textContent = button.dataset.originalLabel;
    }));
    const statusAgenda = document.getElementById('status_agenda');
    if (statusAgenda) {
        const setAlasan = () => {
            const dibatalkan = statusAgenda.value === 'dibatalkan';
            document.getElementById('alasan_batal_field').hidden = !dibatalkan;
            document.getElementById('alasan_pembatalan').required = dibatalkan;
        };
        statusAgenda.addEventListener('change', setAlasan);
        setAlasan();
    }
    const daftarUndangan = document.getElementById('daftar_undangan');
    if (daftarUndangan) {
        let urutanUndangan = daftarUndangan.children.length;
        document.getElementById('tambah_baris_undangan').addEventListener('click', () => {
            if (daftarUndangan.children.length >= 100) return;
            const row = document.getElementById('template_undangan').content.cloneNode(true);
            row.querySelectorAll('[data-field]').forEach(input => {
                input.name = `peserta[${urutanUndangan}][${input.dataset.field}]`;
                input.id = `undangan_${urutanUndangan}_${input.dataset.field}`;
                input.closest('.field').querySelector('label').htmlFor = input.id;
            });
            urutanUndangan++;
            daftarUndangan.appendChild(row);
            daftarUndangan.lastElementChild.querySelector('input').focus();
        });
        daftarUndangan.addEventListener('click', event => {
            const button = event.target.closest('[data-hapus-baris]');
            if (button && daftarUndangan.children.length > 1) button.closest('.agenda-invite-row').remove();
        });
    }
    const pilihanCakupan = document.querySelectorAll('input[name="cakupan"]');
    if (pilihanCakupan.length) {
        const tampilkanCakupan = () => {
            const pilihan = document.querySelector('input[name="cakupan"]:checked').value;
            document.querySelectorAll('[data-scope]').forEach(section => {
                section.hidden = section.dataset.scope !== pilihan;
                section.querySelectorAll('input,select').forEach(input => input.disabled = section.hidden);
            });
        };
        pilihanCakupan.forEach(input => input.addEventListener('change', tampilkanCakupan));
        tampilkanCakupan();
    }
    const salinQr = document.getElementById('salin_qr');
    salinQr?.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(salinQr.dataset.url);
            document.getElementById('salin_status').textContent = 'Tautan berhasil disalin.';
        } catch {
            document.querySelector('.agenda-qr-link').select();
            document.getElementById('salin_status').textContent = 'Pilih dan salin tautan di atas.';
        }
    });
    const monitorQr = document.getElementById('qr_monitor');
    if (monitorQr) {
        let memuatPresensi = false;
        const status = document.getElementById('pantau_status');
        const tombol = document.getElementById('segarkan_presensi');
        const muatPresensi = async () => {
            if (memuatPresensi || document.hidden) return;
            memuatPresensi = true;
            tombol.disabled = true;
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 8000);
            try {
                const response = await fetch(monitorQr.dataset.url, { headers: { Accept: 'application/json' }, signal: controller.signal, cache: 'no-store' });
                if (!response.ok) throw new Error('Gagal memuat presensi');
                const data = await response.json();
                Object.entries(data.rekap).forEach(([kode, jumlah]) => {
                    const target = document.querySelector(`[data-rekap="${kode}"]`);
                    if (target) target.textContent = jumlah;
                });
                monitorQr.dataset.dibuka = data.dibuka ? '1' : '0';
                const badge = document.getElementById('qr_status');
                badge.textContent = data.dibuka ? 'Presensi dibuka' : 'Presensi ditutup';
                badge.className = `agenda-badge agenda-badge--${data.dibuka ? 'selesai' : 'dibatalkan'}`;
                const daftar = document.getElementById('qr_terbaru');
                daftar.replaceChildren();
                data.terbaru.forEach(item => {
                    const row = document.createElement('tr');
                    [item.nama, new Date(item.hadir_pada).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' }) + ' WIB', item.sumber_kehadiran === 'qr' ? 'QR' : 'Petugas'].forEach(value => {
                        const cell = document.createElement('td');
                        cell.textContent = value;
                        row.appendChild(cell);
                    });
                    daftar.appendChild(row);
                });
                if (!data.terbaru.length) {
                    const row = daftar.insertRow();
                    const cell = row.insertCell();
                    cell.colSpan = 3;
                    cell.textContent = 'Belum ada peserta hadir.';
                }
                status.textContent = 'Diperbarui ' + new Date().toLocaleTimeString('id-ID') + (data.dibuka ? ' / Presensi dibuka' : ' / Presensi ditutup');
            } catch {
                status.textContent = 'Kehadiran belum dapat diperbarui. Coba kembali atau muat ulang halaman.';
            } finally {
                clearTimeout(timeout);
                memuatPresensi = false;
                tombol.disabled = false;
            }
        };
        tombol.addEventListener('click', muatPresensi);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) muatPresensi(); });
        setInterval(() => { if (monitorQr.dataset.dibuka === '1') muatPresensi(); }, 10000);
        muatPresensi();
    }
</script>
@endpush
