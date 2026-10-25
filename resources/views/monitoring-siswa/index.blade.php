<!DOCTYPE html>
<html lang="id" translate="no">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="google" content="notranslate">
    <title>Monitoring Siswa - NUSA</title>
    <link rel="icon" href="{{ asset('images/logo-nusa.png') }}" type="image/png">
    @fonts
    <link rel="stylesheet" href="{{ asset('css/monitoring-tv.css') }}?v={{ filemtime(public_path('css/monitoring-tv.css')) }}">
    <script defer src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
    <script defer src="{{ asset('vendor/lucide/lucide.min.js') }}"></script>
    <script defer src="{{ asset('js/monitoring-tv.js') }}?v={{ filemtime(public_path('js/monitoring-tv.js')) }}"></script>
</head>
<body data-endpoint="{{ route('monitoring-siswa.data') }}">
<main class="tv-screen">
    <header class="tv-header">
        <div class="brand"><img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA" width="52" height="52"><div><strong>NUSA<span>.</span></strong><p>SMP Negeri 2 Padang Panjang</p></div></div>
        <div class="tv-heading"><h1>Monitoring aktivitas siswa</h1><p id="tanggal">Memuat data sekolah...</p></div>
        <div class="tv-tools"><div><span id="connection" class="connection waiting" role="status">Menghubungkan</span><p id="updated">Pembaruan setiap 5 detik</p></div><button id="fullscreen" type="button" title="Layar penuh" aria-label="Layar penuh"><i data-lucide="maximize"></i></button></div>
    </header>
    <div id="notice" class="notice" role="status" hidden></div>
    <section class="kpis" aria-label="Ringkasan presensi hari ini">
        @foreach ([['hadir','Hadir','user-check','teal'],['izin','Izin','clipboard-check','blue'],['sakit','Sakit','heart-pulse','rose'],['alfa','Alfa tercatat','user-x','rose'],['belum','Belum tercatat','scan-line','neutral'],['terlambat','Terlambat','clock-3','yellow'],['laporan','Laporan masuk','message-square-warning','blue']] as [$key,$label,$icon,$tone])
        <article class="kpi {{ $tone }}"><div><i data-lucide="{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></div><strong id="kpi-{{ $key }}">-</strong><small id="detail-{{ $key }}">Hari ini</small></article>
        @endforeach
    </section>
    <div class="tv-grid">
        <section class="chart-section attendance-section">
            <div class="section-heading"><div><p class="eyebrow">PRESENSI SEKOLAH</p><h2>Tren kehadiran</h2></div><div class="segments" aria-label="Periode grafik presensi"><button type="button" data-period="day" aria-pressed="true">Hari ini</button><button type="button" data-period="week" aria-pressed="false">Minggu</button><button type="button" data-period="month" aria-pressed="false">Bulan</button></div></div>
            <div class="chart-key"><span><b class="dot teal-dot"></b>Hadir</span><span><b class="dot yellow-dot"></b>Terlambat (termasuk hadir)</span></div>
            <div class="chart-area"><canvas id="attendance-chart" role="img" aria-label="Grafik kehadiran dan keterlambatan"></canvas><p id="attendance-empty" class="chart-empty" hidden></p></div>
            <p id="attendance-caption" class="caption">Menunggu data presensi</p>
        </section>
        <section class="chart-section reports-section">
            <div class="section-heading"><div><p class="eyebrow" id="secondary-eyebrow">PELAPORAN SISWA</p><h2 id="secondary-title">Laporan masuk</h2></div><span class="tag" id="report-period">Minggu ini</span></div>
            <div class="segments chart-modes" aria-label="Jenis grafik batang"><button type="button" data-metric="laporan" aria-pressed="true"><i data-lucide="chart-no-axes-column"></i> Laporan</button><button type="button" data-metric="kelas" aria-pressed="false"><i data-lucide="school"></i> Per kelas</button></div>
            <div class="chart-area"><canvas id="reports-chart" role="img" aria-label="Jumlah laporan menurut tanggal masuk"></canvas></div>
            <p class="caption" id="secondary-caption">Menurut waktu laporan masuk, termasuk yang belum diverifikasi.</p>
        </section>
        <section class="feed-section">
            <div class="section-heading"><div><p class="eyebrow">PEMANTAUAN LANGSUNG</p><h2>Laporan terbaru</h2></div><span class="tag yellow-tag" id="feed-count">0 laporan</span></div>
            <div id="feed" class="feed"><p class="empty">Menunggu laporan siswa...</p></div>
            <p class="caption" id="feed-page">Belum diverifikasi tidak berarti terbukti melanggar.</p>
        </section>
        <section class="lower-section worship-section">
            <div class="section-heading"><h2><i data-lucide="hand-heart"></i> Ibadah hari ini</h2><span class="tag teal-tag" id="worship-count">-</span></div>
            <div id="worship"><p class="empty">Menunggu jadwal ibadah...</p></div>
            <p class="caption">Rekap peserta wajib yang hadir di sekolah.</p>
        </section>
        <section class="lower-section late-section">
            <div class="section-heading"><h2><i data-lucide="alarm-clock"></i> Keterlambatan</h2><span class="tag yellow-tag" id="late-total">-</span></div>
            <div id="late-list"><p class="empty">Menunggu data keterlambatan...</p></div>
            <p class="caption" id="late-caption">Keterlambatan dihitung sejak detik pertama.</p>
        </section>
        <section class="lower-section points-section">
            <div class="section-heading"><h2><i data-lucide="shield-check"></i> Poin resmi</h2><span class="tag teal-tag">Tahun aktif</span></div>
            <div class="points-balance"><strong id="point-balance">-</strong><span>saldo poin aktif<br><b id="point-students">- siswa</b></span></div>
            <div class="point-changes"><span>Penambahan hari ini <b id="point-added">-</b></span><span>Koreksi / pengurangan <b id="point-reduced">-</b></span></div>
            <p class="caption">Bersumber dari transaksi resmi, bukan usulan poin laporan.</p>
        </section>
    </div>
    <footer class="tv-footer"><span><b class="dot teal-dot"></b> Ruang guru &middot; <span id="year">NUSA</span></span><span id="population">- siswa dipantau</span><span>Data pribadi terbatas &middot; Pembaruan 5 detik</span></footer>
</main>
</body>
</html>
