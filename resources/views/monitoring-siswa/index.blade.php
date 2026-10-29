@php $pameran = $pameran ?? false; @endphp
<!DOCTYPE html>
<html lang="id" translate="no">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="google" content="notranslate">
    <title>{{ $pameran ? 'Monitoring Pameran' : 'Monitoring Siswa' }} - NUSA</title>
    <link rel="icon" href="{{ asset('images/logo-nusa.png') }}" type="image/png">
    @fonts
    <link rel="stylesheet" href="{{ asset('css/monitoring-tv.css') }}?v={{ filemtime(public_path('css/monitoring-tv.css')) }}">
    @if($pameran)<link rel="stylesheet" href="{{ asset('css/monitoring-pameran.css') }}?v={{ filemtime(public_path('css/monitoring-pameran.css')) }}">@endif
    <script defer src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
    <script defer src="{{ asset('vendor/lucide/lucide.min.js') }}"></script>
    <script defer src="{{ asset('js/monitoring-tv-rincian.js') }}?v={{ filemtime(public_path('js/monitoring-tv-rincian.js')) }}"></script>
    <script defer src="{{ asset('js/monitoring-tv.js') }}?v={{ filemtime(public_path('js/monitoring-tv.js')) }}"></script>
    @if($pameran)<script defer src="{{ asset('js/monitoring-pameran-rincian.js') }}?v={{ filemtime(public_path('js/monitoring-pameran-rincian.js')) }}"></script>@endif
    @if($pameran)<script defer src="{{ asset('js/monitoring-pameran.js') }}?v={{ filemtime(public_path('js/monitoring-pameran.js')) }}"></script>@endif
