<!DOCTYPE html>
<html lang="id" translate="no" class="notranslate">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="google" content="notranslate">
    <title>Monitoring TV Ujian Terpusat - NUSA</title>
    <link rel="icon" href="{{ asset('images/logo-nusa.png') }}">@fonts
    <link rel="stylesheet" href="{{ asset('css/monitoring-tv.css') }}?v={{ filemtime(public_path('css/monitoring-tv.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/monitoring-ujian-tv.css') }}?v={{ filemtime(public_path('css/monitoring-ujian-tv.css')) }}">
    <script defer src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
    <script defer src="{{ asset('vendor/lucide/lucide.min.js') }}"></script>
    <script defer src="{{ asset('js/monitoring-ujian-tv-rincian.js') }}?v={{ filemtime(public_path('js/monitoring-ujian-tv-rincian.js')) }}"></script>
    <script defer src="{{ asset('js/monitoring-ujian-tv.js') }}?v={{ filemtime(public_path('js/monitoring-ujian-tv.js')) }}"></script>
</head>
<body class="exam-tv" data-endpoint="{{ route('monitoring-ujian-tv.data') }}">
<main class="tv-screen">
    <header class="tv-header">
        <div class="brand"><img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA" width="48" height="48"><div><strong>NUSA<span>.</span></strong><p>Monitoring ujian terpusat</p></div></div>
        <div class="tv-heading"><h1 id="exam-name">Pusat pantau ujian</h1><p id="exam-schedule">Menghubungkan ke kegiatan ujian...</p></div>
        <div class="tv-tools"><div><span id="connection" class="connection waiting" role="status">Menghubungkan</span><p id="updated">Pembaruan 5 detik</p></div><button id="fullscreen" type="button" aria-label="Layar penuh" title="Layar penuh"><i data-lucide="maximize"></i></button></div>
    </header>
    <div id="notice" class="notice" role="status" hidden></div>
    <div class="exam-toolbar">
        <div class="exam-filters"><label for="jadwal">Jadwal<select id="jadwal"><option value="">Otomatis: jadwal hari ini</option></select></label><label for="sesi">Sesi<select id="sesi"><option value="">Semua sesi paket ini</option></select></label></div>
        <div class="view-controls"><div class="segments" aria-label="Tampilan monitoring"><button type="button" data-mode="overview" aria-pressed="true"><i data-lucide="layout-grid"></i> Semua ruang</button><button type="button" data-mode="detail" aria-pressed="false"><i data-lucide="list"></i> Detail ruang</button></div><span id="server-clock">--:--:--</span></div>
    </div>
    <section class="kpis" aria-label="Ringkasan peserta ujian">
        @foreach ([['total','Peserta terdaftar','users','blue'],['mengerjakan','Mengerjakan','pencil-line','teal'],['selesai','Selesai','circle-check','teal'],['menunggu','Belum mulai','timer','yellow'],['tidak_hadir','Tidak hadir','user-minus','neutral'],['perlu_perhatian','Perlu perhatian','triangle-alert','rose']] as [$key,$label,$icon,$tone])
        <button type="button" class="kpi {{ $tone }}" data-exam-detail="{{ $key }}" aria-haspopup="dialog" aria-controls="exam-detail-dialog" title="Rincian {{ strtolower($label) }}"><div><i data-lucide="{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></div><strong id="stat-{{ $key }}">-</strong><small id="hint-{{ $key }}">Jadwal terpilih</small></button>
        @endforeach
    </section>
    <div class="exam-layout">
        <section class="exam-workspace">
            <div class="workspace-heading"><h2 id="workspace-title">Situasi ruang ujian</h2><div class="room-controls" id="room-controls" hidden><label class="sr-only" for="room">Pilih ruang</label><select id="room"></select><label class="auto-label"><input id="auto-room" type="checkbox" checked> Bergilir</label></div><div class="heading-actions"><span class="tag" id="room-page">- ruang</span><button type="button" id="workspace-detail" class="detail-icon" data-exam-detail="total" title="Rincian peserta" aria-label="Rincian peserta"><i data-lucide="expand"></i></button></div></div>
            <p id="room-meta" class="room-meta" hidden></p>
            <div id="room-grid" class="room-grid"><p class="empty">Menunggu data ruang...</p></div>
            <div id="participant-table" class="participant-table" hidden><table><thead><tr><th>Meja</th><th>Peserta</th><th>Status / presensi ujian</th><th>Jawaban</th><th>Sisa waktu</th><th>Komunikasi</th></tr></thead><tbody id="participants"></tbody></table></div>
            <p class="caption" id="workspace-caption">Selesai mengerjakan tidak berarti hasil sudah difinalisasi.</p>
        </section>
        <aside class="exam-sidebar">
            <section class="attention-section"><div class="section-heading"><h2><i data-lucide="triangle-alert"></i> Perlu perhatian</h2><div class="heading-actions"><span class="tag yellow-tag" id="attention-count">-</span><button type="button" class="detail-icon" data-exam-detail="perlu_perhatian" title="Semua peserta perlu perhatian" aria-label="Semua peserta perlu perhatian"><i data-lucide="expand"></i></button></div></div><div id="attention-list" class="event-list"><p class="empty">Menunggu data...</p></div><p class="caption">Indikator pemeriksaan, bukan vonis pelanggaran.</p></section>
            <section class="activity-section"><div class="section-heading"><h2><i data-lucide="activity"></i> Aktivitas terbaru</h2><button type="button" class="detail-icon" data-exam-detail="aktivitas" title="Rincian aktivitas terbaru" aria-label="Rincian aktivitas terbaru"><i data-lucide="expand"></i></button></div><div id="activity-list" class="event-list"><p class="empty">Belum ada aktivitas.</p></div><p class="caption" id="activity-page">Perubahan dari server ujian</p></section>
        </aside>
        <div class="exam-charts">
            <section class="exam-chart"><div class="section-heading"><h2>Pengerjaan per ruang</h2><div class="heading-actions"><span class="tag" id="chart-room-page">-</span><button type="button" class="detail-icon" data-exam-detail="total" title="Rincian peserta per ruang" aria-label="Rincian peserta per ruang"><i data-lucide="list-filter"></i></button></div></div><div class="exam-chart-canvas"><canvas id="room-chart" role="img" aria-label="Grafik status peserta per ruang"></canvas></div></section>
            <section class="exam-chart"><div class="section-heading"><h2>Perkembangan selesai</h2><div class="heading-actions"><span class="tag teal-tag">Akumulasi</span><button type="button" class="detail-icon" data-exam-detail="selesai" title="Rincian peserta selesai" aria-label="Rincian peserta selesai"><i data-lucide="list-filter"></i></button></div></div><div class="exam-chart-canvas"><canvas id="finish-chart" role="img" aria-label="Grafik akumulasi peserta selesai"></canvas></div></section>
        </div>
    </div>
    <footer class="tv-footer"><span><b class="dot teal-dot"></b> <span id="footer-name">Ruang guru</span></span><span id="footer-count">Monitoring baca-saja</span><span>Jawaban tersimpan di server &middot; Pembaruan 5 detik</span></footer>
