@extends('layouts.app')

@section('title', 'Pengembalian Barang - NUSA')

@section('content')
    <style>
        .return-start-shell {
            width: min(900px, 100%);
            margin-inline: auto;
        }

        .return-start-panel {
            border-top: 4px solid #F1C40F;
        }

        .return-start-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: end;
        }

        .return-start-status {
            min-height: 46px;
            margin-top: 14px;
            border-left: 4px solid #15477A;
            background: #eff6ff;
            padding: 12px 14px;
            color: #15477A;
            font-size: .88rem;
            font-weight: 800;
        }

        .return-start-status.error {
            border-left-color: #b42318;
            background: #fef3f2;
            color: #b42318;
        }

        .return-start-status.success {
            border-left-color: #18864b;
            background: #ecfdf3;
            color: #166534;
        }

        .return-found-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .return-found-code {
            display: inline-flex;
            min-height: 30px;
            align-items: center;
            border: 1px solid #f1c40f;
            border-radius: 999px;
            background: #fff8d8;
            padding: 5px 10px;
            color: #604900;
            font-size: .8rem;
            font-weight: 900;
        }

        .return-found-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 18px;
        }

        .return-found-item {
            min-width: 0;
            border-top: 1px solid #d8e1eb;
            padding-top: 10px;
        }

        .return-found-item dt {
            color: #64748b;
            font-size: .76rem;
            font-weight: 800;
        }

        .return-found-item dd {
            margin: 4px 0 0;
            overflow-wrap: anywhere;
            color: #0f172a;
            font-weight: 800;
        }

        @media (max-width: 720px) {
            .return-start-grid,
            .return-found-grid {
                grid-template-columns: 1fr;
            }

            .return-start-grid .button {
                width: 100%;
            }

            .return-found-head {
                display: grid;
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Pengembalian barang</h1>
        </div>

        <a href="{{ route('peminjaman-barang.index') }}" class="button button-muted">Daftar peminjaman</a>
    </div>

    <div class="return-start-shell section-stack">
        <section class="panel panel-pad return-start-panel">
            <p class="eyebrow">Scan aset</p>
            <h2 class="panel-title">Temukan transaksi peminjaman</h2>

            <div class="return-start-grid" style="margin-top: 18px;">
                <div class="field">
                    <label for="kode_pengembalian">Barcode aset</label>
                    <input id="kode_pengembalian" type="text" class="input" value="{{ $kodeAwal }}" placeholder="Scan atau masukkan kode AST" autocomplete="off" autofocus>
                </div>
                <button id="tombol_cari_pengembalian" type="button" class="button button-primary">Cari transaksi</button>
            </div>

            <div id="status_pengembalian_awal" class="return-start-status">Scanner siap menerima barcode aset.</div>
        </section>

        <section id="hasil_pengembalian" class="panel panel-pad" hidden aria-live="polite">
            <div class="return-found-head">
                <div>
                    <span id="hasil_kode" class="return-found-code">AST</span>
                    <h2 id="hasil_barang" class="panel-title" style="margin-top: 10px;">Barang</h2>
                    <p id="hasil_aset" class="help-text" style="margin-top: 5px;"></p>
                </div>
                <a id="tombol_konfirmasi" href="#" class="button button-primary">Konfirmasi pengembalian</a>
            </div>

            <dl class="return-found-grid">
                <div class="return-found-item">
                    <dt>Peminjam</dt>
                    <dd id="hasil_peminjam">-</dd>
                    <dd id="hasil_identitas" class="help-text">-</dd>
                </div>
                <div class="return-found-item">
                    <dt>Transaksi</dt>
                    <dd id="hasil_transaksi">-</dd>
                </div>
                <div class="return-found-item">
                    <dt>Lokasi asal</dt>
                    <dd id="hasil_lokasi">-</dd>
                </div>
                <div class="return-found-item">
                    <dt>Tanggal dipinjam</dt>
                    <dd id="hasil_tanggal">-</dd>
                </div>
                <div class="return-found-item">
                    <dt>Rencana kembali</dt>
                    <dd id="hasil_rencana">-</dd>
                </div>
                <div class="return-found-item">
                    <dt>Kondisi tercatat</dt>
                    <dd id="hasil_kondisi">-</dd>
                </div>
            </dl>
        </section>
    </div>

    <script>
        (() => {
            const endpoint = @json(route('pengembalian-barang.identifikasi'));
            const input = document.getElementById('kode_pengembalian');
            const tombolCari = document.getElementById('tombol_cari_pengembalian');
            const status = document.getElementById('status_pengembalian_awal');
            const hasil = document.getElementById('hasil_pengembalian');
            let antrean = Promise.resolve();

            const ubahStatus = (pesan, jenis = '') => {
                status.textContent = pesan;
                status.className = `return-start-status ${jenis}`.trim();
            };

            const isiTeks = (id, nilai) => {
                document.getElementById(id).textContent = String(nilai ?? '-');
            };

            const tampilkanHasil = (item) => {
                isiTeks('hasil_kode', item.kode);
                isiTeks('hasil_barang', item.nama_barang);
                isiTeks('hasil_aset', `Nomor aset ${item.nomor_aset_resmi}`);
                isiTeks('hasil_peminjam', item.nama_peminjam);
                isiTeks('hasil_identitas', item.identitas_peminjam);
                isiTeks('hasil_transaksi', item.nomor_peminjaman);
                isiTeks('hasil_lokasi', item.lokasi_asal);
                isiTeks('hasil_tanggal', item.tanggal_peminjaman);
                isiTeks('hasil_rencana', item.rencana_kembali);
                isiTeks('hasil_kondisi', item.kondisi_tercatat);
                document.getElementById('tombol_konfirmasi').href = item.url_konfirmasi;
                hasil.hidden = false;
            };

            const prosesKode = async (kode) => {
                hasil.hidden = true;
                ubahStatus(`Mencari transaksi aktif untuk ${kode}...`);
                tombolCari.disabled = true;

                try {
                    const url = new URL(endpoint);
                    url.searchParams.set('kode', kode);
                    const respons = await fetch(url, { headers: { Accept: 'application/json' } });
                    const data = await respons.json();

                    if (!respons.ok) {
                        throw new Error(data.pesan || 'Transaksi peminjaman tidak ditemukan.');
                    }

                    tampilkanHasil(data.item);
                    ubahStatus('Transaksi aktif ditemukan. Periksa peminjam lalu konfirmasi pengembalian.', 'success');
                    input.value = '';
                    document.getElementById('tombol_konfirmasi').focus();
                } catch (error) {
                    ubahStatus(error.message, 'error');
                    input.focus();
                    input.select();
                } finally {
                    tombolCari.disabled = false;
                }
            };

            const masukkanKeAntrean = () => {
                const kode = input.value.trim().toUpperCase();

                if (!kode) {
                    ubahStatus('Scan atau masukkan barcode aset terlebih dahulu.', 'error');
                    input.focus();
                    return;
                }

                input.value = '';
                antrean = antrean.then(() => prosesKode(kode));
            };

            tombolCari.addEventListener('click', masukkanKeAntrean);
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    masukkanKeAntrean();
                }
            });

            if (input.value.trim() !== '') {
                masukkanKeAntrean();
            }
        })();
    </script>
@endsection
