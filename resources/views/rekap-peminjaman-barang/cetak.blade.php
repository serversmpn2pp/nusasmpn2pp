<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Rekap Peminjaman Barang - NUSA</title>
    <style>
        :root {
            --primary: #15477a;
            --accent: #f1c40f;
            --line: #cbd5e1;
            --muted: #475569;
            --danger: #b42318;
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
            padding: 24px;
        }

        .report-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 14px;
        }

        h1 {
            margin: 0;
            color: var(--primary);
            font-size: 20px;
        }

        p {
            margin: 4px 0 0;
        }

        .muted {
            color: var(--muted);
            font-size: 13px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin: 16px 0;
        }

        .summary div {
            border: 1px solid var(--line);
            padding: 10px;
        }

        .summary span {
            display: block;
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
        }

        .summary strong {
            display: block;
            margin-top: 3px;
            font-size: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        th,
        td {
            border: 1px solid var(--line);
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #eff6ff;
            color: var(--primary);
            font-size: 11px;
            text-transform: uppercase;
        }

        .overdue {
            background: #fff1f2;
        }

        .danger {
            color: var(--danger);
            font-weight: 800;
        }

        .report-foot {
            margin-top: 18px;
            color: var(--muted);
            font-size: 11px;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm;
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
                box-shadow: none;
            }

            tr {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <div>
            <strong>Rekap peminjaman barang siap dicetak</strong>
            <p class="muted">Periksa kembali filter sebelum mencetak.</p>
        </div>
        <div class="actions">
            <a href="{{ route('rekap-peminjaman-barang.index', request()->query()) }}" class="button">Kembali</a>
            <button type="button" class="button button-primary" onclick="window.print()">Cetak</button>
        </div>
    </div>

    <main class="report-page">
        <header class="report-head">
            <div>
                <h1>REKAP PEMINJAMAN BARANG</h1>
                <p><strong>SMP Negeri 2 Padang Panjang</strong></p>
                <p class="muted">Pemantauan: {{ $daftarStatusPemantauan[$status_pemantauan] }}</p>
            </div>
            <div class="muted">
                Dicetak: {{ $tanggalCetak }}
            </div>
        </header>

        <section class="summary">
            <div><span>Masih dipinjam</span><strong>{{ $jumlahAktif }}</strong></div>
            <div><span>Terlambat</span><strong>{{ $jumlahTerlambat }}</strong></div>
            <div><span>Jatuh tempo 7 hari</span><strong>{{ $jumlahJatuhTempo }}</strong></div>
            <div><span>Tanpa rencana kembali</span><strong>{{ $jumlahTanpaRencana }}</strong></div>
        </section>

        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Transaksi</th>
                    <th>Peminjam</th>
                    <th>Barang belum kembali</th>
                    <th>Rencana kembali</th>
                    <th>Pemantauan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($peminjamanBarang as $index => $item)
                    @php
                        $terlambat = $item->terlambat();
                        $detailBelumKembali = $item->detailPeminjamanBarang
                            ->filter(fn ($detail) => $detail->wajib_dikembalikan && $detail->jumlahBelumDikembalikan() > 0);
                    @endphp
                    <tr class="{{ $terlambat ? 'overdue' : '' }}">
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $item->nomor_peminjaman }}</strong><br>
                            <span class="muted">{{ $item->tanggal_peminjaman->locale('id')->translatedFormat('d M Y') }}</span>
                        </td>
                        <td>
                            <strong>{{ $item->namaPeminjam() }}</strong><br>
                            <span class="muted">{{ $item->identitasPeminjam() }}</span>
                        </td>
                        <td>
                            @forelse ($detailBelumKembali as $detail)
                                @php
                                    $satuan = $detail->tipe_pengelolaan === 'aset_individual' ? 'unit' : $detail->barang->satuanBarang->nama;
                                @endphp
                                <div>{{ $detail->barang->nama }}: {{ number_format($detail->jumlahBelumDikembalikan(), 2, ',', '.') }} {{ $satuan }}</div>
                            @empty
                                <span class="muted">Tidak ada barang yang perlu kembali.</span>
                            @endforelse
                        </td>
                        <td>{{ $item->rencana_kembali?->locale('id')->translatedFormat('d M Y') ?: '-' }}</td>
                        <td class="{{ $terlambat ? 'danger' : '' }}">{{ $item->labelPemantauan() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 18px; text-align: center;">Belum ada transaksi pada pilihan rekap ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <p class="report-foot">Dokumen ini dihasilkan oleh NUSA SMP Negeri 2 Padang Panjang.</p>
    </main>
</body>
</html>