</main>
<dialog id="exam-detail-dialog" class="detail-dialog exam-detail-dialog" aria-labelledby="exam-detail-title" aria-describedby="exam-detail-subtitle">
    <header class="detail-header"><div><p class="eyebrow">MONITORING UJIAN TERPUSAT</p><h2 id="exam-detail-title">Rincian peserta</h2><p id="exam-detail-subtitle">Jadwal terpilih</p></div><button type="button" id="exam-detail-close" class="detail-icon" title="Tutup rincian" aria-label="Tutup rincian" autofocus><i data-lucide="x"></i></button></header>
    <div class="exam-detail-room" id="exam-detail-room" hidden></div>
    <div class="detail-filters">
        <label class="detail-search">Cari nama<input type="search" id="exam-detail-search" maxlength="100" placeholder="Nama peserta" autocomplete="off"></label>
        <div id="exam-detail-extra-filters" class="exam-detail-filter-options">
            <label>Ruang<select id="exam-detail-room-filter"><option value="">Semua ruang</option></select></label>
            <label>Kelas<select id="exam-detail-class"><option value="">Semua kelas</option></select></label>
            <label id="exam-detail-status-label">Status<select id="exam-detail-status"><option value="">Semua status</option><option value="mengerjakan">Mengerjakan</option><option value="selesai">Selesai</option><option value="menunggu">Belum mulai</option><option value="belum_hadir">Belum hadir</option><option value="hadir_belum_mulai">Hadir, belum mulai</option><option value="tidak_hadir">Tidak hadir</option><option value="perlu_perhatian">Perlu perhatian</option><option value="terblokir">Terblokir</option><option value="nonaktif">Nonaktif</option></select></label>
        </div>
        <button type="button" id="exam-detail-filters-toggle" class="detail-icon exam-detail-filter-toggle" title="Filter rincian" aria-label="Filter rincian" aria-expanded="false" aria-controls="exam-detail-extra-filters"><i data-lucide="sliders-horizontal"></i></button>
        <button type="button" id="exam-detail-reset" class="detail-icon" title="Reset filter" aria-label="Reset filter"><i data-lucide="rotate-ccw"></i></button>
    </div>
    <div class="detail-summary"><strong id="exam-detail-count" role="status">Menunggu data...</strong><span id="exam-detail-updated"></span></div>
    <p id="exam-detail-message" class="detail-message" role="status" hidden></p>
    <div id="exam-detail-scroll" class="detail-scroll">
        <table class="detail-table exam-detail-table"><thead id="exam-detail-head"></thead><tbody id="exam-detail-rows"></tbody></table>
        <p id="exam-detail-empty" class="empty" hidden>Tidak ada peserta yang sesuai.</p>
    </div>
    <footer class="detail-footer"><p id="exam-detail-context">Monitoring baca-saja. Tidak mengubah pengerjaan atau hasil ujian.</p><nav aria-label="Halaman rincian ujian"><button type="button" id="exam-detail-prev" class="detail-icon" title="Halaman sebelumnya" aria-label="Halaman sebelumnya"><i data-lucide="chevron-left"></i></button><span id="exam-detail-page">1 / 1</span><button type="button" id="exam-detail-next" class="detail-icon" title="Halaman berikutnya" aria-label="Halaman berikutnya"><i data-lucide="chevron-right"></i></button></nav></footer>
</dialog>
</body></html>
