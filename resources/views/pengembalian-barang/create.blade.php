@extends('layouts.app')

@section('title', 'Catat Pengembalian Barang - NUSA')

@section('content')
    <style>
        .return-scan-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: end;
        }

        .return-status {
            min-height: 42px;
            border-left: 4px solid #15477A;
            background: #eff6ff;
            padding: 10px 12px;
            color: #15477A;
            font-size: .86rem;
            font-weight: 800;
        }

        .return-status.error {
            border-left-color: #b42318;
            background: #fef3f2;
            color: #b42318;
        }

        .return-status.success {
            border-left-color: #18864b;
            background: #ecfdf3;
            color: #166534;
        }

        @media (max-width: 620px) {
            .return-scan-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Catat pengembalian barang</h1>
        </div>

        <a href="{{ route('peminjaman-barang.show', $peminjamanBarang) }}" class="button button-muted">Kembali</a>
    </div>

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

    <section class="panel panel-pad" style="margin-bottom: 18px;">
        <p class="eyebrow">{{ $peminjamanBarang->nomor_peminjaman }}</p>
        <h2 class="panel-title">{{ $peminjamanBarang->namaPeminjam() }}</h2>
        <p class="help-text" style="margin-top: 8px;">{{ $peminjamanBarang->identitasPeminjam() }}. Centang barang secara manual atau scan barcode untuk memilihnya.</p>
    </section>

    <form action="{{ route('pengembalian-barang.store', $peminjamanBarang) }}" method="POST">
        @csrf

        <section class="panel panel-pad">
            <div class="return-scan-grid">
                <div class="field">
                    <label for="kode_pengembalian_scan">Scan barcode barang</label>
                    <input id="kode_pengembalian_scan" type="text" class="input" placeholder="Scan barcode unit atau kode barang" autocomplete="off">
                </div>
                <button id="tombol_scan_pengembalian" type="button" class="button button-dark">Proses</button>
            </div>
            <div id="status_pengembalian" class="return-status" style="margin-top: 12px;">Scanner siap menerima barcode barang.</div>
        </section>

        <section class="panel" style="margin-top: 18px;">
            <div class="table-wrap">
                <table class="employee-table">
                    <thead>
                        <tr>
                            <th>Pilih</th>
                            <th>Barang</th>
                            <th>Sisa</th>
                            <th>Dikembalikan</th>
                            <th>Kondisi unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($peminjamanBarang->detailPeminjamanBarang as $detail)
                            @php
                                $nilaiLama = old('items.' . $detail->id, []);
                                $terpilih = array_key_exists($detail->id, old('items', []));
                                $kodeScan = $detail->unitBarang?->kode_inventaris ?: $detail->barang->kode;
                                $satuan = $detail->tipe_pengelolaan === 'aset_individual' ? 'unit' : $detail->barang->satuanBarang->nama;
                            @endphp
                            <tr data-return-row data-kode="{{ $kodeScan }}" data-detail-id="{{ $detail->id }}">
                                <td>
                                    <input type="checkbox" data-return-check @checked($terpilih)>
                                </td>
                                <td>
                                    <p class="person-name">{{ $detail->barang->nama }}</p>
                                    <p class="person-meta">{{ $kodeScan }} - {{ $detail->lokasiBarang?->nama ?: 'Tanpa lokasi' }}</p>
                                    <input type="hidden" name="items[{{ $detail->id }}][detail_peminjaman_barang_id]" value="{{ $detail->id }}" data-return-field @disabled(! $terpilih)>
                                    <input type="hidden" name="items[{{ $detail->id }}][cara_input_barang]" value="{{ $nilaiLama['cara_input_barang'] ?? 'manual' }}" data-return-method data-return-field @disabled(! $terpilih)>
                                </td>
                                <td>{{ number_format($detail->jumlahBelumDikembalikan(), 2, ',', '.') }} {{ $satuan }}</td>
                                <td>
                                    <input
                                        type="number"
                                        name="items[{{ $detail->id }}][jumlah]"
                                        value="{{ $nilaiLama['jumlah'] ?? ($detail->tipe_pengelolaan === 'aset_individual' ? 1 : $detail->jumlahBelumDikembalikan()) }}"
                                        min="0.01"
                                        max="{{ $detail->jumlahBelumDikembalikan() }}"
                                        step="0.01"
                                        class="input"
                                        data-return-field
                                        @readonly($detail->tipe_pengelolaan === 'aset_individual')
                                        @disabled(! $terpilih)
                                    >
                                </td>
                                <td>
                                    @if ($detail->tipe_pengelolaan === 'aset_individual')
                                        <select name="items[{{ $detail->id }}][kondisi_pengembalian]" class="select" data-return-field @disabled(! $terpilih)>
                                            @foreach ($daftarKondisi as $nilai => $label)
                                                <option value="{{ $nilai }}" @selected(($nilaiLama['kondisi_pengembalian'] ?? 'baik') === $nilai)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="person-meta">Tidak diperlukan</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel panel-pad" style="margin-top: 18px;">
            <div class="form-grid">
                <div class="field">
                    <label for="tanggal_pengembalian">Tanggal pengembalian</label>
                    <input id="tanggal_pengembalian" name="tanggal_pengembalian" type="date" value="{{ old('tanggal_pengembalian', now()->toDateString()) }}" class="input" required>
                </div>
                <div class="field span-2">
                    <label for="catatan">Catatan</label>
                    <textarea id="catatan" name="catatan" class="textarea" placeholder="Tambahkan catatan jika diperlukan.">{{ old('catatan') }}</textarea>
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('peminjaman-barang.show', $peminjamanBarang) }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">Simpan pengembalian</button>
        </div>
    </form>

    <script>
        (() => {
            const masukanScan = document.getElementById('kode_pengembalian_scan');
            const status = document.getElementById('status_pengembalian');
            const semuaBaris = Array.from(document.querySelectorAll('[data-return-row]'));
            let antrean = Promise.resolve();

            const ubahStatus = (pesan, jenis = '') => {
                status.textContent = pesan;
                status.className = `return-status ${jenis}`.trim();
            };

            const aktifkanBaris = (baris, caraInput = 'manual') => {
                const checkbox = baris.querySelector('[data-return-check]');
                checkbox.checked = true;
                baris.querySelectorAll('[data-return-field]').forEach((input) => {
                    input.disabled = false;
                });
                baris.querySelector('[data-return-method]').value = caraInput;
            };

            semuaBaris.forEach((baris) => {
                const checkbox = baris.querySelector('[data-return-check]');
                checkbox.addEventListener('change', () => {
                    baris.querySelectorAll('[data-return-field]').forEach((input) => {
                        input.disabled = !checkbox.checked;
                    });

                    if (checkbox.checked) {
                        baris.querySelector('[data-return-method]').value = 'manual';
                    }
                });
            });

            const prosesScan = async (kode) => {
                const ditemukan = semuaBaris.filter((baris) => baris.dataset.kode === kode);

                if (ditemukan.length === 0) {
                    ubahStatus('Barcode tidak termasuk barang yang masih perlu dikembalikan.', 'error');
                    return;
                }

                if (ditemukan.length > 1) {
                    ubahStatus('Kode barang memiliki beberapa baris peminjaman. Pilih baris yang sesuai secara manual.', 'error');
                    return;
                }

                aktifkanBaris(ditemukan[0], 'scan');
                ubahStatus('Barang berhasil dipilih dari hasil scan.', 'success');
            };

            const masukkanKeAntrean = () => {
                const kode = masukanScan.value.trim();

                if (!kode) {
                    return;
                }

                masukanScan.value = '';
                antrean = antrean.then(() => prosesScan(kode));
            };

            document.getElementById('tombol_scan_pengembalian').addEventListener('click', masukkanKeAntrean);
            masukanScan.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    masukkanKeAntrean();
                }
            });
        })();
    </script>
@endsection
