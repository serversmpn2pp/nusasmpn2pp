<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan Presensi Pegawai {{ $labelPeriode }} - NUSA</title>
    <style>
        :root {
            --primary: #15477a;
            --primary-dark: #0e3157;
            --accent: #f1c40f;
            --line: #cbd5e1;
            --muted: #475569;
            --soft: #f8fafc;
            --danger: #9f1239;
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
            width: min(210mm, calc(100% - 32px));
            margin: 18px auto;
            border: 1px solid rgba(21, 71, 122, .14);
            border-radius: 8px;
            background: #fff;
            padding: 14px 16px;
            box-shadow: 0 14px 40px rgba(15, 23, 42, .12);
        }

        .print-toolbar p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            border: 0;
            border-radius: 8px;
            padding: 10px 14px;
            background: var(--primary);
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
        }

        .button-muted {
            border: 1px solid var(--line);
            background: #fff;
            color: var(--primary-dark);
        }

        .report-stack {
            display: grid;
            gap: 12mm;
            padding: 0 16px 20px;
        }

        .report-page {
            display: flex;
            flex-direction: column;
            width: 210mm;
            min-height: 277mm;
            margin: 0 auto;
            border-top: 3mm solid var(--primary);
            border-bottom: 1.2mm solid var(--accent);
            background: #fff;
            padding: 8mm 10mm 7mm;
            box-shadow: 0 18px 48px rgba(15, 23, 42, .16);
        }

        .report-letterhead {
            display: grid;
            grid-template-columns: 18mm minmax(0, 1fr) 16mm;
            gap: 4mm;
            align-items: center;
            border-bottom: .35mm solid var(--primary);
            padding-bottom: 3mm;
        }

        .report-logo {
            display: grid;
            place-items: center;
            width: 100%;
            height: 18mm;
        }

        .report-logo img {
            display: block;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .school-copy {
            text-align: center;
        }

        .school-copy p {
            margin: 0;
        }

        .school-copy .school-name {
            color: var(--primary-dark);
            font-size: 13pt;
            font-weight: 800;
            line-height: 1.15;
            text-transform: uppercase;
        }

        .school-copy .school-app {
            margin-top: .8mm;
            color: var(--muted);
            font-size: 8.6pt;
            font-weight: 700;
        }

        .report-title {
            margin: 4mm 0 3mm;
            text-align: center;
        }

        .report-title h1 {
            margin: 0;
            color: var(--primary-dark);
            font-size: 12.5pt;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .report-title p {
            margin: 1mm 0 0;
            font-size: 9pt;
            font-weight: 700;
        }

        .employee-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.5mm 6mm;
            margin-bottom: 3mm;
            border: .25mm solid var(--line);
            border-radius: 2mm;
            background: var(--soft);
            padding: 2.5mm 3mm;
            font-size: 8.3pt;
        }

        .meta-row {
            display: grid;
            grid-template-columns: 25mm minmax(0, 1fr);
            gap: 1.5mm;
            min-width: 0;
        }

        .meta-row span:first-child {
            color: var(--muted);
        }

        .meta-row strong {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7.15pt;
        }

        .attendance-table th,
        .attendance-table td {
            border: .22mm solid #94a3b8;
            padding: 1.15mm 1mm;
            vertical-align: top;
        }

        .attendance-table th {
            background: var(--primary);
            color: #fff;
            font-size: 6.9pt;
            font-weight: 800;
            line-height: 1.15;
            text-align: center;
        }

        .attendance-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .attendance-table td {
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .attendance-table .center {
            text-align: center;
        }

        .attendance-table .status-alfa {
            color: var(--danger);
            font-weight: 800;
        }

        .summary-signature {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 56mm;
            gap: 6mm;
            align-items: start;
            margin-top: auto;
            padding-top: 3.2mm;
        }

        .summary-box {
            border: .25mm solid var(--line);
            border-radius: 2mm;
            overflow: hidden;
        }

        .summary-box h2 {
            margin: 0;
            background: #eaf2fb;
            color: var(--primary-dark);
            padding: 1.5mm 2.2mm;
            font-size: 8.2pt;
            text-transform: uppercase;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1mm 2mm;
            padding: 2mm 2.2mm;
            font-size: 7.4pt;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            gap: 1.5mm;
            border-bottom: .16mm dotted var(--line);
            padding-bottom: .5mm;
        }

        .summary-item strong {
            white-space: nowrap;
        }

        .signature {
            font-size: 8pt;
            line-height: 1.3;
        }

        .signature p {
            margin: 0 0 1.8mm;
        }

        .signature .statement {
            color: #334155;
            font-size: 7.2pt;
        }

        .signature-space {
            height: 18mm;
        }

        .signature-name {
            border-bottom: .25mm solid #111827;
            padding-bottom: .8mm;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .page-note {
            margin-top: 2mm;
            color: var(--muted);
            font-size: 6.6pt;
        }

        .empty-report {
            display: grid;
            place-items: center;
            min-height: 120mm;
            border: .25mm dashed var(--line);
            border-radius: 3mm;
            color: var(--muted);
            text-align: center;
        }

        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        @media print {
            body {
                background: #fff;
            }

            .print-toolbar {
                display: none;
            }

            .report-stack {
                display: block;
                padding: 0;
            }

            .report-page {
                width: auto;
                min-height: 277mm;
                margin: 0;
                border-top-width: 2.2mm;
                box-shadow: none;
                break-after: page;
                page-break-after: always;
                break-inside: avoid;
                page-break-inside: avoid;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .report-page:last-child {
                break-after: auto;
                page-break-after: auto;
            }
        }

        @media screen and (max-width: 900px) {
            .print-toolbar {
                align-items: stretch;
                flex-direction: column;
            }

            .toolbar-actions,
            .button {
                width: 100%;
            }

            .report-stack {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    @php
        $routeKembali = ($halamanPribadi ?? false) ? 'absensi-pegawai-saya.laporan' : 'laporan-absensi-pegawai-bulanan.index';
        $parameterKembali = array_filter([
            'bulan' => $bulan,
            'jenis_pegawai' => ($halamanPribadi ?? false) ? null : $jenisPegawai,
            'pegawai_id' => ($halamanPribadi ?? false) ? null : $pegawaiId,
            'status_pegawai' => ($halamanPribadi ?? false) ? null : $statusPegawai,
        ], fn ($nilai) => filled($nilai));
    @endphp
    <header class="print-toolbar">
        <div>
            <strong>{{ ($halamanPribadi ?? false) ? 'Laporan presensi saya' : 'Laporan presensi pegawai' }} {{ $labelPeriode }}</strong>
            <p>{{ $jumlahLembar }} lembar. Setiap pegawai dipisahkan menjadi satu halaman A4.</p>
        </div>

        <div class="toolbar-actions">
            <button type="button" class="button" onclick="window.print()">Cetak / Simpan PDF</button>
            <a href="{{ route($routeKembali, $parameterKembali) }}" class="button button-muted">Kembali</a>
        </div>
    </header>

    <main class="report-stack">
        @forelse ($laporanAbsensiPegawai as $item)
            @php
                $pegawai = $item['pegawai'];
                $formatMenit = fn (int $menit) => $menit > 0 ? $menit . ' menit' : '-';
            @endphp
            <article class="report-page">
                <header class="report-letterhead">
                    <div class="report-logo">
                        <img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo SMP Negeri 2 Padang Panjang">
                    </div>

                    <div class="school-copy">
                        <p class="school-name">SMP Negeri 2 Padang Panjang</p>
                        <p class="school-app">NUSA - Sistem Data dan Layanan Sekolah</p>
                    </div>

                    <div class="report-logo">
                        <img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA">
                    </div>
                </header>

                <div class="report-title">
                    <h1>Laporan Presensi Pegawai Bulanan</h1>
                    <p>Periode {{ $labelPeriode }}</p>
                </div>

                <section class="employee-meta" aria-label="Identitas pegawai">
                    <div class="meta-row">
                        <span>Nama</span>
                        <strong>{{ $pegawai->nama_lengkap ?: '-' }}</strong>
                    </div>
                    <div class="meta-row">
                        <span>NIP</span>
                        <strong>{{ $pegawai->nip ?: '-' }}</strong>
                    </div>
                    <div class="meta-row">
                        <span>Jenis pegawai</span>
                        <strong>{{ $pegawai->jenis_pegawai ?: '-' }}</strong>
                    </div>
                    <div class="meta-row">
                        <span>Jabatan</span>
                        <strong>{{ $pegawai->jabatan_utama ?: '-' }}</strong>
                    </div>
                </section>

                <table class="attendance-table" aria-label="Rincian presensi {{ $pegawai->nama_lengkap }}">
                    <colgroup>
                        <col style="width: 5%;">
                        <col style="width: 10%;">
                        <col style="width: 8%;">
                        <col style="width: 12%;">
                        <col style="width: 9%;">
                        <col style="width: 10%;">
                        <col style="width: 9%;">
                        <col style="width: 10%;">
                        <col style="width: 27%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Tanggal</th>
                            <th>Hari</th>
                            <th>Jadwal</th>
                            <th>Scan Masuk</th>
                            <th>Terlambat</th>
                            <th>Scan Pulang</th>
                            <th>Status</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($item['rincian'] as $baris)
                            <tr>
                                <td class="center">{{ $loop->iteration }}</td>
                                <td class="center">{{ $baris['tanggal']->format('d/m/Y') }}</td>
                                <td>{{ $baris['hari'] }}</td>
                                <td class="center">{{ $baris['jam_jadwal'] }}</td>
                                <td class="center">{{ $baris['jam_masuk'] }}</td>
                                <td class="center">{{ $formatMenit($baris['menit_terlambat']) }}</td>
                                <td class="center">
                                    {{ $baris['jam_pulang'] }}
                                    @if ($baris['menit_pulang_cepat'] > 0)
                                        <br>Cepat {{ $formatMenit($baris['menit_pulang_cepat']) }}
                                    @endif
                                </td>
                                <td class="{{ $baris['status_kehadiran'] === 'alfa' ? 'status-alfa' : '' }}">{{ $baris['label_status'] }}</td>
                                <td>{{ $baris['keterangan'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="center">Belum ada hari jadwal atau catatan presensi pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <section class="summary-signature">
                    <div class="summary-box">
                        <h2>Ringkasan</h2>
                        <div class="summary-grid">
                            <div class="summary-item"><span>Hari jadwal</span><strong>{{ $item['hari_efektif'] }}</strong></div>
                            <div class="summary-item"><span>Hadir</span><strong>{{ $item['hadir'] }}</strong></div>
                            <div class="summary-item"><span>Alfa</span><strong>{{ $item['alfa'] }}</strong></div>
                            <div class="summary-item"><span>Izin</span><strong>{{ $item['izin'] }}</strong></div>
                            <div class="summary-item"><span>Sakit</span><strong>{{ $item['sakit'] }}</strong></div>
                            <div class="summary-item"><span>Dinas luar</span><strong>{{ $item['dinas_luar'] }}</strong></div>
                            <div class="summary-item"><span>Cuti</span><strong>{{ $item['cuti'] }}</strong></div>
                            <div class="summary-item"><span>Terlambat</span><strong>{{ $item['terlambat'] }} kali</strong></div>
                            <div class="summary-item"><span>Total terlambat</span><strong>{{ $formatMenit($item['menit_terlambat']) }}</strong></div>
                            <div class="summary-item"><span>Pulang cepat</span><strong>{{ $item['pulang_cepat'] }} kali</strong></div>
                            <div class="summary-item"><span>Total cepat</span><strong>{{ $formatMenit($item['menit_pulang_cepat']) }}</strong></div>
                            <div class="summary-item"><span>Belum pulang</span><strong>{{ $item['belum_pulang'] }}</strong></div>
                        </div>
                    </div>

                    <div class="signature">
                        <p class="statement">Saya menyatakan data presensi ini telah saya periksa dan sesuai dengan catatan kehadiran saya.</p>
                        <p>Padang Panjang, {{ $tanggalCetak }}</p>
                        <p>Pegawai yang bersangkutan,</p>
                        <div class="signature-space"></div>
                        <div class="signature-name">{{ $pegawai->nama_lengkap ?: '-' }}</div>
                        <p>NIP. {{ $pegawai->nip ?: '-' }}</p>
                    </div>
                </section>

                <footer class="page-note">
                    Dicetak dari NUSA. Scan dan koreksi manual yang tersimpan pada periode ini menjadi sumber laporan.
                </footer>
            </article>
        @empty
            <article class="report-page">
                <header class="report-letterhead">
                    <div class="report-logo">
                        <img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo SMP Negeri 2 Padang Panjang">
                    </div>
                    <div class="school-copy">
                        <p class="school-name">SMP Negeri 2 Padang Panjang</p>
                        <p class="school-app">NUSA - Sistem Data dan Layanan Sekolah</p>
                    </div>
                    <div class="report-logo">
                        <img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA">
                    </div>
                </header>
                <div class="empty-report">
                    <div>
                        <strong>Tidak ada laporan untuk dicetak.</strong>
                        <p>Periksa filter bulan atau pilihan pegawai.</p>
                    </div>
                </div>
            </article>
        @endforelse
    </main>
</body>
</html>