</head>
<body data-endpoint="{{ route($pameran ? 'monitoring-pameran.data' : 'monitoring-siswa.data') }}" data-detail-endpoint="{{ route($pameran ? 'monitoring-pameran.rincian' : 'monitoring-siswa.rincian') }}" @if($pameran) data-pameran="true" data-tanggal="{{ $tanggalPameran }}" data-pameran-tanggal-endpoint="{{ route('monitoring-pameran.tanggal') }}" data-pameran-tamu-endpoint="{{ route('monitoring-pameran.pengunjung') }}" data-pameran-rincian-endpoint="{{ route('monitoring-pameran.pengunjung.rincian') }}" data-monitor-csrf="{{ $monitorCsrf }}" @endif>
<main class="tv-screen">
    <header class="tv-header">
        <div class="brand"><img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA" width="52" height="52"><div><strong>NUSA<span>.</span></strong><p>SMP Negeri 2 Padang Panjang</p></div></div>
        <div class="tv-heading"><h1>{{ $pameran ? 'Monitoring TV' : 'Monitoring aktivitas siswa' }}</h1><p id="{{ $pameran ? 'pameran-event' : 'tanggal' }}">{{ $pameran ? ($acaraPameran?->nama ?? 'Acara tidak tersedia') : 'Memuat data sekolah...' }}</p></div>
        <div class="tv-tools"><div><span id="connection" class="connection waiting" role="status">Menghubungkan</span><p id="updated">Pembaruan setiap {{ $pameran ? 3 : 5 }} detik</p></div><button id="fullscreen" type="button" title="Layar penuh" aria-label="Layar penuh"><i data-lucide="maximize"></i></button></div>
    </header>
    @if($pameran)
    <div class="pameran-nav"><div role="tablist" aria-label="Monitor pameran"><button type="button" id="tab-siswa" role="tab" aria-selected="true" aria-controls="panel-siswa"><i data-lucide="school" aria-hidden="true"></i>Aktivitas siswa</button><button type="button" id="tab-tamu" role="tab" aria-selected="false" aria-controls="panel-tamu" tabindex="-1"><i data-lucide="users" aria-hidden="true"></i>Pengunjung stand</button></div><label><input type="checkbox" id="pameran-rotate">Rotasi 30 detik</label></div>
    <section id="panel-siswa" role="tabpanel" aria-labelledby="tab-siswa" class="pameran-panel">
    <form id="pameran-date-form" class="pameran-date"><div><strong>Data aktivitas</strong><p id="tanggal">Memuat tanggal terpilih...</p></div><label for="pameran-date">Tanggal<input id="pameran-date" type="date" min="{{ $batasTanggalPameran['min'] }}" max="{{ $batasTanggalPameran['max'] }}" value="{{ $tanggalPameran }}" required></label><button type="submit" id="pameran-date-save"><i data-lucide="calendar-check" aria-hidden="true"></i>Tampilkan</button><span id="pameran-date-status" role="status">{{ $layar->pameran_identitas ? 'Identitas diaktifkan oleh admin' : 'Identitas siswa disamarkan' }}</span><p id="pameran-date-error" class="pameran-date-error" role="alert" hidden></p></form>
    @endif
    <div id="notice" class="notice" role="status" hidden></div>
    <section class="kpis" aria-label="Ringkasan presensi hari ini">
        @foreach ([['hadir','Hadir','user-check','teal'],['izin','Izin','clipboard-check','blue'],['sakit','Sakit','heart-pulse','rose'],['alfa','Alfa tercatat','user-x','rose'],['belum','Belum tercatat','scan-line','neutral'],['terlambat','Terlambat','clock-3','yellow'],['laporan','Laporan masuk','message-square-warning','blue']] as [$key,$label,$icon,$tone])
        <button type="button" class="kpi {{ $tone }}" data-detail="{{ $key }}" aria-haspopup="dialog" title="Lihat rincian {{ strtolower($label) }}"><div><i data-lucide="{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></div><strong id="kpi-{{ $key }}">-</strong><small id="detail-{{ $key }}">Hari ini</small></button>
        @endforeach
    </section>
    <div class="tv-grid">
        <section class="chart-section attendance-section">
            <div class="section-heading"><div><p class="eyebrow">PRESENSI SEKOLAH</p><h2>Tren kehadiran</h2></div><div class="segments" aria-label="Periode grafik presensi"><button type="button" data-period="day" aria-pressed="true">Hari ini</button><button type="button" data-period="week" aria-pressed="false">Minggu</button><button type="button" data-period="month" aria-pressed="false">Bulan</button></div></div>
            <div class="chart-key"><span><b class="dot teal-dot"></b>Hadir</span><span><b class="dot yellow-dot"></b>Terlambat (termasuk hadir)</span></div>
            <div class="chart-area"><canvas id="attendance-chart" role="img" aria-label="Grafik kehadiran dan keterlambatan"></canvas><p id="attendance-empty" class="chart-empty" hidden></p></div>
            <div class="caption-action"><p id="attendance-caption" class="caption">Menunggu data presensi</p><button type="button" class="detail-icon" data-detail="dipantau" title="Rincian presensi hari ini" aria-label="Rincian presensi hari ini"><i data-lucide="list-filter"></i></button></div>
        </section>
        <section class="chart-section reports-section">
            <div class="section-heading"><div><p class="eyebrow" id="secondary-eyebrow">PELAPORAN SISWA</p><h2 id="secondary-title">Laporan masuk</h2></div><span class="tag" id="report-period">Minggu ini</span></div>
            <div class="segments chart-modes" aria-label="Jenis grafik batang"><button type="button" data-metric="laporan" aria-pressed="true"><i data-lucide="chart-no-axes-column"></i> Laporan</button><button type="button" data-metric="kelas" aria-pressed="false"><i data-lucide="school"></i> Per kelas</button></div>
            <div class="chart-area"><canvas id="reports-chart" role="img" aria-label="Jumlah laporan menurut tanggal masuk"></canvas></div>
            <div class="caption-action"><p class="caption" id="secondary-caption">Menurut waktu laporan masuk, termasuk yang belum diverifikasi.</p><button type="button" class="detail-icon" id="secondary-detail" data-detail="laporan" title="Rincian laporan hari ini" aria-label="Rincian laporan hari ini"><i data-lucide="list-filter"></i></button></div>
        </section>
        <section class="feed-section">
            <div class="section-heading"><div><p class="eyebrow">PEMANTAUAN LANGSUNG</p><h2>Laporan terbaru</h2></div><div class="heading-actions"><span class="tag yellow-tag" id="feed-count">0 laporan</span><button type="button" class="detail-icon" data-detail="laporan" title="Semua laporan hari ini" aria-label="Semua laporan hari ini"><i data-lucide="expand"></i></button></div></div>
            <div id="feed" class="feed"><p class="empty">Menunggu laporan siswa...</p></div>
            <p class="caption" id="feed-page">Belum diverifikasi tidak berarti terbukti melanggar.</p>
        </section>
        <section class="lower-section worship-section">
            <div class="section-heading"><h2><i data-lucide="hand-heart"></i> Ibadah hari ini</h2><div class="heading-actions"><span class="tag teal-tag" id="worship-count">-</span><button type="button" class="detail-icon" data-detail="ibadah" title="Rincian ibadah hari ini" aria-label="Rincian ibadah hari ini"><i data-lucide="expand"></i></button></div></div>
            <div id="worship"><p class="empty">Menunggu jadwal ibadah...</p></div>
            <p class="caption">Rekap peserta wajib yang hadir di sekolah.</p>
        </section>
        <section class="lower-section late-section">
            <div class="section-heading"><h2><i data-lucide="alarm-clock"></i> Keterlambatan</h2><div class="heading-actions"><span class="tag yellow-tag" id="late-total">-</span><button type="button" class="detail-icon" data-detail="terlambat" title="Semua siswa terlambat" aria-label="Semua siswa terlambat"><i data-lucide="expand"></i></button></div></div>
            <div id="late-list"><p class="empty">Menunggu data keterlambatan...</p></div>
            <p class="caption" id="late-caption">Keterlambatan dihitung sejak detik pertama.</p>
        </section>
        <section class="lower-section points-section">
            <div class="section-heading"><h2><i data-lucide="shield-check"></i> Poin resmi</h2><div class="heading-actions"><span class="tag teal-tag">Tahun aktif</span><button type="button" class="detail-icon" data-detail="poin" title="Rincian saldo poin resmi" aria-label="Rincian saldo poin resmi"><i data-lucide="expand"></i></button></div></div>
            <div class="points-balance"><strong id="point-balance">-</strong><span>saldo poin aktif<br><b id="point-students">- siswa</b></span></div>
            <div class="point-changes"><button type="button" data-detail="poin_tambah" title="Rincian penambahan poin hari ini">Penambahan hari ini <b id="point-added">-</b></button><button type="button" data-detail="poin_kurang" title="Rincian koreksi / pengurangan hari ini">Koreksi / pengurangan <b id="point-reduced">-</b></button></div>
            <p class="caption">Bersumber dari transaksi resmi, bukan usulan poin laporan.</p>
        </section>
    </div>
    <footer class="tv-footer"><span><b class="dot teal-dot"></b> {{ $pameran ? 'Mode pameran' : 'Ruang guru' }} &middot; <span id="year">NUSA</span></span><button type="button" id="population" data-detail="dipantau">- siswa dipantau</button><span>Data pribadi terbatas &middot; Pembaruan {{ $pameran ? 3 : 5 }} detik</span></footer>
    @if($pameran)</section>@include('monitoring-siswa._pameran-tamu')@endif
