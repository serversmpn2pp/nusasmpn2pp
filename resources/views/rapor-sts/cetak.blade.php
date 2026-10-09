<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pratinjau ? 'Pratinjau' : 'Cetak' }} Rapor STS {{ $kelas->nama }}</title>
    <style>
        :root {
            --navy: #173f69;
            --navy-dark: #102f50;
            --line: #8fa5bb;
            --line-soft: #cbd6e1;
            --header-soft: #eaf1f7;
            --ink: #172334;
            --muted: #59697a;
            --accent: #e5b51b;
            --paper: #ffffff;
        }

        * { box-sizing: border-box; }
        html { background: #e8edf2; }
        body { margin: 0; color: var(--ink); font: 10pt Arial, Helvetica, sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 12px 20px; border-bottom: 1px solid #ccd5df; background: rgba(255,255,255,.96); box-shadow: 0 2px 9px rgba(26,47,69,.08); }
        .toolbar a,.toolbar button { min-height: 38px; padding: 9px 14px; border-radius: 5px; font: 700 9.5pt Arial, Helvetica, sans-serif; text-decoration: none; cursor: pointer; }
        .toolbar a { border: 1px solid #aebdcb; background: #fff; color: var(--navy-dark); }
        .toolbar button { border: 1px solid var(--navy); background: var(--navy); color: #fff; }
        .toolbar span { margin-left: auto; color: var(--muted); font-size: 9pt; }

        .sheet { position: relative; width: 210mm; min-height: 297mm; margin: 18px auto; padding: 11mm 15mm 10mm; overflow: hidden; background: var(--paper); box-shadow: 0 5px 22px rgba(24,47,71,.13); }
        .draft { margin-bottom: 4mm; padding: 1.8mm 3mm; border: 1px dashed #9f3740; color: #862c34; text-align: center; font-size: 8.5pt; font-weight: 700; letter-spacing: .2px; }

        .report-header { display: grid; grid-template-columns: 22mm minmax(0,1fr) 22mm; gap: 5mm; align-items: center; }
        .report-logo { display: flex; align-items: center; justify-content: center; height: 23mm; }
        .report-logo img { display: block; max-width: 21mm; max-height: 21mm; object-fit: contain; }
        .report-logo--city img { max-width: 18mm; max-height: 21mm; }
        .report-title { text-align: center; color: var(--navy-dark); }
        .report-title h1,.report-title h2,.report-title p { margin: 0; }
        .report-title h1 { font-size: 15pt; line-height: 1.15; letter-spacing: 0; }
        .report-title h2 { margin-top: 1.1mm; font-size: 10.3pt; line-height: 1.18; }
        .report-title .semester { font-size: 10pt; }
        .report-title p { margin-top: 1.2mm; font-size: 9.3pt; }
        .header-rule { position: relative; height: 1px; margin: 3mm 0 4mm; background: var(--navy); }
        .header-rule::after { position: absolute; top: -1px; left: 50%; width: 25mm; height: 3px; content: ''; transform: translateX(-50%); background: var(--accent); }

        .identity { display: grid; grid-template-columns: 28mm minmax(0,1fr) 14mm 30mm; align-items: center; min-height: 12mm; margin-bottom: 4mm; padding: 2.2mm 4mm; border: 1px solid var(--line); background: #fff; }
        .identity-label { color: var(--muted); }
        .identity-value { min-width: 0; overflow-wrap: anywhere; color: var(--navy-dark); font-weight: 700; }
        .identity-class-label { padding-left: 3mm; border-left: 1px solid var(--line-soft); }

        .grades { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .grades th,.grades td { border: .8px solid var(--line); padding: 1.6mm 2.2mm; vertical-align: middle; overflow-wrap: anywhere; }
        .grades th { height: 8mm; background: var(--header-soft); color: var(--navy-dark); font-size: 8.7pt; text-align: center; text-transform: uppercase; }
        .grades tbody tr:not(.summary-row) { min-height: 7mm; }
        .grades td { height: 7mm; line-height: 1.2; }
        .grades .number,.grades .score,.grades .description { text-align: center; font-variant-numeric: tabular-nums; }
        .grades .score { font-weight: 700; }
        .grades .description { font-size: 8.7pt; }
        .grades .summary-row td { height: 7mm; background: #f6f8fa; color: var(--navy-dark); font-weight: 700; }
        .grades .summary-label { padding-left: 4mm; }

        .exception-note { margin: 2.5mm 0 0; padding-left: 3mm; border-left: 2px solid var(--accent); color: var(--muted); font-size: 8pt; line-height: 1.35; }
        .attendance { margin-top: 4mm; border: 1px solid var(--line); }
        .attendance-row { display: grid; grid-template-columns: 48mm minmax(0,1fr); align-items: center; }
        .attendance-row + .attendance-row { border-top: 1px solid var(--line-soft); }
        .attendance-title { padding: 3mm 4mm; color: var(--navy-dark); font-weight: 700; text-align: center; }
        .attendance-list { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); border-left: 1px solid var(--line-soft); }
        .lateness-list { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .attendance-item { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 2mm; align-items: center; padding: 2.5mm 3mm; }
        .attendance-item + .attendance-item { border-left: 1px solid var(--line-soft); }
        .attendance-amount { display: inline-flex; align-items: center; gap: 1mm; white-space: nowrap; }
        .attendance-value { display: inline-block; min-width: 11mm; padding: 1mm 2mm; border: 1px solid var(--line-soft); background: var(--header-soft); color: var(--navy-dark); text-align: center; font-weight: 700; font-variant-numeric: tabular-nums; }
        .attendance-unit { color: var(--muted); font-size: 8pt; }

        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 24mm; margin-top: 5mm; text-align: center; }
        .signature-block p { margin: 1mm 0; }
        .signature-space { height: 17mm; }
        .signature-name { display: inline-block; min-width: 54mm; padding-bottom: .6mm; border-bottom: 1px solid var(--ink); font-weight: 700; }
        .signature-parent { letter-spacing: .7px; }

        .notes { position: relative; margin-top: 5mm; padding: 3mm 4mm 3mm 5mm; border: 1px solid var(--line-soft); color: #34475a; font-size: 7.8pt; line-height: 1.4; }
        .notes::before { position: absolute; top: 3mm; bottom: 3mm; left: 2.5mm; width: 2px; content: ''; background: var(--accent); }
        .notes strong { color: var(--navy-dark); }
        .notes p { margin: 0; }
        .notes p + p { margin-top: .6mm; }

        .sheet--dense { padding-top: 9mm; padding-bottom: 8mm; font-size: 9.4pt; }
        .sheet--dense .report-logo { height: 20mm; }
        .sheet--dense .report-logo img { max-height: 18mm; }
        .sheet--dense .grades th,.sheet--dense .grades td { padding-top: 1.1mm; padding-bottom: 1.1mm; }
        .sheet--dense .grades td { height: 6mm; }
        .sheet--dense .signature-space { height: 13mm; }
        .sheet--dense .attendance { margin-top: 3mm; }
        .sheet--dense .signatures,.sheet--dense .notes { margin-top: 3.5mm; }
        .sheet--behavior { overflow:visible; }
        .behavior-period { margin:0 0 3mm; color:var(--muted); font-size:8pt; }
        .behavior-print { font-size:8.5pt; }
        .behavior-print td { height:auto; padding:2mm; vertical-align:top; line-height:1.35; }
        .behavior-print th:first-child { white-space:nowrap; padding-left:1mm; padding-right:1mm; }
        .behavior-totals { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:3mm; margin:4mm 0; padding:3mm 0; border-top:1px solid var(--line); border-bottom:1px solid var(--line); }
        .behavior-totals span { display:block; font-size:7.5pt; color:var(--muted); margin-bottom:1mm; }
        .behavior-totals strong { font-size:10pt; }
        .behavior-remarks { margin:3mm 0; font-size:8.5pt; line-height:1.4; overflow-wrap:anywhere; white-space:pre-line; }
        .signatures--behavior { grid-template-columns:repeat(3,minmax(0,1fr)); gap:4mm; margin-top:5mm; font-size:8pt; }
        .signatures--behavior .signature-name { min-width:0; max-width:100%; overflow-wrap:anywhere; }
        .signatures--behavior .signature-space { height:15mm; }
        .signatures--behavior .signature-parent { letter-spacing:0; }
        .signatures--behavior .signature-block > p:first-child { min-height:7mm; }
        .behavior-date { text-align:right; font-size:8.5pt; margin-top:4mm; }

        @page { size: A4 portrait; margin: 0; }
        @media print {
            html,body { background: #fff; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .toolbar { display: none; }
            .sheet { width: 210mm; height: 297mm; min-height: 297mm; margin: 0; box-shadow: none; break-after: page; page-break-after: always; }
            .sheet:last-child { break-after: auto; page-break-after: auto; }
            .sheet--behavior { height:auto; overflow:visible; }
            .grades tr,.attendance,.signatures,.notes { break-inside: avoid; page-break-inside: avoid; }
        }
        @media screen and (max-width: 820px) {
            body { overflow-x: auto; }
            .sheet { margin: 12px; }
            .toolbar span { flex-basis: 100%; margin-left: 0; }
        }
    </style>
</head>
<body>
    <nav class="toolbar" aria-label="Tindakan rapor">
        <a href="{{ route('rapor-sts.index', ['kegiatan_id' => $kegiatan->id, 'kelas_id' => $kelas->id]) }}">Kembali ke rekap</a>
        @unless ($pratinjau)
            <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        @endunless
        <span>{{ $baris->count() }} siswa · A4 portrait · {{ $pratinjau ? 'Pratinjau draf' : ($baris->every(fn ($item) => $item['nilai_tuntas']) ? 'Rekap nilai lengkap' : 'Memuat nilai belum tersedia') }}</span>
    </nav>

    @foreach ($baris as $item)
        <article @class(['sheet', 'sheet--dense' => $mapel->count() > 11])>
            @if ($pratinjau)
                <div class="draft">DRAF PRATINJAU · {{ $item['siap'] ? 'Siap dicetak melalui halaman rekap' : 'Belum siap dibagikan' }}</div>
            @endif

            <header class="report-header">
                <div class="report-logo report-logo--city">
                    <img src="{{ asset('images/logo-padang-panjang.png') }}" alt="Logo Kota Padang Panjang">
                </div>
                <div class="report-title">
                    <h1>SMP NEGERI 2 PADANG PANJANG</h1>
                    <h2>LAPORAN HASIL CAPAIAN PEMBELAJARAN</h2>
                    <h2 class="semester">SUMATIF TENGAH SEMESTER {{ $kegiatan->semester === 'ganjil' ? 'I' : 'II' }}</h2>
                    <p>TAHUN PELAJARAN {{ $kegiatan->tahunPelajaran->nama }}</p>
                </div>
                <div class="report-logo">
                    <img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo SMP Negeri 2 Padang Panjang">
                </div>
            </header>
            <div class="header-rule" aria-hidden="true"></div>

            <section class="identity" aria-label="Identitas siswa">
                <span class="identity-label">Nama siswa</span>
                <span class="identity-value">: {{ $item['anggota']->siswa->nama_lengkap }}</span>
                <span class="identity-label identity-class-label">Kelas</span>
                <span class="identity-value">: {{ $kelas->nama }}</span>
            </section>

            <table class="grades">
                <colgroup>
                    <col style="width:8%">
                    <col style="width:51%">
                    <col style="width:14%">
                    <col style="width:27%">
                </colgroup>
                <thead>
                    <tr><th>No.</th><th>Mata Pelajaran</th><th>Nilai</th><th>Keterangan</th></tr>
                </thead>
                <tbody>
                    @foreach ($item['nilai'] as $nilai)
                        <tr>
                            <td class="number">{{ $loop->iteration }}</td>
                            <td>{{ $nilai['mapel']->nama }}</td>
                            <td class="score">{{ $nilai['nilai'] === null ? '-' : number_format($nilai['nilai'], 2, ',', '.') }}</td>
                            <td class="description">{{ $nilai['keterangan'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="summary-row">
                        <td class="summary-label" colspan="2">Jumlah</td>
                        <td class="score">{{ $item['jumlah'] === null ? '-' : number_format($item['jumlah'], 2, ',', '.') }}</td>
                        <td></td>
                    </tr>
                    <tr class="summary-row">
                        <td class="summary-label" colspan="2">Rata-rata</td>
                        <td class="score">{{ $item['rata'] === null ? '-' : number_format($item['rata'], 2, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            @if (! $item['nilai_tuntas'] || $item['jumlah_pengecualian'])
                <p class="exception-note">
                    @if ($item['jumlah_pengecualian']){{ $item['jumlah_pengecualian'] }} mata pelajaran berstatus Tidak mengikuti STS.@endif
                    @if (! $item['nilai_tuntas'])
                        {{ $item['nilai']->whereNull('nilai')->where('dikecualikan', false)->count() }} mata pelajaran belum tersedia. Jumlah dan rata-rata belum dihitung; nilai kosong tidak dianggap nol.
                    @elseif ($item['jumlah_bernilai'])
                        Jumlah dan rata-rata dihitung dari {{ $item['jumlah_bernilai'] }} mata pelajaran yang memiliki nilai.
                    @else
                        Jumlah dan rata-rata tidak dihitung karena belum ada nilai STS.
                    @endif
                </p>
            @endif

            <section class="attendance" aria-label="Kehadiran dan keterlambatan siswa">
                <div class="attendance-row">
                    <div class="attendance-title">Ketidakhadiran (hari)</div>
                    <div class="attendance-list">
                        @foreach (['sakit' => 'Sakit', 'izin' => 'Izin', 'alfa' => 'Alfa'] as $jenis => $label)
                            <div class="attendance-item">
                                <span>{{ $label }}</span>
                                <span class="attendance-amount"><span class="attendance-value">{{ $item['kehadiran'][$jenis] }}</span> <span class="attendance-unit">hari</span></span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="attendance-row">
                    <div class="attendance-title">Keterlambatan</div>
                    <div class="attendance-list lateness-list">
                        <div class="attendance-item">
                            <span>Terlambat</span>
                            <span class="attendance-amount"><span class="attendance-value" data-sts-late-count>{{ $item['keterlambatan']['jumlah'] }}</span> <span class="attendance-unit">kali</span></span>
                        </div>
                        <div class="attendance-item">
                            <span>Total keterlambatan</span>
                            <span class="attendance-amount"><span class="attendance-value" data-sts-late-minutes>{{ $item['keterlambatan']['total_menit'] }}</span> <span class="attendance-unit">menit</span></span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="signatures" aria-label="Tanda tangan">
                <div class="signature-block">
                    <p>&nbsp;</p>
                    <p>Orang Tua / Wali Siswa</p>
                    <div class="signature-space"></div>
                    <p class="signature-parent">(................................................)</p>
                </div>
                <div class="signature-block">
                    <p>Padang Panjang, {{ $pengaturan->tanggal_rapor->locale('id')->translatedFormat('d F Y') }}</p>
                    <p>Wali Kelas</p>
                    <div class="signature-space"></div>
                    <p><span class="signature-name">{{ $kelas->waliKelas?->nama_lengkap ?? 'Belum ditetapkan' }}</span></p>
                    <p>NIP. {{ $kelas->waliKelas?->nip ?: '-' }}</p>
                </div>
            </section>

            <footer class="notes">
                <p><strong>Kriteria ketercapaian tujuan pembelajaran:</strong> nilai &lt; 70 = Perlu Bimbingan; 70 sampai &lt; 80 = Cukup; 80 sampai &lt; 90 = Baik; 90 sampai 100 = Sangat Baik.</p>
                <p><strong>Periode presensi:</strong> {{ $pengaturan->tanggal_awal_presensi->format('d-m-Y') }} s.d. {{ $pengaturan->tanggal_akhir_presensi->format('d-m-Y') }}.</p>
            </footer>
        </article>
        @if($lampiran ?? null)
            @include('rapor-sts._perilaku-cetak', ['perilakuSiswa' => $lampiran['baris']->get($item['anggota']->id)])
        @endif
    @endforeach
</body>
</html>
