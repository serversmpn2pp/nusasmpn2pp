<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Leger STS {{ $mode === 'kelas' ? $kelas->nama : 'Tingkat '.$tingkat }}</title>
    <style>
        :root { --navy:#173f69; --navy-dark:#102f50; --line:#91a5b8; --line-soft:#ccd6df; --soft:#edf3f8; --ink:#172334; --muted:#586979; --accent:#e5b51b; }
        * { box-sizing:border-box; }
        html { background:#e8edf2; }
        body { margin:0; color:var(--ink); font:8.2pt Arial,Helvetica,sans-serif; }
        .toolbar { position:sticky; top:0; z-index:10; display:flex; flex-wrap:wrap; align-items:center; gap:9px; padding:11px 18px; border-bottom:1px solid #ccd5df; background:rgba(255,255,255,.97); box-shadow:0 2px 8px rgba(26,47,69,.08); }
        .toolbar a,.toolbar button { min-height:36px; padding:8px 13px; border-radius:5px; font:700 9pt Arial,Helvetica,sans-serif; text-decoration:none; cursor:pointer; }
        .toolbar a { border:1px solid #aebdcb; background:#fff; color:var(--navy-dark); }
        .toolbar button { border:1px solid var(--navy); background:var(--navy); color:#fff; }
        .toolbar span { margin-left:auto; color:var(--muted); }
        .sheet { width:297mm; min-height:210mm; margin:16px auto; padding:8mm 9mm 7mm; background:#fff; box-shadow:0 5px 22px rgba(24,47,71,.13); }
        .report-header { display:grid; grid-template-columns:18mm minmax(0,1fr) 18mm; gap:4mm; align-items:center; }
        .logo { display:flex; height:18mm; align-items:center; justify-content:center; }
        .logo img { display:block; max-width:17mm; max-height:17mm; object-fit:contain; }
        .logo.city img { max-width:15mm; }
        .title { text-align:center; color:var(--navy-dark); }
        .title h1,.title h2,.title p { margin:0; }
        .title h1 { font-size:14pt; line-height:1.1; }
        .title h2 { margin-top:1mm; font-size:10.5pt; }
        .title p { margin-top:1mm; font-size:8.5pt; }
        .rule { position:relative; height:1px; margin:2.5mm 0 3mm; background:var(--navy); }
        .rule::after { position:absolute; top:-1px; left:50%; width:24mm; height:3px; content:''; transform:translateX(-50%); background:var(--accent); }
        .meta { display:grid; grid-template-columns:1.5fr 1fr 1fr 1fr; margin-bottom:3mm; border:1px solid var(--line); }
        .meta-item { min-height:12mm; padding:2.2mm 3mm; }
        .meta-item + .meta-item { border-left:1px solid var(--line-soft); }
        .meta-label { display:block; color:var(--muted); font-size:7pt; text-transform:uppercase; }
        .meta-value { display:block; margin-top:1mm; color:var(--navy-dark); font-size:10pt; font-weight:700; }
        .notice { margin:0 0 3mm; padding:1.8mm 2.5mm; border-left:2px solid var(--accent); background:#fffbea; color:#5f4b05; font-size:7.5pt; }
        .table-wrap { width:100%; }
        table { width:100%; border-collapse:collapse; table-layout:fixed; }
        thead { display:table-header-group; }
        th,td { border:.7px solid var(--line); padding:1.4mm 1.1mm; vertical-align:middle; overflow-wrap:anywhere; }
        th { background:var(--soft); color:var(--navy-dark); font-size:6.7pt; line-height:1.12; text-align:center; text-transform:uppercase; }
        td { height:7mm; line-height:1.18; }
        tr { break-inside:avoid; page-break-inside:avoid; }
        .number,.center,.score { text-align:center; font-variant-numeric:tabular-nums; }
        .score { font-weight:700; }
        .student strong,.student span { display:block; }
        .student strong { font-size:7.8pt; }
        .student span { margin-top:.7mm; color:var(--muted); font-size:6.6pt; }
        .status { font-size:6.5pt; font-weight:700; text-align:center; }
        .progress { display:block; margin-top:.8mm; font-size:6pt; font-weight:400; color:var(--muted); }
        .draft { display:block; font-size:5.8pt; font-weight:400; color:#805500; }
        .rank { color:var(--navy-dark); font-size:9pt; font-weight:700; text-align:center; }
        .legend { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:1mm 5mm; margin-top:2.5mm; padding:2mm 2.5mm; border:1px solid var(--line-soft); color:var(--muted); font-size:6.8pt; }
        .legend strong { color:var(--navy-dark); }
        .notes { display:flex; flex-wrap:wrap; justify-content:space-between; gap:2mm 7mm; margin-top:2.5mm; color:var(--muted); font-size:6.8pt; }
        .signatures { display:grid; grid-template-columns:1fr 1fr; gap:36mm; margin-top:4mm; text-align:center; }
        .signature p { margin:.7mm 0; }
        .signature-space { height:13mm; }
        .signature-name { display:inline-block; min-width:54mm; padding-bottom:.5mm; border-bottom:1px solid var(--ink); font-weight:700; }
        @page { size:A4 landscape; margin:6mm; }
        @media print {
            html,body { background:#fff; }
            body { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .toolbar { display:none; }
            .sheet { width:auto; min-height:0; margin:0; padding:0; box-shadow:none; }
            .report-header,.meta,.notice,.legend,.notes,.signatures { break-inside:avoid; page-break-inside:avoid; }
        }
        @media screen and (max-width:900px) {
            body { overflow-x:auto; }
            .sheet { margin:12px; }
            .toolbar span { flex-basis:100%; margin-left:0; }
        }
    </style>
</head>
<body>
    @php
        $baris = $leger['baris'];
        $mapel = $leger['mapel'];
        $ringkasan = $leger['ringkasan'];
        $rataCakupan = $mode === 'kelas' ? $ringkasan['rata_kelas'] : $ringkasan['rata_tingkat'];
        $cakupan = $mode === 'kelas' ? $kelas->nama : 'Tingkat '.$tingkat;
        $kembali = route('leger-sts.index', [
            'mode' => $mode,
            'kegiatan_id' => $kegiatan->id,
            'kelas_id' => $mode === 'kelas' ? $kelas?->id : null,
            'tingkat' => $mode === 'tingkat' ? $tingkat : null,
        ]);
    @endphp
    <nav class="toolbar" aria-label="Tindakan cetak leger">
        <a href="{{ $kembali }}">Kembali ke Leger</a>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        <span>{{ $baris->count() }} siswa · {{ $mapel->count() }} mapel · A4 landscape</span>
    </nav>

    <main class="sheet">
        <header class="report-header">
            <div class="logo city"><img src="{{ asset('images/logo-padang-panjang.png') }}" alt="Logo Kota Padang Panjang"></div>
            <div class="title">
                <h1>SMP NEGERI 2 PADANG PANJANG</h1>
                <h2>LEGER NILAI SUMATIF TENGAH SEMESTER {{ $kegiatan->semester === 'ganjil' ? 'I' : 'II' }}</h2>
                <p>TAHUN PELAJARAN {{ $kegiatan->tahunPelajaran->nama }}</p>
            </div>
            <div class="logo"><img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo SMP Negeri 2 Padang Panjang"></div>
        </header>
        <div class="rule" aria-hidden="true"></div>

        <section class="meta" aria-label="Ringkasan leger">
            <div class="meta-item"><span class="meta-label">Cakupan</span><span class="meta-value">{{ $cakupan }}</span></div>
            <div class="meta-item"><span class="meta-label">Jumlah siswa</span><span class="meta-value">{{ $ringkasan['jumlah_siswa'] }}</span></div>
            <div class="meta-item"><span class="meta-label">Masuk ranking</span><span class="meta-value">{{ $ringkasan['masuk_ranking'] }}</span></div>
            <div class="meta-item"><span class="meta-label">Rata-rata {{ $mode === 'kelas' ? 'kelas' : 'tingkat' }}</span><span class="meta-value">{{ $rataCakupan === null ? '-' : number_format($rataCakupan, 2, ',', '.') }}</span></div>
        </section>

        @if ($mapel->isNotEmpty())
            <p class="notice"><strong>{{ $leger['ranking_sementara'] ? 'Ranking sementara.' : 'Nilai lengkap dan final.' }}</strong> Rata-rata = jumlah nilai tersedia dibagi {{ $mapel->count() }} mapel yang ditetapkan{{ $mode === 'tingkat' ? ' untuk seluruh siswa paralel' : '' }}. {{ $ringkasan['lengkap_final'] }}/{{ $ringkasan['jumlah_siswa'] }} siswa lengkap dan final. @if ($leger['ranking_sementara'])Ranking dapat berubah; belum menjadi dasar penetapan penghargaan.@endif @if ($ringkasan['belum_masuk_ranking']){{ $ringkasan['belum_masuk_ranking'] }} siswa belum memiliki nilai dan tidak masuk ranking.@endif</p>
        @else
            <p class="notice">Belum ada mata pelajaran untuk perhitungan ranking.</p>
        @endif

        <div class="table-wrap">
            <table>
                <colgroup>
                    <col style="width:7mm">
                    <col style="width:9mm">
                    <col style="width:42mm">
                    @if ($mode === 'tingkat')<col style="width:14mm">@endif
                    @foreach ($mapel as $pelajaran)<col style="width:11mm">@endforeach
                    <col style="width:13mm">
                    <col style="width:13mm">
                    <col style="width:25mm">
                </colgroup>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Rank</th>
                        <th>Nama Siswa</th>
                        @if ($mode === 'tingkat')<th>Kelas</th>@endif
                        @foreach ($mapel as $pelajaran)
                            <th title="{{ $pelajaran->nama }}">{{ $pelajaran->kode ?: 'M'.$loop->iteration }}</th>
                        @endforeach
                        <th>Jumlah</th>
                        <th>Rata-rata</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($baris as $item)
                        <tr>
                            <td class="number">{{ $loop->iteration }}</td>
                            <td class="rank">{{ $item['ranking'] ?? '-' }}</td>
                            <td class="student"><strong>{{ $item['anggota']->siswa->nama_lengkap }}</strong><span>NISN {{ $item['anggota']->siswa->nisn ?: '-' }}</span></td>
                            @if ($mode === 'tingkat')<td class="center"><strong>{{ $item['kelas']->nama }}</strong></td>@endif
                            @foreach ($item['nilai'] as $nilai)
                                <td class="score">@if ($nilai['nilai'] !== null){{ number_format($nilai['nilai'], 2, ',', '.') }}@if ($nilai['draf'])<span class="draft">Draf</span>@endif @elseif ($nilai['dikecualikan'])TM @else-@endif</td>
                            @endforeach
                            <td class="score">{{ $item['jumlah_leger'] === null ? '-' : number_format($item['jumlah_leger'], 2, ',', '.') }}</td>
                            <td class="score">{{ $item['rata_leger'] === null ? '-' : number_format($item['rata_leger'], 2, ',', '.') }}</td>
                            <td class="status">{{ $item['status_ranking'] }}<span class="progress">{{ $item['jumlah_nilai_tersedia'] }}/{{ $item['jumlah_mapel'] }} mapel · {{ $item['jumlah_nilai_final'] }} final · {{ $item['jumlah_nilai_draf'] }} draf</span></td>
                        </tr>
                    @empty
                        <tr><td class="center" colspan="30">Belum ada siswa aktif pada cakupan ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <section class="legend" aria-label="Keterangan kode mata pelajaran">
            @foreach ($mapel as $pelajaran)
                <span><strong>{{ $pelajaran->kode ?: 'M'.$loop->iteration }}</strong> = {{ $pelajaran->nama }}</span>
            @endforeach
        </section>
        <div class="notes">
            <span>Ranking menggunakan rata-rata dua desimal; nilai sama memperoleh ranking yang sama. Nilai kosong tetap belum tersedia.</span>
            <span>TM = Tidak mengikuti STS · Dicetak {{ now()->locale('id')->translatedFormat('d F Y H:i') }} WIB</span>
        </div>

        <section class="signatures" aria-label="Tanda tangan">
            <div class="signature">
                <p>Mengetahui,</p>
                <p>Kepala Sekolah</p>
                <div class="signature-space"></div>
                <p><span class="signature-name">................................................</span></p>
                <p>NIP. ........................................</p>
            </div>
            <div class="signature">
                <p>Padang Panjang, {{ now()->locale('id')->translatedFormat('d F Y') }}</p>
                <p>{{ $mode === 'kelas' ? 'Wali Kelas' : 'Wakil Kepala Sekolah Bidang Kurikulum' }}</p>
                <div class="signature-space"></div>
                <p><span class="signature-name">{{ $mode === 'kelas' ? ($kelas->waliKelas?->nama_lengkap ?? '................................................') : '................................................' }}</span></p>
                <p>NIP. {{ $mode === 'kelas' ? ($kelas->waliKelas?->nip ?: '........................................') : '........................................' }}</p>
            </div>
        </section>
    </main>
</body>
</html>
