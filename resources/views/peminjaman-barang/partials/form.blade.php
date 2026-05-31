@php
    $nilai = fn (string $field, mixed $default = '') => old($field, $default);
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
@endphp

<style>
    .loan-workspace {
        display: grid;
        gap: 18px;
    }

    .loan-scan-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(210px, .55fr);
        gap: 16px;
    }

    .loan-inline-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 130px auto;
        gap: 10px;
        align-items: end;
    }

    .scan-status {
        min-height: 42px;
        border-left: 4px solid #15477A;
        background: #eff6ff;
        padding: 10px 12px;
        color: #15477A;
        font-size: .86rem;
        font-weight: 800;
    }

    .scan-status.error {
        border-left-color: #b42318;
        background: #fef3f2;
        color: #b42318;
    }

    .scan-status.success {
        border-left-color: #18864b;
        background: #ecfdf3;
        color: #166534;
    }

    .borrower-summary {
        min-height: 84px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background: #f8fafc;
        padding: 14px;
    }

    .loan-cart-table input {
        min-width: 88px;
    }

    @media (max-width: 840px) {
        .loan-scan-grid,
        .loan-inline-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Ada data yang perlu diperbaiki.</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="loan-workspace">
    <section class="panel panel-pad">
        <p class="eyebrow">Langkah 1</p>
        <h2 class="panel-title">Pilih peminjam</h2>

        <div class="form-grid" style="margin-top: 16px;">
            <div class="field">
                <label for="jenis_peminjam">Jenis peminjam</label>
                <select id="jenis_peminjam" name="jenis_peminjam" class="{{ $selectClass('jenis_peminjam') }}" required>
                    <option value="siswa" @selected($nilai('jenis_peminjam', 'siswa') === 'siswa')>Siswa</option>
                    <option value="pegawai" @selected($nilai('jenis_peminjam', 'siswa') === 'pegawai')>Pegawai</option>
                </select>
            </div>

            <div class="field">
                <label for="kode_peminjam_scan">Scan kartu peminjam</label>
                <div class="actions" style="gap: 8px;">
                    <input id="kode_peminjam_scan" type="text" class="input" placeholder="Scan NISN atau NIP" autocomplete="off">
                    <button id="tombol_scan_peminjam" type="button" class="button button-dark">Proses</button>
                </div>
            </div>

            <div id="bungkus_siswa" class="field">
                <label for="siswa_id">Pilih siswa manual</label>
                <select id="siswa_id" name="siswa_id" class="{{ $selectClass('siswa_id') }}">
                    <option value="">Pilih siswa</option>
                    @foreach ($daftarSiswa as $siswa)
                        <option value="{{ $siswa['id'] }}" @selected((string) $nilai('siswa_id') === (string) $siswa['id'])>{{ $siswa['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div id="bungkus_pegawai" class="field">
                <label for="pegawai_id">Pilih pegawai manual</label>
                <select id="pegawai_id" name="pegawai_id" class="{{ $selectClass('pegawai_id') }}">
                    <option value="">Pilih pegawai</option>
                    @foreach ($daftarPegawai as $pegawai)
                        <option value="{{ $pegawai['id'] }}" @selected((string) $nilai('pegawai_id') === (string) $pegawai['id'])>{{ $pegawai['label'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <input id="cara_input_peminjam" type="hidden" name="cara_input_peminjam" value="{{ $nilai('cara_input_peminjam', 'manual') }}">

        <div id="status_peminjam" class="scan-status" style="margin-top: 16px;">Pilih manual atau scan kartu peminjam.</div>
        <div id="ringkasan_peminjam" class="borrower-summary" style="margin-top: 12px;">
            <strong>Belum ada peminjam terpilih.</strong>
        </div>
    </section>

    <section class="panel panel-pad">
        <p class="eyebrow">Langkah 2</p>
        <h2 class="panel-title">Masukkan barang</h2>

        <div class="loan-scan-grid" style="margin-top: 16px;">
            <div class="field">
                <label for="kode_barang_scan">Scan barcode barang</label>
                <div class="actions" style="gap: 8px;">
                    <input id="kode_barang_scan" type="text" class="input" placeholder="Scan barcode unit atau kode barang" autocomplete="off">
                    <button id="tombol_scan_barang" type="button" class="button button-dark">Proses</button>
                </div>
            </div>

            <div id="bungkus_lokasi_scan" class="field" hidden>
                <label for="lokasi_barang_scan">Lokasi stok</label>
                <select id="lokasi_barang_scan" class="select">
                    <option value="">Pilih lokasi asal</option>
                </select>
            </div>
        </div>

        <div id="status_barang" class="scan-status" style="margin-top: 12px;">Scanner siap menerima barcode barang.</div>

        <div class="loan-inline-grid" style="margin-top: 20px;">
            <div class="field">
                <label for="barang_manual">Pilih barang manual</label>
                <select id="barang_manual" class="select">
                    <option value="">Pilih unit aset atau stok barang</option>
                    @foreach ($daftarItemManual as $item)
                        <option value="{{ $item['kunci'] }}">{{ $item['label'] }} - {{ $item['keterangan'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="jumlah_barang_manual">Jumlah</label>
                <input id="jumlah_barang_manual" type="number" class="input" min="0.01" step="0.01" value="1">
            </div>

            <button id="tombol_tambah_barang_manual" type="button" class="button button-muted">Tambahkan</button>
        </div>
    </section>

    <section class="panel">
        <div class="panel-pad">
            <p class="eyebrow">Langkah 3</p>
            <h2 class="panel-title">Keranjang transaksi</h2>
        </div>

        <div class="table-wrap">
            <table class="employee-table loan-cart-table">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th>Jenis</th>
                        <th>Jumlah</th>
                        <th>Input</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody id="daftar_barang_dipilih">
                    <tr>
                        <td colspan="5" class="empty-state">Belum ada barang dalam transaksi.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel panel-pad">
        <p class="eyebrow">Langkah 4</p>
        <h2 class="panel-title">Simpan transaksi</h2>

        <div class="form-grid" style="margin-top: 16px;">
            <div class="field">
                <label for="tanggal_peminjaman">Tanggal peminjaman</label>
                <input id="tanggal_peminjaman" name="tanggal_peminjaman" type="date" value="{{ $nilai('tanggal_peminjaman', now()->toDateString()) }}" class="{{ $inputClass('tanggal_peminjaman') }}" required>
            </div>

            <div class="field">
                <label for="rencana_kembali">Rencana kembali</label>
                <input id="rencana_kembali" name="rencana_kembali" type="date" value="{{ $nilai('rencana_kembali') }}" class="{{ $inputClass('rencana_kembali') }}">
            </div>

            <div class="field span-2">
                <label for="catatan">Catatan</label>
                <textarea id="catatan" name="catatan" class="{{ $textareaClass('catatan') }}" placeholder="Tambahkan catatan jika diperlukan.">{{ $nilai('catatan') }}</textarea>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ route('peminjaman-barang.index') }}" class="button button-muted">Batal</a>
        <button type="submit" class="button button-primary">Simpan transaksi</button>
    </div>
</div>

<script>
    (() => {
        const endpointPeminjam = @json(route('peminjaman-barang.identifikasi-peminjam'));
        const endpointBarang = @json(route('peminjaman-barang.identifikasi-barang'));
        const daftarItemManual = @json($daftarItemManual);
        const itemTersimpan = @json(old('items', []));
        const petaItemManual = new Map(daftarItemManual.map((item) => [item.kunci, item]));
        const daftarItem = new Map();
        let antrianScanBarang = Promise.resolve();

        const jenisPeminjam = document.getElementById('jenis_peminjam');
        const siswa = document.getElementById('siswa_id');
        const pegawai = document.getElementById('pegawai_id');
        const caraInputPeminjam = document.getElementById('cara_input_peminjam');
        const ringkasanPeminjam = document.getElementById('ringkasan_peminjam');
        const statusPeminjam = document.getElementById('status_peminjam');
        const kodePeminjamScan = document.getElementById('kode_peminjam_scan');
        const kodeBarangScan = document.getElementById('kode_barang_scan');
        const statusBarang = document.getElementById('status_barang');
        const lokasiBarangScan = document.getElementById('lokasi_barang_scan');
        const bungkusLokasiScan = document.getElementById('bungkus_lokasi_scan');
        const barangManual = document.getElementById('barang_manual');
        const jumlahBarangManual = document.getElementById('jumlah_barang_manual');
        const daftarBarangDipilih = document.getElementById('daftar_barang_dipilih');

        const teksAman = (nilai) => String(nilai ?? '');

        const ubahStatus = (elemen, pesan, jenis = '') => {
            elemen.textContent = pesan;
            elemen.className = `scan-status ${jenis}`.trim();
        };

        const perbaruiJenisPeminjam = () => {
            const siswaAktif = jenisPeminjam.value === 'siswa';
            document.getElementById('bungkus_siswa').hidden = !siswaAktif;
            document.getElementById('bungkus_pegawai').hidden = siswaAktif;
        };

        const tampilkanRingkasanManual = () => {
            const pilihan = jenisPeminjam.value === 'siswa' ? siswa : pegawai;
            const opsi = pilihan.selectedOptions[0];

            if (!opsi?.value) {
                ringkasanPeminjam.innerHTML = '<strong>Belum ada peminjam terpilih.</strong>';
                return;
            }

            caraInputPeminjam.value = 'manual';
            ringkasanPeminjam.innerHTML = '';
            const nama = document.createElement('strong');
            nama.textContent = opsi.textContent;
            ringkasanPeminjam.appendChild(nama);
            ringkasanPeminjam.appendChild(document.createElement('br'));
            ringkasanPeminjam.appendChild(document.createTextNode('Dipilih secara manual.'));
            ubahStatus(statusPeminjam, 'Peminjam manual siap digunakan.', 'success');
        };

        const prosesScanPeminjam = async () => {
            const kode = kodePeminjamScan.value.trim();

            if (!kode) {
                return;
            }

            ubahStatus(statusPeminjam, 'Mencari data peminjam...');

            try {
                const url = new URL(endpointPeminjam);
                url.searchParams.set('jenis_peminjam', jenisPeminjam.value);
                url.searchParams.set('kode', kode);
                const respons = await fetch(url, { headers: { Accept: 'application/json' } });
                const data = await respons.json();

                if (!respons.ok) {
                    throw new Error(data.pesan || 'Peminjam tidak ditemukan.');
                }

                const pilihan = data.jenis_peminjam === 'siswa' ? siswa : pegawai;
                pilihan.value = String(data.id);
                caraInputPeminjam.value = 'scan';
                ringkasanPeminjam.innerHTML = '';

                [data.nama, data.identitas, data.informasi].forEach((teks, index) => {
                    const baris = document.createElement(index === 0 ? 'strong' : 'span');
                    baris.textContent = teksAman(teks);
                    ringkasanPeminjam.appendChild(baris);
                    ringkasanPeminjam.appendChild(document.createElement('br'));
                });

                ubahStatus(statusPeminjam, 'Kartu peminjam berhasil dibaca.', 'success');
                kodePeminjamScan.value = '';
            } catch (error) {
                ubahStatus(statusPeminjam, error.message, 'error');
            }
        };

        const buatKunciItem = (item) => item.kunci || (item.tipe_item === 'unit'
            ? `unit:${item.unit_barang_id}`
            : `stok:${item.barang_id}:${item.lokasi_barang_id}`);

        const tambahItem = (itemBaru, caraInput, jumlah = 1) => {
            const item = { ...itemBaru };
            const kunci = buatKunciItem(item);
            item.kunci = kunci;
            item.jumlah = item.tipe_item === 'unit' ? 1 : Number(jumlah || 1);
            item.cara_input_barang = caraInput;
            item.label = item.label || 'Barang';
            item.keterangan = item.keterangan || '';
            item.satuan = item.satuan || (item.tipe_item === 'unit' ? 'unit' : '');

            if (daftarItem.has(kunci)) {
                const tersimpan = daftarItem.get(kunci);

                if (item.tipe_item === 'unit') {
                    ubahStatus(statusBarang, 'Unit aset tersebut sudah ada di keranjang.', 'error');
                    return;
                }

                tersimpan.jumlah = Number(tersimpan.jumlah) + Number(item.jumlah);
                tersimpan.cara_input_barang = tersimpan.cara_input_barang === caraInput ? caraInput : 'campuran';
                daftarItem.set(kunci, tersimpan);
            } else {
                daftarItem.set(kunci, item);
            }

            renderKeranjang();
            ubahStatus(statusBarang, `${item.label} masuk ke keranjang.`, 'success');
        };

        const buatInputTersembunyi = (nama, nilai) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = nama;
            input.value = nilai ?? '';
            return input;
        };

        const renderKeranjang = () => {
            daftarBarangDipilih.innerHTML = '';

            if (daftarItem.size === 0) {
                const baris = document.createElement('tr');
                const kolom = document.createElement('td');
                kolom.colSpan = 5;
                kolom.className = 'empty-state';
                kolom.textContent = 'Belum ada barang dalam transaksi.';
                baris.appendChild(kolom);
                daftarBarangDipilih.appendChild(baris);
                return;
            }

            Array.from(daftarItem.values()).forEach((item, index) => {
                const baris = document.createElement('tr');
                const kolomBarang = document.createElement('td');
                const nama = document.createElement('p');
                nama.className = 'person-name';
                nama.textContent = item.label;
                const info = document.createElement('p');
                info.className = 'person-meta';
                info.textContent = item.keterangan;
                kolomBarang.append(nama, info);

                [
                    ['tipe_item', item.tipe_item],
                    ['unit_barang_id', item.unit_barang_id],
                    ['barang_id', item.barang_id],
                    ['lokasi_barang_id', item.lokasi_barang_id],
                    ['jumlah', item.jumlah],
                    ['cara_input_barang', item.cara_input_barang],
                    ['label', item.label],
                ].forEach(([field, nilai]) => kolomBarang.appendChild(buatInputTersembunyi(`items[${index}][${field}]`, nilai)));

                const kolomJenis = document.createElement('td');
                kolomJenis.textContent = item.tipe_item === 'unit' ? 'Unit aset' : 'Stok barang';
                const kolomJumlah = document.createElement('td');
                kolomJumlah.textContent = `${Number(item.jumlah).toLocaleString('id-ID', { maximumFractionDigits: 2 })} ${item.satuan}`;
                const kolomInput = document.createElement('td');
                kolomInput.textContent = item.cara_input_barang;
                const kolomAksi = document.createElement('td');
                kolomAksi.className = 'text-right';
                const tombolHapus = document.createElement('button');
                tombolHapus.type = 'button';
                tombolHapus.className = 'button button-danger';
                tombolHapus.textContent = 'Hapus';
                tombolHapus.addEventListener('click', () => {
                    daftarItem.delete(item.kunci);
                    renderKeranjang();
                });
                kolomAksi.appendChild(tombolHapus);
                baris.append(kolomBarang, kolomJenis, kolomJumlah, kolomInput, kolomAksi);
                daftarBarangDipilih.appendChild(baris);
            });
        };

        const prosesScanBarang = async (kode) => {
            ubahStatus(statusBarang, `Memproses barcode ${kode}...`);

            try {
                const url = new URL(endpointBarang);
                url.searchParams.set('kode', kode);

                if (lokasiBarangScan.value) {
                    url.searchParams.set('lokasi_barang_id', lokasiBarangScan.value);
                }

                const respons = await fetch(url, { headers: { Accept: 'application/json' } });
                const data = await respons.json();

                if (!respons.ok) {
                    throw new Error(data.pesan || 'Barang tidak ditemukan.');
                }

                if (data.perlu_pilih_lokasi) {
                    lokasiBarangScan.innerHTML = '<option value="">Pilih lokasi asal</option>';
                    data.pilihan_lokasi.forEach((lokasi) => {
                        const opsi = document.createElement('option');
                        opsi.value = lokasi.id;
                        opsi.textContent = `${lokasi.nama} - ${lokasi.saldo} ${lokasi.satuan}`;
                        lokasiBarangScan.appendChild(opsi);
                    });
                    bungkusLokasiScan.hidden = false;
                    kodeBarangScan.value = kode;
                    lokasiBarangScan.focus();
                    ubahStatus(statusBarang, data.pesan);
                    return;
                }

                tambahItem(data.item, 'scan', 1);
                kodeBarangScan.value = '';
                lokasiBarangScan.value = '';
                bungkusLokasiScan.hidden = true;
            } catch (error) {
                ubahStatus(statusBarang, error.message, 'error');
            }
        };

        const masukkanScanBarangKeAntrean = () => {
            const kode = kodeBarangScan.value.trim();

            if (!kode) {
                return;
            }

            kodeBarangScan.value = '';
            antrianScanBarang = antrianScanBarang.then(() => prosesScanBarang(kode));
        };

        jenisPeminjam.addEventListener('change', () => {
            perbaruiJenisPeminjam();
            tampilkanRingkasanManual();
        });
        siswa.addEventListener('change', tampilkanRingkasanManual);
        pegawai.addEventListener('change', tampilkanRingkasanManual);
        document.getElementById('tombol_scan_peminjam').addEventListener('click', prosesScanPeminjam);
        kodePeminjamScan.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                prosesScanPeminjam();
            }
        });
        document.getElementById('tombol_scan_barang').addEventListener('click', masukkanScanBarangKeAntrean);
        kodeBarangScan.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                masukkanScanBarangKeAntrean();
            }
        });
        lokasiBarangScan.addEventListener('change', () => {
            if (lokasiBarangScan.value && kodeBarangScan.value.trim()) {
                masukkanScanBarangKeAntrean();
            }
        });
        document.getElementById('tombol_tambah_barang_manual').addEventListener('click', () => {
            const item = petaItemManual.get(barangManual.value);

            if (!item) {
                ubahStatus(statusBarang, 'Pilih barang manual terlebih dahulu.', 'error');
                return;
            }

            tambahItem(item, 'manual', jumlahBarangManual.value);
            barangManual.value = '';
            jumlahBarangManual.value = '1';
        });

        itemTersimpan.forEach((item) => tambahItem(item, item.cara_input_barang || 'manual', item.jumlah || 1));
        perbaruiJenisPeminjam();
        tampilkanRingkasanManual();
        renderKeranjang();
    })();
</script>
