<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan Inventaris {{ $labelPeriode }} - NUSA</title>
    <style>
        :root {
            --primary: #15477a;
            --accent: #f1c40f;
            --line: #cbd5e1;
            --muted: #475569;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e2e8f0;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            letter-spacing: 0;
        }

        .print-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            width: min(1120px, calc(100% - 32px));
            margin: 18px auto;
            border-radius: 8px;
            background: #fff;
            padding: 14px 16px;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .button {
            display: inline-flex;
            min-height: 40px;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 9px 13px;
            color: var(--primary);
            cursor: pointer;
            font: inherit;
            font-weight: 800;
            text-decoration: none;
        }

        .button-primary {
            border-color: var(--primary);
            background: var(--primary);
            color: #fff;
        }

        .report-page {
            width: min(1120px, calc(100% - 32px));
            min-height: 190mm;
            margin: 0 auto 18px;
            border-top: 4px solid var(--primary);
            border-bottom: 3px solid var(--accent);
            background: #fff;
            padding: 22px;
        }

        .report-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 12px;
        }

        h1,
        h2,
        p {
            margin: 0;
        }

        h1 {
            color: var(--primary);
            font-size: 20px;
        }

        h2 {
            margin: 18px 0 8px;
            color: var(--primary);
            font-size: 15px;
        }

        .muted {
            color: var(--muted);
            font-size: 11px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 8px;
            margin: 14px 0;
        }

        .summary div {
            border: 1px solid var(--line);
            padding: 8px;
        }

        .summary span {
            display: block;
            color: var(--muted);
            font-size: 9px;
            text-transform: uppercase;
        }

        .summary strong {
            display: block;
            margin-top: 3px;
            font-size: 17px;
        }

        .summary.service-summary {
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        th,
        td {
            border: 1px solid var(--line);
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #eff6ff;
            color: var(--primary);
            font-size: 9px;
            text-transform: uppercase;
        }

        .warning {
            background: #fff7ed;
        }

        .danger {
            background: #fff1f2;
        }

        .asset-status {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 9px;
        }

        .asset-status span {
            border: 1px solid var(--line);
            padding: 5px 7px;
            font-size: 10px;
        }

        .note {
            margin-top: 9px;
            color: var(--muted);
            font-size: 10px;
            line-height: 1.45;
        }

        .signatures {
            margin-top: 24px;
            text-align: center;
            font-size: 11px;
        }

        .signature-row {
            display: grid;
            grid-template-columns: repeat(2, 230px);
            justify-content: space-between;
            gap: 24px;
        }

        .signature-row-head {
            display: flex;
            justify-content: center;
            margin-top: 18px;
        }

        .signature-block {
            width: 230px;
        }

        .signature-space {
            height: 58px;
        }

        .signature-name {
            font-weight: 700;
            text-decoration: underline;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 9mm;
            }

            body {
                background: #fff;
            }

            .print-toolbar {
                display: none;
            }

            .report-page {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                border-top-width: 3px;
            }

            .page-break {
                break-before: page;
            }

            tr {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    @php
        $formatJumlah = fn ($nilai) => number_format((float) $nilai, 2, ',', '.');
    @endphp

    <div class="print-toolbar">
        <div>
            <strong>Laporan inventaris bulanan siap dicetak</strong>
            <p class="muted">Periksa kembali periode dan lokasi sebelum mencetak.</p>
        </div>
        <div class="actions">
            <a href="{{ route('laporan-inventaris-bulanan.index', request()->query()) }}" class="button">Kembali</a>
            <button type="button" class="button button-primary" onclick="window.print()">Cetak</button>
        </div>
    </div>

    <main class="report-page">
        <header class="report-head">
            <div>
                <h1>LAPORAN INVENTARIS BULANAN</h1>
                <p><strong>SMP Negeri 2 Padang Panjang</strong></p>
                <p class="muted">Periode: {{ $labelPeriode }} | Lokasi: {{ $lokasiBarang?->nama ?: 'Semua lokasi' }}</p>
            </div>
            <p class="muted">Dicetak: {{ $tanggalCetak }}</p>
        </header>

        <section class="summary">
            <div><span>Baris stok</span><strong>{{ $ringkasan['baris_stok'] }}</strong></div>
            <div><span>Mutasi periode</span><strong>{{ $ringkasan['jumlah_mutasi'] }}</strong></div>
            <div><span>Stok menipis</span><strong>{{ $ringkasan['stok_menipis'] }}</strong></div>
            <div><span>Stok habis</span><strong>{{ $ringkasan['stok_habis'] }}</strong></div>
            <div><span>Unit aset aktif</span><strong>{{ $ringkasan['unit_aset'] }}</strong></div>
            <div><span>Unit perhatian</span><strong>{{ $ringkasan['unit_perlu_perhatian'] }}</strong></div>
        </section>

        <h2>REKAP STOK BULANAN</h2>
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Barang</th>
                    <th>Lokasi</th>
                    <th>Saldo awal</th>
                    <th>Masuk</th>
                    <th>Keluar</th>
                    <th>Penyesuaian</th>
                    <th>Saldo akhir</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rekapStok as $index => $item)
                    @php
                        $saldo = $item['saldo'];
                        $satuan = $saldo->barang->satuanBarang->nama;
                    @endphp
                    <tr class="{{ $item['status'] === 'Habis' ? 'danger' : ($item['status'] === 'Menipis' ? 'warning' : '') }}">
                        <td>{{ $index + 1 }}</td>
                        <td><strong>{{ $saldo->barang->nama }}</strong><br><span class="muted">{{ $saldo->barang->kode }}</span></td>
                        <td>{{ $saldo->lokasiBarang->nama }}</td>
                        <td>{{ $formatJumlah($item['saldo_awal']) }} {{ $satuan }}</td>
                        <td>{{ $formatJumlah($item['stok_masuk']) }} {{ $satuan }}</td>
                        <td>{{ $formatJumlah($item['stok_keluar']) }} {{ $satuan }}</td>
                        <td>{{ $item['penyesuaian'] > 0 ? '+' : '' }}{{ $formatJumlah($item['penyesuaian']) }} {{ $satuan }}</td>
                        <td><strong>{{ $formatJumlah($item['saldo_akhir']) }} {{ $satuan }}</strong></td>
                        <td>{{ $item['status'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="text-align: center;">Belum ada saldo stok pada pilihan laporan ini.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($barangStokBelumDicatat->isNotEmpty())
            <p class="note"><strong>Perhatian:</strong> {{ $barangStokBelumDicatat->pluck('nama')->implode(', ') }} belum memiliki saldo awal.</p>
        @endif

        <h2>SNAPSHOT UNIT ASET</h2>
        <div class="asset-status">
            @foreach ($distribusiStatusUnit as $item)
                <span><strong>{{ $item['label'] }}:</strong> {{ $item['jumlah'] }}</span>
            @endforeach
        </div>

        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Kode inventaris</th>
                    <th>Barang</th>
                    <th>Lokasi</th>
                    <th>Kondisi</th>
                    <th>Status unit</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($unitPerluPerhatian as $index => $item)
                    <tr class="warning">
                        <td>{{ $index + 1 }}</td>
                        <td><strong>{{ $item->kode_inventaris }}</strong></td>
                        <td>{{ $item->barang->nama }}</td>
                        <td>{{ $item->lokasiBarang?->nama ?: '-' }}</td>
                        <td>{{ $item->labelKondisi() }}</td>
                        <td>{{ $item->labelStatus() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align: center;">Tidak ada unit aset yang perlu perhatian.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="note">Kondisi unit aset merupakan snapshot saat laporan dicetak. Rekap stok dihitung dari ledger mutasi selama periode terpilih.</p>
    </main>

    <section class="report-page page-break">
        <header class="report-head">
            <div>
                <h1>RINCIAN MUTASI STOK &amp; LAYANAN BARANG</h1>
                <p><strong>SMP Negeri 2 Padang Panjang</strong></p>
                <p class="muted">Periode: {{ $labelPeriode }} | Lokasi: {{ $lokasiBarang?->nama ?: 'Semua lokasi' }}</p>
            </div>
            <p class="muted">{{ $ringkasanLayananPegawai['jumlah_layanan'] }} layanan pegawai</p>
        </header>

        <section class="summary service-summary">
            <div><span>Total layanan</span><strong>{{ $ringkasanLayananPegawai['jumlah_layanan'] }}</strong></div>
            <div><span>Pegawai dilayani</span><strong>{{ $ringkasanLayananPegawai['pegawai_dilayani'] }}</strong></div>
            <div><span>Peminjaman aset</span><strong>{{ $ringkasanLayananPegawai['peminjaman_aset'] }}</strong></div>
            <div><span>Barang habis pakai</span><strong>{{ $ringkasanLayananPegawai['penyerahan_habis_pakai'] }}</strong></div>
            <div><span>Masih dipinjam</span><strong>{{ $ringkasanLayananPegawai['pinjaman_aktif'] }}</strong></div>
        </section>

        <h2>LAYANAN BARANG PEGAWAI</h2>
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Tanggal</th>
                    <th>Pegawai</th>
                    <th>Barang</th>
                    <th>Rencana kembali</th>
                    <th>Status</th>
                    <th>Sumber</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($layananBarangPegawai as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item->tanggal_peminjaman->locale('id')->translatedFormat('d M Y') }}<br><span class="muted">{{ $item->nomor_peminjaman }}</span></td>
                        <td><strong>{{ $item->pegawai?->nama_lengkap ?: 'Pegawai tidak ditemukan' }}</strong><br><span class="muted">NIP {{ $item->pegawai?->nip ?: '-' }}</span></td>
                        <td>
                            @foreach ($item->detailPeminjamanBarang as $detail)
                                @php $satuanLayanan = $detail->tipe_pengelolaan === 'aset_individual' ? 'unit' : $detail->barang->satuanBarang->nama; @endphp
                                <strong>{{ $detail->barang->nama }}</strong> - {{ $formatJumlah($detail->jumlah) }} {{ $satuanLayanan }}
                                @if ($detail->unitBarang) ({{ $detail->unitBarang->kode_inventaris }}) @endif
                                @if (! $loop->last)<br>@endif
                            @endforeach
                        </td>
                        <td>{{ $item->rencana_kembali?->locale('id')->translatedFormat('d M Y') ?: '-' }}</td>
                        <td>{{ $item->labelStatus() }}</td>
                        <td>{{ $item->pengajuanBarang->first()?->nomor_pengajuan ?: 'Dicatat petugas' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align: center;">Belum ada layanan barang kepada pegawai pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>TRANSAKSI PERIODE</h2>
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Tanggal</th>
                    <th>Barang</th>
                    <th>Lokasi</th>
                    <th>Jenis</th>
                    <th>Kategori</th>
                    <th>Perubahan</th>
                    <th>Saldo akhir</th>
                    <th>Referensi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($mutasiPeriode as $index => $item)
                    @php
                        $perubahan = (float) $item->jumlah_perubahan;
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item->tanggal_mutasi->locale('id')->translatedFormat('d M Y') }}</td>
                        <td><strong>{{ $item->barang->nama }}</strong></td>
                        <td>{{ $item->lokasiBarang->nama }}</td>
                        <td>{{ $item->labelJenis() }}</td>
                        <td>{{ $item->labelKategori() }}</td>
                        <td>{{ $perubahan > 0 ? '+' : '' }}{{ $formatJumlah($perubahan) }} {{ $item->barang->satuanBarang->nama }}</td>
                        <td>{{ $formatJumlah($item->saldo_sesudah) }} {{ $item->barang->satuanBarang->nama }}</td>
                        <td>{{ $item->referensi ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="text-align: center;">Belum ada mutasi stok pada periode terpilih.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="signatures">
            <div class="signature-row">
                <div class="signature-block">
                    <p>Mengetahui,</p>
                    <p>Wakil Kepala Sekolah Bidang Sarpras</p>
                    <div class="signature-space"></div>
                    <p class="signature-name">{{ $penandatangan['wakil_sarpras']?->nama_lengkap ?: 'Belum ditentukan' }}</p>
                    <p>NIP. {{ $penandatangan['wakil_sarpras']?->nip ?: '-' }}</p>
                </div>
                <div class="signature-block">
                    <p>Padang Panjang, ................................</p>
                    <p>Petugas Inventaris</p>
                    <div class="signature-space"></div>
                    <p class="signature-name">{{ $penandatangan['petugas_inventaris']?->nama_lengkap ?: 'Belum ditentukan' }}</p>
                    <p>NIP. {{ $penandatangan['petugas_inventaris']?->nip ?: '-' }}</p>
                </div>
            </div>
            <div class="signature-row-head">
                <div class="signature-block">
                    <p>Mengetahui,</p>
                    <p>Kepala SMP Negeri 2 Padang Panjang</p>
                    <div class="signature-space"></div>
                    <p class="signature-name">{{ $penandatangan['kepala_sekolah']?->nama_lengkap ?: 'Belum ditentukan' }}</p>
                    <p>NIP. {{ $penandatangan['kepala_sekolah']?->nip ?: '-' }}</p>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
