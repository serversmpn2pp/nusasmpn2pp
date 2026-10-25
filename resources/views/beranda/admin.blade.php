@extends('layouts.app')

@section('title', 'Dashboard Admin - NUSA')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard-admin.css') }}?v={{ filemtime(public_path('css/dashboard-admin.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard-admin-layout.css') }}?v={{ filemtime(public_path('css/dashboard-admin-layout.css')) }}">
@endpush

@section('content')
    @php
        $jumlah = fn ($nilai) => number_format((float) $nilai, 0, ',', '.');
        $persen = fn ($nilai) => number_format((float) $nilai, 2, ',', '.');
        $rekap = $grafikPresensi['day']['summary'];
        $laporanMenunggu = $ringkasanPembinaan['menunggu_bk'] + $ringkasanPembinaan['menunggu_wakil'];
        $perhatian = [
            ['jumlah' => $rekap['pending'], 'judul' => 'siswa belum scan', 'catatan' => 'Periksa konfirmasi kehadiran hari ini', 'ikon' => 'scan-line', 'warna' => 'yellow', 'url' => route('rekap-absensi-harian.index', ['tanggal' => $hariIni->toDateString(), 'status' => 'belum_scan'])],
            ['jumlah' => $laporanMenunggu, 'judul' => 'laporan pembinaan', 'catatan' => 'Menunggu pemeriksaan dan pengesahan', 'ikon' => 'clipboard-pen-line', 'warna' => 'blue', 'url' => route('laporan-pembinaan-siswa.index')],
            ['jumlah' => $ringkasanAkademik['komponen_belum_terisi'], 'judul' => 'komponen nilai kosong', 'catatan' => 'Belum ada nilai yang dimasukkan', 'ikon' => 'notebook-pen', 'warna' => 'teal', 'url' => route('komponen-nilai.index')],
        ];
        $judulRingkasan = ['present' => 'Hadir', 'permit' => 'Izin', 'sick' => 'Sakit', 'absent' => 'Alfa', 'pending' => 'Belum scan', 'excluded' => 'Pengecualian'];
    @endphp
    <div class="admin-dashboard" data-admin-dashboard>
        <section class="page-heading" aria-labelledby="page-title">
            <div><p class="eyebrow">SMP NEGERI 2 PADANG PANJANG</p><h1 id="page-title">Dashboard admin</h1><p class="greeting">{{ $hariIni->hour < 11 ? 'Selamat pagi' : ($hariIni->hour < 15 ? 'Selamat siang' : ($hariIni->hour < 18 ? 'Selamat sore' : 'Selamat malam')) }}, {{ auth()->user()->nama }}.</p></div>
            <div class="heading-meta"><span class="year-badge"><i data-lucide="calendar-days" aria-hidden="true"></i>{{ $tahunPelajaranAktif?->nama ?? 'Tahun ajaran belum diatur' }}<span class="meta-dot"></span>{{ $hariIni->month >= 7 ? 'Ganjil' : 'Genap' }}</span><span class="heading-date">{{ $hariIni->copy()->locale('id')->translatedFormat('l, j F Y') }}</span></div>
        </section>
        <section class="school-stats" aria-label="Ringkasan data sekolah">
            @foreach ([['Siswa aktif', $ringkasanUtama['siswa_aktif'], 'Terdaftar di sekolah', 'graduation-cap', 'blue'], ['Pegawai aktif', $ringkasanUtama['pegawai_aktif'], 'Guru dan tenaga kependidikan', 'users-round', 'teal'], ['Kelas aktif', $ringkasanUtama['kelas_aktif'], 'Kelas yang sedang aktif', 'door-open', 'yellow'], ['Mata pelajaran', $ringkasanUtama['mata_pelajaran_aktif'], 'Mata pelajaran aktif', 'notebook-tabs', 'white']] as [$label, $nilai, $catatan, $ikon, $warna])
                <article class="stat stat-{{ $warna }}"><div class="stat-copy"><p>{{ $label }}</p><strong>{{ $jumlah($nilai) }}</strong><small>{{ $catatan }}</small></div><span class="stat-icon"><i data-lucide="{{ $ikon }}" aria-hidden="true"></i></span></article>
            @endforeach
        </section>
        <section class="attendance-section" aria-labelledby="attendance-title">
            <div class="attendance-main">
                <div class="section-heading"><div><p class="section-kicker">PRESENSI SISWA</p><h2 id="attendance-title">Statistik kehadiran</h2></div><span class="update-time"><span></span>Rekap {{ $hariIni->format('H.i') }} WIB</span></div>
                <div class="chart-toolbar"><div class="period-switch" role="group" aria-label="Periode kehadiran"><button type="button" data-period="day" aria-pressed="true">Hari ini</button><button type="button" data-period="week" aria-pressed="false">Mingguan</button><button type="button" data-period="month" aria-pressed="false">Bulanan</button></div><div class="chart-tools"><div class="chart-types" role="group" aria-label="Jenis grafik"><button class="icon-button" data-chart-type="bar" type="button" aria-label="Grafik batang" aria-pressed="false" data-admin-tooltip="Grafik batang"><i data-lucide="chart-column-increasing" aria-hidden="true"></i></button><button class="icon-button" data-chart-type="line" type="button" aria-label="Grafik garis" aria-pressed="true" data-admin-tooltip="Grafik garis"><i data-lucide="chart-no-axes-combined" aria-hidden="true"></i></button></div><button class="icon-button" id="chart-table" type="button" aria-label="Lihat data grafik" data-admin-tooltip="Lihat data grafik"><i data-lucide="table-2" aria-hidden="true"></i></button></div></div>
                <div class="chart-caption"><p id="chart-subtitle">{{ $grafikPresensi['day']['subtitle'] }}</p><span id="chart-date">{{ $grafikPresensi['day']['date'] }}</span></div>
                <div class="chart-container"><canvas id="attendance-chart" role="img" aria-label="Grafik kehadiran siswa"></canvas><div class="chart-empty" id="chart-empty" @if (! $grafikPresensi['day']['empty']) hidden @endif><i data-lucide="chart-no-axes-combined" aria-hidden="true"></i><p>{{ $grafikPresensi['day']['empty'] }}</p></div></div>
                <p class="sr-only" id="chart-description"></p>
                <p class="untimed-note" id="chart-untimed" hidden></p>
                <div class="chart-bottom"><div class="chart-legend" role="group" aria-label="Seri grafik"><button class="legend-button" type="button" data-series="0" aria-pressed="true"><span class="legend-line legend-present"></span>Hadir</button><button class="legend-button" type="button" data-series="1" aria-pressed="true" data-admin-tooltip="Siswa terlambat sudah termasuk dalam jumlah hadir, bukan tambahan siswa."><span class="legend-line legend-late"></span>Terlambat<i data-lucide="info" class="tiny-icon" aria-hidden="true"></i></button></div><span class="chart-unit" id="chart-unit">Jumlah siswa</span></div>
            </div>
            <aside class="attendance-summary" aria-labelledby="summary-title">
                <div class="summary-heading"><h3 id="summary-title">Kehadiran hari ini</h3><span class="summary-symbol"><i data-lucide="user-check" aria-hidden="true"></i></span></div>
                <div class="attendance-rate"><strong id="attendance-rate">{{ $rekap['opportunities'] ? $persen($rekap['present'] / $rekap['opportunities'] * 100) : '-' }}@if ($rekap['opportunities'])<span>%</span>@endif</strong><span id="summary-basis">{{ $jumlah($rekap['present']) }} dari {{ $jumlah($rekap['opportunities']) }} siswa</span></div>
                <div class="attendance-track" id="attendance-track" aria-hidden="true"></div>
                <dl class="attendance-breakdown">
                    @foreach ($judulRingkasan as $key => $label)
                        <div @if ($key === 'excluded') id="excluded-row" @if (! $rekap['excluded']) hidden @endif @endif><dt><span class="status-dot dot-{{ $key }}"></span>{{ $label }}@if ($key === 'pending')<button class="info-button" type="button" aria-label="Tentang belum scan" data-admin-tooltip="Belum scan belum dianggap alfa otomatis selama hari presensi masih berjalan."><i data-lucide="info" aria-hidden="true"></i></button>@endif</dt><dd id="count-{{ $key }}">{{ $jumlah($rekap[$key]) }}</dd></div>
                    @endforeach
                </dl>
                <div class="late-summary"><i data-lucide="clock-3" aria-hidden="true"></i><div><strong><span id="count-late">{{ $jumlah($rekap['late']) }}</span> terlambat</strong><span>Termasuk dalam jumlah hadir</span></div></div>
                <a class="text-button summary-link" href="{{ route('rekap-absensi-harian.index') }}" id="attendance-detail" data-day-url="{{ route('rekap-absensi-harian.index', ['tanggal' => $hariIni->toDateString()]) }}" data-week-url="{{ route('laporan-absensi.index', ['periode' => 'rentang', 'tanggal_mulai' => $hariIni->copy()->startOfWeek()->toDateString(), 'tanggal_selesai' => $hariIni->toDateString(), 'tahun_pelajaran_id' => $tahunPelajaranAktif?->id]) }}" data-month-url="{{ route('laporan-absensi.index', ['periode' => 'bulanan', 'bulan' => $hariIni->format('Y-m'), 'tahun_pelajaran_id' => $tahunPelajaranAktif?->id]) }}">Rincian kehadiran<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
            </aside>
        </section>
        <noscript><p class="admin-noscript">Grafik interaktif memerlukan JavaScript. <a href="{{ route('laporan-absensi.index') }}">Buka laporan presensi</a>.</p></noscript>
        <section class="attention-section" aria-labelledby="attention-title"><div class="section-heading compact-heading"><h2 id="attention-title">Perlu perhatian</h2><span class="small-muted">{{ collect($perhatian)->where('jumlah', '>', 0)->count() }} hal perlu ditinjau</span></div><div class="attention-list">@foreach ($perhatian as $item)<a class="attention-item" href="{{ $item['url'] }}"><span class="attention-icon attention-{{ $item['warna'] }}"><i data-lucide="{{ $item['ikon'] }}" aria-hidden="true"></i></span><span class="attention-copy"><strong>{{ $jumlah($item['jumlah']) }} {{ $item['judul'] }}</strong><small>{{ $item['catatan'] }}</small></span><i data-lucide="arrow-right" class="attention-arrow" aria-hidden="true"></i></a>@endforeach</div></section>
        <div class="secondary-charts">
            @include('beranda.partials.grafik-admin', ['kode' => 'pegawai', 'kategori' => 'PRESENSI PEGAWAI', 'judul' => 'Kehadiran pegawai', 'ikon' => 'contact-round', 'data' => $grafikTambahan['pegawai'], 'url' => route('rekap-absensi-pegawai-harian.index'), 'tautan' => 'Rincian presensi pegawai'])
            @include('beranda.partials.grafik-admin', ['kode' => 'laporan', 'kategori' => 'KESISWAAN & BK', 'judul' => 'Laporan siswa', 'ikon' => 'clipboard-pen-line', 'data' => $grafikTambahan['laporan'], 'url' => route('laporan-pembinaan-siswa.index', ['tahun_pelajaran_id' => $tahunPelajaranAktif?->id]), 'tautan' => 'Semua laporan siswa'])
        </div>
        <div class="activity-band">
            <section class="activity-section" aria-labelledby="activity-title"><div class="section-heading compact-heading"><div><p class="section-kicker">PRESENSI TERAKHIR</p><h2 id="activity-title">Aktivitas siswa</h2></div><span class="activity-label"><span></span>Hari ini</span></div><div class="activity-list">
                @forelse ($logScanTerakhir as $log)
                    @php
                        $inisial = collect(preg_split('/\s+/', trim($log->siswa?->nama_lengkap ?? 'Siswa')))->take(2)->map(fn ($kata) => mb_strtoupper(mb_substr($kata, 0, 1)))->implode('');
                    @endphp
                    <div class="activity-row"><span class="student-avatar {{ $loop->index % 3 === 0 ? 'student-teal' : ($loop->index % 3 === 1 ? 'student-blue' : 'student-yellow') }}">{{ $inisial }}</span><span class="student-copy"><strong>{{ $log->siswa?->nama_lengkap ?? 'Siswa tidak ditemukan' }}</strong><small>{{ $log->absensiSiswa?->kelas?->nama ?? '-' }}<span class="activity-status {{ ! $log->berhasil ? 'status-failed' : ($log->absensiSiswa?->menit_terlambat > 0 && $log->jenis_scan === 'masuk' ? 'status-late' : '') }}">{{ ! $log->berhasil ? 'Scan gagal' : ($log->jenis_scan === 'pulang' ? 'Pulang' : ($log->absensiSiswa?->menit_terlambat > 0 ? 'Terlambat' : 'Datang')) }}</span></small></span><time datetime="{{ $log->waktu_scan->toIso8601String() }}">{{ $log->waktu_scan->format('H.i') }}</time><i data-lucide="{{ $log->berhasil ? 'check' : 'x' }}" class="activity-check" aria-label="{{ $log->berhasil ? 'Scan berhasil' : 'Scan gagal' }}"></i></div>
                @empty
                    <div class="admin-activity-empty"><i data-lucide="scan-line" aria-hidden="true"></i><p>Belum ada aktivitas scan siswa hari ini.</p></div>
                @endforelse
            </div><a class="text-button" href="{{ route('rekap-absensi-harian.index') }}">Semua aktivitas presensi<i data-lucide="arrow-up-right" aria-hidden="true"></i></a></section>
        </div>
        <footer class="page-footer"><span>&copy; {{ $hariIni->year }} NUSA <span class="footer-dot">/</span> SMP Negeri 2 Padang Panjang</span><span>Satu Nusa, Terhubung untuk <strong>Bertumbuh</strong></span></footer>
    </div>
    <dialog id="admin-chart-dialog" aria-labelledby="admin-chart-dialog-title"><div class="dialog-header"><div><p class="section-kicker" id="admin-chart-dialog-category">PRESENSI SISWA</p><h2 id="admin-chart-dialog-title">Data statistik kehadiran</h2></div><button class="icon-button" type="button" id="admin-chart-close" aria-label="Tutup rincian"><i data-lucide="x" aria-hidden="true"></i></button></div><div class="dialog-content" id="admin-chart-content"></div></dialog>
    <div class="sr-only" id="dashboard-status" role="status" aria-live="polite"></div>
    <script type="application/json" id="admin-attendance-data">{!! json_encode($grafikPresensi, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}" defer></script>
    <script src="{{ asset('js/dashboard-admin.js') }}?v={{ filemtime(public_path('js/dashboard-admin.js')) }}" defer></script>
@endpush