</main>
<dialog id="detail-dialog" class="detail-dialog" aria-labelledby="detail-title" aria-describedby="detail-subtitle">
    <header class="detail-header"><div><p class="eyebrow">MONITORING AKTIVITAS SISWA</p><h2 id="detail-title">Rincian siswa</h2><p id="detail-subtitle">Hari ini</p></div><button type="button" id="detail-close" class="detail-icon" title="Tutup rincian" aria-label="Tutup rincian" autofocus><i data-lucide="x"></i></button></header>
    <div class="detail-filters">
        <label class="detail-search">Cari nama<input type="search" id="detail-search" placeholder="Nama siswa" maxlength="100" autocomplete="off"></label>
        <label>Kelas<select id="detail-class"><option value="">Semua kelas</option></select></label>
        <label id="detail-activity-filter" hidden>Kegiatan<select id="detail-activity"><option value="">Semua kegiatan</option></select></label>
        <label id="detail-worship-filter" hidden>Status<select id="detail-worship"><option value="">Semua peserta wajib</option><option value="sudah">Sudah tercatat</option><option value="belum">Belum tercatat</option></select></label>
        <button type="button" id="detail-reset" class="detail-icon" title="Reset filter" aria-label="Reset filter"><i data-lucide="rotate-ccw"></i></button>
    </div>
    <div class="detail-summary"><strong id="detail-count" role="status">Memuat rincian...</strong><span id="detail-updated"></span></div>
    <p id="detail-message" class="detail-message" role="status" hidden></p>
    <div id="detail-scroll" class="detail-scroll" aria-busy="false">
        <table class="detail-table"><thead><tr><th>Siswa</th><th>Kelas</th><th>Status</th><th>Waktu</th><th>Keterangan</th></tr></thead><tbody id="detail-rows"></tbody></table>
        <p id="detail-empty" class="empty" hidden>Tidak ada data yang sesuai.</p>
    </div>
    <footer class="detail-footer"><p id="detail-context">Data hari ini</p><nav aria-label="Halaman rincian"><button type="button" id="detail-prev" class="detail-icon" title="Halaman sebelumnya" aria-label="Halaman sebelumnya"><i data-lucide="chevron-left"></i></button><span id="detail-page">1 / 1</span><button type="button" id="detail-next" class="detail-icon" title="Halaman berikutnya" aria-label="Halaman berikutnya"><i data-lucide="chevron-right"></i></button></nav></footer>
</dialog>
@if($pameran) @include('monitoring-siswa._pameran-rincian') @endif
</body>
</html>
