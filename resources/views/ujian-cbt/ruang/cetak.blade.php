<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Daftar Hadir dan Berita Acara CBT {{ $ujianCbt->kode }} - NUSA</title>
    <style>
        :root {
            --primary: #15477a;
            --primary-dark: #0e3157;
            --accent: #f1c40f;
            --line: #94a3b8;
            --muted: #475569;
            --soft: #f8fafc;
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
            font-size: 13px;
            font-weight: 700;
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
            font-weight: 800;
            text-decoration: none;
        }

        .button-muted {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: var(--primary-dark);
        }

        .print-stack {
            display: grid;
            gap: 12mm;
            padding: 0 16px 24px;
        }

        .print-page {
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

        .letterhead {
            display: grid;
            grid-template-columns: 18mm minmax(0, 1fr) 16mm;
            gap: 4mm;
            align-items: center;
            border-bottom: .35mm solid var(--primary);
            padding-bottom: 3mm;
        }

        .logo-box {
            display: grid;
            place-items: center;
            width: 100%;
            height: 18mm;
        }

        .logo-box img {
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

        .school-name {
            color: var(--primary-dark);
            font-size: 13pt;
            font-weight: 900;
            line-height: 1.15;
            text-transform: uppercase;
        }

        .school-app {
            margin-top: .8mm;
            color: var(--muted);
            font-size: 8.4pt;
            font-weight: 800;
        }

        .document-title {
            margin: 4mm 0 3mm;
            text-align: center;
        }

        .document-title h1 {
            margin: 0;
            color: var(--primary-dark);
            font-size: 12.2pt;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .document-title p {
            margin: 1mm 0 0;
            font-size: 8.6pt;
            font-weight: 800;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.4mm 6mm;
            margin-bottom: 3mm;
            border: .25mm solid #cbd5e1;
            border-radius: 2mm;
            background: var(--soft);
            padding: 2.5mm 3mm;
            font-size: 8.2pt;
        }

        .meta-row {
            display: grid;
            grid-template-columns: 28mm minmax(0, 1fr);
            gap: 1.4mm;
            min-width: 0;
        }

        .meta-row span:first-child {
            color: var(--muted);
        }

        .meta-row strong {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .note {
            margin: 0 0 2.5mm;
            color: #334155;
            font-size: 7.5pt;
            font-weight: 700;
            line-height: 1.35;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7.4pt;
        }

        .data-table th,
        .data-table td {
            border: .22mm solid var(--line);
            padding: 1.25mm 1mm;
            vertical-align: middle;
        }

        .data-table th {
            background: var(--primary);
            color: #fff;
            font-size: 6.9pt;
            font-weight: 900;
            line-height: 1.15;
            text-align: center;
        }

        .data-table td {
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .data-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .center {
            text-align: center;
        }

        .signature-cell {
            height: 9mm;
        }

        .event-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4mm;
            table-layout: fixed;
            font-size: 8.2pt;
        }

        .event-table td {
            border: .22mm solid var(--line);
            padding: 2mm;
            vertical-align: top;
        }

        .event-table .label {
            width: 34%;
            background: var(--soft);
            color: var(--muted);
            font-weight: 800;
        }

        .line {
            display: inline-block;
            min-width: 34mm;
            border-bottom: .25mm dotted #64748b;
            transform: translateY(-.5mm);
        }

        .statement {
            margin: 0 0 3mm;
            font-size: 8.5pt;
            line-height: 1.65;
            text-align: justify;
        }

        .manual-area {
            display: grid;
            gap: 2mm;
            margin-top: 2mm;
        }

        .manual-box {
            border: .25mm solid #cbd5e1;
            border-radius: 2mm;
            padding: 2.5mm 3mm;
            min-height: 30mm;
        }

        .manual-box h2 {
            margin: 0 0 2mm;
            color: var(--primary-dark);
            font-size: 8pt;
            text-transform: uppercase;
        }

        .manual-lines {
            min-height: 20mm;
            background: repeating-linear-gradient(
                to bottom,
                transparent 0,
                transparent 5.4mm,
                #cbd5e1 5.55mm,
                transparent 5.7mm
            );
            white-space: pre-line;
        }

        .signature-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 7mm;
            margin-top: auto;
            padding-top: 5mm;
        }

        .signature-box {
            font-size: 7.9pt;
            line-height: 1.35;
            text-align: center;
        }

        .signature-role {
            min-height: 10mm;
            color: var(--muted);
            font-weight: 800;
        }

        .signature-space {
            height: 21mm;
        }

        .signature-name {
            border-bottom: .25mm solid #111827;
            padding-bottom: .8mm;
            font-weight: 900;
            overflow-wrap: anywhere;
        }

        .signature-nip {
            margin-top: .8mm;
            color: var(--muted);
            font-size: 7.1pt;
            font-weight: 700;
        }

        .empty-report {
            display: grid;
            place-items: center;
            min-height: 120mm;
            border: .25mm dashed #cbd5e1;
            border-radius: 3mm;
            color: var(--muted);
            font-size: 10pt;
            font-weight: 800;
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

            .print-stack {
                display: block;
                padding: 0;
            }

            .print-page {
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

            .print-page:last-child {
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

            .print-stack {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    @php
        $formatTanggal = fn ($tanggal) => $tanggal ? $tanggal->format('d-m-Y H:i') : '-';
        $formatTanggalSaja = fn ($tanggal) => $tanggal ? $tanggal->format('d-m-Y') : '-';
        $formatJam = fn ($tanggal) => $tanggal ? $tanggal->format('H:i') : '-';
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $queryKembali = array_filter([
            'sesi_ujian_cbt_id' => $sesiUjianCbtId,
            'jadwal_ujian_cbt_id' => $jadwalUjianCbtId,
            'ruang_ujian_cbt_id' => $ruangUjianCbtId,
        ], fn ($nilai) => filled($nilai));
        $gabungTanggalJam = function ($jadwal, mixed $jam) {
            if (! $jadwal?->tanggal || ! filled($jam)) {
                return null;
            }

            return \Illuminate\Support\Carbon::parse($jadwal->tanggal->format('Y-m-d') . ' ' . substr((string) $jam, 0, 8));
        };
    @endphp

    <header class="print-toolbar">
        <div>
            <strong>Daftar hadir dan berita acara CBT</strong>
            <p>{{ $ruangUjianCbt->count() }} ruang siap dicetak. Satu ruang berisi daftar hadir dan berita acara.</p>
        </div>

        <div class="toolbar-actions">
            <button type="button" class="button" onclick="window.print()">Cetak / Simpan PDF</button>
            <a href="{{ route('ujian-cbt.ruang.index', array_merge([$ujianCbt], $queryKembali)) }}" class="button button-muted">Kembali</a>
        </div>
    </header>

    <main class="print-stack">
        @forelse ($ruangUjianCbt as $ruang)
            @php
                $pesertaRuang = $ruang->pesertaUjianCbt;
                $jadwalRuang = $ruang->jadwalUjianCbt;
                $waktuMulai = $gabungTanggalJam($jadwalRuang, $jadwalRuang?->waktu_mulai)
                    ?: ($ruang->sesiUjianCbt?->waktu_mulai ?: $ujianCbt->tanggal_mulai);
                $waktuSelesai = $gabungTanggalJam($jadwalRuang, $jadwalRuang?->waktu_selesai)
                    ?: ($ruang->sesiUjianCbt?->waktu_selesai ?: $ujianCbt->tanggal_selesai);
                $mataPelajaranRuang = $jadwalRuang?->mataPelajaran?->nama ?: ($ujianCbt->mataPelajaran?->nama ?: '-');
                $labelSesiRuang = $jadwalRuang?->label_sesi ?: ($ruang->sesiUjianCbt?->nama ?: 'Mengikuti jadwal paket');
                $kegiatanUjianRuang = $jadwalRuang?->kegiatanUjianCbt?->nama ?: ($ujianCbt->jenisUjianCbt?->nama ?: '-');
            @endphp

            <section class="print-page">
                <header class="letterhead">
                    <div class="logo-box">
                        <img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo SMP Negeri 2 Padang Panjang">
                    </div>

                    <div class="school-copy">
                        <p class="school-name">SMP Negeri 2 Padang Panjang</p>
                        <p class="school-app">NUSA - Sistem Data dan Layanan Sekolah</p>
                    </div>

                    <div class="logo-box">
                        <img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA">
                    </div>
                </header>

                <div class="document-title">
                    <h1>Daftar Hadir Peserta Ujian CBT</h1>
                    <p>{{ $ruang->kode }} - {{ $ruang->nama }}</p>
                </div>

                <section class="meta-grid">
                    <div class="meta-row"><span>Kegiatan</span><strong>{{ $kegiatanUjianRuang }}</strong></div>
                    <div class="meta-row"><span>Mata pelajaran</span><strong>{{ $mataPelajaranRuang }}</strong></div>
                    <div class="meta-row"><span>Tahun pelajaran</span><strong>{{ $ujianCbt->tahunPelajaran?->nama ?: '-' }}</strong></div>
                    <div class="meta-row"><span>Waktu</span><strong>{{ $formatTanggal($waktuMulai) }} - {{ $formatJam($waktuSelesai) }}</strong></div>
                    <div class="meta-row"><span>Sesi</span><strong>{{ $labelSesiRuang }}</strong></div>
                    <div class="meta-row"><span>Lokasi</span><strong>{{ $teks($ruang->lokasi) }}</strong></div>
                    <div class="meta-row"><span>Pengawas 1</span><strong>{{ $ruang->pengawasUtama?->nama_lengkap ?: '-' }}</strong></div>
                    <div class="meta-row"><span>Pengawas 2</span><strong>{{ $ruang->pengawasPendamping?->nama_lengkap ?: '-' }}</strong></div>
                </section>

                <p class="note">Peserta yang hadir menandatangani kolom tanda tangan. Peserta yang tidak hadir dibiarkan kosong dan ditulis pada kolom keterangan.</p>

                <table class="data-table" aria-label="Daftar hadir peserta CBT {{ $ruang->nama }}">
                    <colgroup>
                        <col style="width: 5%;">
                        <col style="width: 8%;">
                        <col style="width: 18%;">
                        <col style="width: 31%;">
                        <col style="width: 9%;">
                        <col style="width: 17%;">
                        <col style="width: 12%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>No. Meja</th>
                            <th>No. Peserta</th>
                            <th>Nama Peserta</th>
                            <th>Kelas</th>
                            <th>Tanda Tangan Peserta</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pesertaRuang as $peserta)
                            @php
                                $siswa = $peserta->anggotaKelas?->siswa;
                            @endphp
                            <tr>
                                <td class="center">{{ $loop->iteration }}</td>
                                <td class="center"><strong>{{ $peserta->nomor_meja ?: '-' }}</strong></td>
                                <td>{{ $peserta->akunPesertaCbt?->nomor_peserta ?: ($peserta->nomor_peserta ?: '-') }}</td>
                                <td>{{ $siswa?->nama_lengkap ?: '-' }}</td>
                                <td class="center">{{ $peserta->kelasUjianCbt?->kelas?->nama ?: '-' }}</td>
                                <td class="signature-cell"></td>
                                <td>{{ $peserta->catatan_kehadiran_ujian }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="center">Belum ada peserta yang ditempatkan di ruang ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="signature-grid">
                    <div class="signature-box">
                        <div class="signature-role">Pengawas 1</div>
                        <div class="signature-space"></div>
                        <div class="signature-name">{{ $ruang->pengawasUtama?->nama_lengkap ?: '................................' }}</div>
                        <div class="signature-nip">NIP. {{ $ruang->pengawasUtama?->nip ?: '................................' }}</div>
                    </div>
                    <div class="signature-box">
                        <div class="signature-role">Pengawas 2</div>
                        <div class="signature-space"></div>
                        <div class="signature-name">{{ $ruang->pengawasPendamping?->nama_lengkap ?: '................................' }}</div>
                        <div class="signature-nip">NIP. {{ $ruang->pengawasPendamping?->nip ?: '................................' }}</div>
                    </div>
                    <div class="signature-box">
                        <div class="signature-role">Proktor / Panitia</div>
                        <div class="signature-space"></div>
                        <div class="signature-name">................................</div>
                        <div class="signature-nip">NIP. ................................</div>
                    </div>
                </div>
            </section>

            <section class="print-page">
                <header class="letterhead">
                    <div class="logo-box">
                        <img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo SMP Negeri 2 Padang Panjang">
                    </div>

                    <div class="school-copy">
                        <p class="school-name">SMP Negeri 2 Padang Panjang</p>
                        <p class="school-app">NUSA - Sistem Data dan Layanan Sekolah</p>
                    </div>

                    <div class="logo-box">
                        <img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA">
                    </div>
                </header>

                <div class="document-title">
                    <h1>Berita Acara Ujian CBT</h1>
                    <p>{{ $ruang->kode }} - {{ $ruang->nama }}</p>
                </div>

                <table class="event-table" aria-label="Data berita acara CBT">
                    <tbody>
                        <tr>
                            <td class="label">Kegiatan</td>
                            <td>{{ $kegiatanUjianRuang }}</td>
                            <td class="label">Mata pelajaran</td>
                            <td>{{ $mataPelajaranRuang }}</td>
                        </tr>
                        <tr>
                            <td class="label">Hari/tanggal</td>
                            <td>{{ $formatTanggalSaja($waktuMulai) }}</td>
                            <td class="label">Waktu/sesi</td>
                            <td>{{ $formatJam($waktuMulai) }} - {{ $formatJam($waktuSelesai) }}{{ filled($labelSesiRuang) ? ' / ' . $labelSesiRuang : '' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Ruang</td>
                            <td>{{ $ruang->kode }} - {{ $ruang->nama }}</td>
                            <td class="label">Jumlah peserta terdaftar</td>
                            <td>{{ $pesertaRuang->count() }} orang</td>
                        </tr>
                        <tr>
                            <td class="label">Jumlah hadir</td>
                            <td><span class="line"></span> orang</td>
                            <td class="label">Jumlah tidak hadir</td>
                            <td><span class="line"></span> orang</td>
                        </tr>
                        <tr>
                            <td class="label">Ujian dimulai pukul</td>
                            <td><span class="line"></span></td>
                            <td class="label">Ujian selesai pukul</td>
                            <td><span class="line"></span></td>
                        </tr>
                    </tbody>
                </table>

                <p class="statement">
                    Pada hari dan tanggal tersebut di atas telah dilaksanakan ujian CBT di SMP Negeri 2 Padang Panjang. Daftar hadir peserta terlampir pada lembar sebelumnya. Berita acara ini diisi dan ditandatangani oleh pengawas setelah ujian selesai.
                </p>

                <table class="data-table" aria-label="Peserta tidak hadir">
                    <colgroup>
                        <col style="width: 7%;">
                        <col style="width: 45%;">
                        <col style="width: 18%;">
                        <col style="width: 30%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Peserta Tidak Hadir</th>
                            <th>Kelas</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($i = 1; $i <= 5; $i++)
                            <tr>
                                <td class="center">{{ $i }}</td>
                                <td class="signature-cell"></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>

                <div class="manual-area">
                    <div class="manual-box">
                        <h2>Catatan kejadian / kendala / tindak lanjut</h2>
                        <div class="manual-lines">{{ trim(($ruang->berita_acara ?: '') . "\n" . ($ruang->hambatan ?: '') . "\n" . ($ruang->tindak_lanjut ?: '')) }}</div>
                    </div>
                </div>

                <div class="signature-grid">
                    <div class="signature-box">
                        <div class="signature-role">Pengawas 1</div>
                        <div class="signature-space"></div>
                        <div class="signature-name">{{ $ruang->pengawasUtama?->nama_lengkap ?: '................................' }}</div>
                        <div class="signature-nip">NIP. {{ $ruang->pengawasUtama?->nip ?: '................................' }}</div>
                    </div>
                    <div class="signature-box">
                        <div class="signature-role">Pengawas 2</div>
                        <div class="signature-space"></div>
                        <div class="signature-name">{{ $ruang->pengawasPendamping?->nama_lengkap ?: '................................' }}</div>
                        <div class="signature-nip">NIP. {{ $ruang->pengawasPendamping?->nip ?: '................................' }}</div>
                    </div>
                    <div class="signature-box">
                        <div class="signature-role">Proktor / Panitia</div>
                        <div class="signature-space"></div>
                        <div class="signature-name">................................</div>
                        <div class="signature-nip">NIP. ................................</div>
                    </div>
                </div>
            </section>
        @empty
            <section class="print-page">
                <header class="letterhead">
                    <div class="logo-box">
                        <img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo SMP Negeri 2 Padang Panjang">
                    </div>

                    <div class="school-copy">
                        <p class="school-name">SMP Negeri 2 Padang Panjang</p>
                        <p class="school-app">NUSA - Sistem Data dan Layanan Sekolah</p>
                    </div>

                    <div class="logo-box">
                        <img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA">
                    </div>
                </header>

                <div class="document-title">
                    <h1>Daftar Hadir dan Berita Acara CBT</h1>
                    <p>{{ $ujianCbt->nama }}</p>
                </div>

                <div class="empty-report">Belum ada ruang CBT yang sesuai filter cetak.</div>
            </section>
        @endforelse
    </main>
</body>
</html>
