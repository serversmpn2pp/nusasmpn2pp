<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pratinjau ? 'Pratinjau' : 'Cetak' }} Rapor STS {{ $kelas->nama }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef0f3; color: #111; font: 11pt Arial, sans-serif; }
        .toolbar { padding: 16px 24px; background: #fff; border-bottom: 1px solid #ccc; display: flex; flex-wrap: wrap; gap: 16px; align-items: center; }
        .toolbar button, .toolbar a { padding: 10px 16px; border-radius: 5px; font: inherit; cursor: pointer; text-decoration: none; }
        .toolbar button { border: 0; background: #15477a; color: #fff; font-weight: bold; }
        .toolbar a { border: 1px solid #ccc; color: #111; }
        .sheet { width: 210mm; min-height: 297mm; padding: 14mm 18mm; margin: 20px auto; background: #fff; }
        header { display: grid; grid-template-columns: 18mm 1fr 18mm; align-items: center; gap: 4mm; margin-bottom: 8mm; }
        header img { width: 18mm; height: 18mm; object-fit: contain; }
        header div { text-align: center; }
        h1, h2 { font-size: 12pt; margin: 2mm 0; text-align: center; }
        .school { font-size: 13pt; }
        .identity { display: grid; grid-template-columns: 28mm 1fr 16mm 24mm; gap: 2mm; margin-bottom: 5mm; }
        .name { overflow-wrap: anywhere; font-weight: bold; }
        .grades { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .grades th, .grades td { border: 1px solid #555; padding: 2mm; vertical-align: middle; overflow-wrap: anywhere; }
        .grades th { background: #f1f3f5; font-size: 10pt; }
        .center { text-align: center; font-variant-numeric: tabular-nums; }
        .grades .total td { padding-block: 2mm; font-weight: bold; }
        .attendance { display: grid; grid-template-columns: 1fr 1fr; margin-top: 5mm; gap: 10mm; }
        .attendance table { width: 100%; border-collapse: collapse; }
        .attendance td { padding: 1mm; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 14mm; margin-top: 6mm; text-align: center; }
        .signature-space { height: 18mm; }
        .signatures p { margin: 1.5mm 0; overflow-wrap: anywhere; }
        .legend { font-size: 8.5pt; margin-top: 6mm; line-height: 1.4; }
        .exception-note { font-size: 8.5pt; margin: 3mm 0 0; line-height: 1.4; }
        .draft { padding: 2mm; border: 1px solid #a22; color: #a22; text-align: center; font-weight: bold; margin-bottom: 4mm; }
        .compact { font-size: 10pt; padding-block: 10mm; }
        .compact header { margin-bottom: 4mm; }
        .compact h1, .compact h2 { margin-block: 1mm; }
        .compact .grades td { padding-block: 1mm; }
        .compact .signature-space { height: 14mm; }
        @page { size: A4 portrait; margin: 0; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; break-after: page; page-break-after: always; }
            .sheet:last-child { break-after: auto; page-break-after: auto; }
            tr, .attendance, .signatures { break-inside: avoid; }
        }
        @media screen and (max-width: 820px) { body { overflow-x: auto; } .sheet { margin-left: 12px; margin-right: 12px; } }
    </style>
</head>
<body>
    <nav class="toolbar" aria-label="Tindakan rapor">
        <a href="{{ route('rapor-sts.index', ['kegiatan_id' => $kegiatan->id, 'kelas_id' => $kelas->id]) }}">Kembali ke rekap</a>
        @unless ($pratinjau)<button type="button" onclick="window.print()">Cetak / Simpan PDF</button>@endunless
        <span>{{ $baris->count() }} siswa · A4 · {{ $pratinjau ? 'Pratinjau draf' : 'Rapor final' }}</span>
    </nav>
    @foreach ($baris as $item)
        <article @class(['sheet', 'compact' => $mapel->count() > 10])>
            @if ($pratinjau)<div class="draft">DRAF PRATINJAU · {{ $item['siap'] ? 'Siap cetak melalui rekap' : 'Belum siap dibagikan' }}</div>@endif
            <header>
                <img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo sekolah">
                <div><h1 class="school">SMP NEGERI 2 PADANG PANJANG</h1><h2>LAPORAN HASIL CAPAIAN PEMBELAJARAN</h2><h2>SUMATIF TENGAH SEMESTER {{ $kegiatan->semester === 'ganjil' ? 'I' : 'II' }}</h2><div>TAHUN PELAJARAN {{ $kegiatan->tahunPelajaran->nama }}</div></div>
            </header>
            <div class="identity"><span>Nama siswa</span><span class="name">: {{ $item['anggota']->siswa->nama_lengkap }}</span><span>Kelas</span><strong>: {{ $kelas->nama }}</strong></div>
            <table class="grades">
                <colgroup><col style="width:8%"><col style="width:49%"><col style="width:15%"><col style="width:28%"></colgroup>
                <thead><tr><th>No.</th><th>MATA PELAJARAN</th><th>NILAI</th><th>KETERANGAN</th></tr></thead>
                <tbody>
                    @foreach ($item['nilai'] as $nilai)<tr><td class="center">{{ $loop->iteration }}</td><td>{{ $nilai['mapel']->nama }}</td><td class="center">{{ $nilai['nilai'] === null ? '-' : number_format($nilai['nilai'], 2, ',', '.') }}</td><td class="center">{{ $nilai['keterangan'] }}</td></tr>@endforeach
                    <tr class="total"><td colspan="2">Jumlah</td><td class="center">{{ $item['jumlah'] === null ? '-' : number_format($item['jumlah'], 2, ',', '.') }}</td><td></td></tr>
                    <tr class="total"><td colspan="2">Rata-rata</td><td class="center">{{ $item['rata'] === null ? '-' : number_format($item['rata'], 2, ',', '.') }}</td><td></td></tr>
                </tbody>
            </table>
            @if ($item['jumlah_pengecualian'])
                <p class="exception-note">{{ $item['jumlah_pengecualian'] }} mata pelajaran tidak diikuti.
                    @if ($item['jumlah_bernilai'])Jumlah dan rata-rata dihitung dari {{ $item['jumlah_bernilai'] }} mata pelajaran yang memiliki nilai.
                    @else Jumlah dan rata-rata tidak dihitung karena tidak ada nilai STS.@endif
                </p>
            @endif
            <div class="attendance"><strong>Ketidakhadiran (hari)</strong><table>
                @foreach (['sakit' => 'Sakit', 'izin' => 'Izin', 'alfa' => 'Alfa'] as $jenis => $label)<tr><td>{{ $label }}</td><td class="center">{{ $item['kehadiran'][$jenis] }}</td><td>hari</td></tr>@endforeach
            </table></div>
            <div class="signatures">
                <div><p>&nbsp;</p><p>Orang Tua / Wali Siswa</p><div class="signature-space"></div><p>(.....................................)</p></div>
                <div><p>Padang Panjang, {{ $pengaturan->tanggal_rapor->locale('id')->translatedFormat('d F Y') }}</p><p>Wali Kelas</p><div class="signature-space"></div><p><strong>{{ $kelas->waliKelas?->nama_lengkap ?? 'Belum ditetapkan' }}</strong></p><p>NIP. {{ $kelas->waliKelas?->nip ?: '-' }}</p></div>
            </div>
            <p class="legend">Kriteria ketercapaian tujuan pembelajaran: nilai &lt; 70 = Perlu Bimbingan; 70 sampai &lt; 80 = Cukup; 80 sampai &lt; 90 = Baik; 90 sampai 100 = Sangat Baik.<br>Periode presensi: {{ $pengaturan->tanggal_awal_presensi->format('d-m-Y') }} s.d. {{ $pengaturan->tanggal_akhir_presensi->format('d-m-Y') }}.</p>
        </article>
    @endforeach
</body>
</html>
