@extends('layouts.app')

@section('title', 'Dashboard Pegawai - NUSA')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard-pegawai.css') }}?v={{ filemtime(public_path('css/dashboard-pegawai.css')) }}">
@endpush

@section('content')
    @php
        $angka = fn ($nilai) => number_format($nilai, 0, ',', '.');
        $jam = fn ($nilai) => $nilai ? substr($nilai, 0, 5) : '-';
        $rekap = $ringkasanAbsensiPegawaiPribadi;
        $presensi = $presensiPegawaiHariIni;
        $statusHariIni = $presensi?->labelStatusKehadiran() ?? 'Belum tercatat';
        $warnaHariIni = $presensi?->status_kehadiran === 'hadir' ? 'teal' : 'amber';
        $bolehRekapPribadi = auth()->user()->memilikiIzin('absensi_pegawai.pribadi');
    @endphp

    <div class="pegawai-home" data-pegawai-dashboard>
        @if (session('berhasil'))
            <div class="alert" role="status">{{ session('berhasil') }}</div>
        @endif

        <header class="ph-heading">
            <div>
                <p class="ph-eyebrow"><i data-lucide="layout-dashboard" aria-hidden="true"></i> Dashboard Pegawai</p>
                <h1>Selamat datang, {{ $pegawaiLogin?->nama_lengkap ?? auth()->user()->nama }}</h1>
                <p class="ph-date">{{ $hariIni->copy()->locale('id')->translatedFormat('l, d F Y') }} <span aria-hidden="true">&middot;</span> {{ $tahunPelajaranAktif?->nama ?? 'Belum ada tahun pelajaran aktif' }}</p>
            </div>
            <div class="ph-heading-side">
                <span class="ph-badge {{ $warnaHariIni }}"><i data-lucide="{{ $presensi ? 'badge-check' : 'clock-3' }}" aria-hidden="true"></i> {{ $statusHariIni }}</span>
                <small>{{ $presensi?->jam_masuk ? 'Masuk '.$jam($presensi->jam_masuk) : 'Belum ada scan masuk' }} &middot; {{ $presensi?->jam_pulang ? 'Pulang '.$jam($presensi->jam_pulang) : 'Belum scan pulang' }}</small>
            </div>
        </header>

        @if (! $pegawaiLogin)
            <div class="alert alert-warning" role="status">Akun belum terhubung ke data pegawai. Hubungi operator sekolah untuk melengkapi profil.</div>
        @endif

        <section class="ph-stats" aria-label="Ringkasan pribadi {{ $labelBulan }}">
            @foreach ([
                ['label' => 'Hadir', 'nilai' => $rekap['hadir'], 'satuan' => 'hari', 'catatan' => $labelBulan, 'ikon' => 'calendar-check-2'],
                ['label' => 'Terlambat', 'nilai' => $rekap['terlambat'], 'satuan' => 'kali', 'catatan' => $angka($rekap['total_menit_terlambat']).' menit selama '.$labelBulan, 'ikon' => 'alarm-clock'],
                ['label' => 'Izin / sakit', 'nilai' => $rekap['izin'] + $rekap['sakit'], 'satuan' => 'hari', 'catatan' => $angka($rekap['izin']).' izin, '.$angka($rekap['sakit']).' sakit', 'ikon' => 'heart-pulse'],
                ['label' => 'Tindak lanjut', 'nilai' => $jumlahTugasPegawai, 'satuan' => 'tindakan', 'catatan' => 'Menunggu tindakan Anda', 'ikon' => 'clipboard-list'],
            ] as $item)
                <article class="ph-stat">
                    <div class="ph-stat-label">{{ $item['label'] }} <i data-lucide="{{ $item['ikon'] }}" aria-hidden="true"></i></div>
                    <p><strong>{{ $angka($item['nilai']) }}</strong><span>{{ $item['satuan'] }}</span></p>
                    <small>{{ $item['catatan'] }}</small>
                </article>
            @endforeach
        </section>

        <div class="ph-row">
            <section class="ph-section" aria-labelledby="ph-agenda-title">
                <header class="ph-section-head">
                    <h2 id="ph-agenda-title"><i data-lucide="calendar-days" aria-hidden="true"></i> Agenda hari ini</h2>
                    @izin('jadwal.pribadi')
                        <a class="ph-link" href="{{ route('jadwal-saya.index') }}">Jadwal saya <i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
                    @endizin
                </header>
                <div class="ph-section-body">
                    @if ($jadwalPiketHariIni && auth()->user()->memilikiIzin('piket_guru.lihat_pribadi'))
                        <a class="ph-duty" href="{{ route('piket-kehadiran-siswa.index') }}"><i data-lucide="clipboard-check" aria-hidden="true"></i><span><strong>Anda bertugas piket hari ini</strong><small>{{ $jadwalPiketHariIni->keterangan ?: 'Pemeriksaan kehadiran siswa' }}</small></span><i data-lucide="arrow-right" aria-hidden="true"></i></a>
                    @endif
                    @forelse ($agendaPegawai as $agenda)
                        <a class="ph-schedule" href="{{ $agenda['url'] }}">
                            <div class="ph-time">{{ $agenda['mulai'] }} @if ($agenda['selesai'])<small>{{ $agenda['selesai'] }}</small>@endif</div>
                            <div><strong>{{ $agenda['judul'] }}</strong><p>{{ $agenda['detail'] }}</p></div>
                            <span class="ph-badge {{ $agenda['warna'] }}">{{ $agenda['status'] }}</span>
                        </a>
                    @empty
                        <div class="ph-empty"><i data-lucide="calendar-check" aria-hidden="true"></i><p>Belum ada agenda terjadwal untuk hari ini.</p></div>
                    @endforelse
                </div>
            </section>

            <section class="ph-section" aria-labelledby="ph-tasks-title">
                <header class="ph-section-head"><h2 id="ph-tasks-title"><i data-lucide="list-todo" aria-hidden="true"></i> Perlu ditindaklanjuti</h2><span class="ph-badge blue">{{ $angka($jumlahTugasPegawai) }}</span></header>
                <div class="ph-section-body">
                    @forelse ($tugasPegawai as $tugas)
                        <a class="ph-task" href="{{ $tugas['url'] }}"><span class="ph-task-icon"><i data-lucide="{{ $tugas['ikon'] }}" aria-hidden="true"></i></span><span class="ph-task-text"><strong>{{ $tugas['label'] }}</strong><small>{{ $tugas['jumlah'] }} &middot; {{ $tugas['detail'] }}</small></span><i data-lucide="chevron-right" aria-hidden="true"></i></a>
                    @empty
                        <div class="ph-empty"><i data-lucide="circle-check" aria-hidden="true"></i><p>Tidak ada tugas tertunda dari penugasan nilai, surat, atau supervisi Anda.</p></div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="ph-row">
            <section class="ph-section" aria-labelledby="ph-chart-title" data-grafik-kedatangan>
                <header class="ph-section-head"><h2 id="ph-chart-title"><i data-lucide="chart-no-axes-combined" aria-hidden="true"></i> Riwayat kedatangan</h2>@if ($bolehRekapPribadi)<a class="ph-link" href="{{ route('absensi-pegawai-saya.rekap') }}">Rincian <i data-lucide="arrow-up-right" aria-hidden="true"></i></a>@endif</header>
                <div class="ph-chart-body">
                    <div class="ph-chart-toolbar">
                        <div class="ph-segment" role="group" aria-label="Rentang riwayat kedatangan"><button type="button" data-chart-range="minggu" aria-pressed="true">7 hari</button><button type="button" data-chart-range="bulan" aria-pressed="false">Bulan ini</button></div>
                        <div class="ph-segment ph-chart-modes" role="group" aria-label="Jenis grafik"><button type="button" data-chart-type="bar" aria-pressed="true" title="Grafik batang" aria-label="Grafik batang"><i data-lucide="chart-column" aria-hidden="true"></i></button><button type="button" data-chart-type="line" aria-pressed="false" title="Grafik garis" aria-label="Grafik garis"><i data-lucide="chart-line" aria-hidden="true"></i></button></div>
                    </div>
                    <p class="ph-chart-summary" data-chart-summary aria-live="polite"></p>
                    <div class="ph-canvas"><canvas data-chart-canvas role="img" aria-label="Grafik durasi keterlambatan pribadi dalam menit"></canvas><div class="ph-chart-empty" data-chart-empty hidden>Belum ada scan kedatangan pada rentang ini.</div></div>
                    <div class="ph-chart-legend"><span><b class="ph-dot teal"></b> Tepat waktu</span><span><b class="ph-dot amber"></b> Terlambat</span><span>Tanpa catatan tidak dihitung hadir</span></div>
                    <details class="ph-chart-data"><summary>Data kedatangan</summary><ul data-chart-table></ul></details>
                    <noscript><p class="ph-muted">Grafik memerlukan JavaScript. Rincian presensi tersedia pada menu Kehadiran Saya.</p></noscript>
                </div>
                <script type="application/json" data-chart-source>@json($grafikKedatanganPegawai)</script>
            </section>

            <section class="ph-section" aria-labelledby="ph-presensi-title">
                <header class="ph-section-head"><h2 id="ph-presensi-title"><i data-lucide="scan-line" aria-hidden="true"></i> Presensi hari ini</h2><span class="ph-badge {{ $warnaHariIni }}">{{ $statusHariIni }}</span></header>
                <div class="ph-presensi">
                    <div class="ph-presensi-main"><span>Jam masuk</span><strong>{{ $jam($presensi?->jam_masuk) }}</strong><small>Batas tepat waktu {{ $jam($jadwalPresensiPegawai?->jam_masuk) }}</small></div>
                    <dl class="ph-facts">
                        <div><dt>Keterlambatan</dt><dd>{{ $presensi ? $angka(max(0, (int) $presensi->menit_terlambat)).' menit' : '-' }}</dd></div>
                        <div><dt>Jam pulang</dt><dd>{{ $presensi?->jam_pulang ? $jam($presensi->jam_pulang) : 'Belum tercatat' }}</dd></div>
                        <div><dt>Jadwal pulang</dt><dd>{{ $jam($jadwalPresensiPegawai?->jam_pulang) }}</dd></div>
                    </dl>
                    @if (! $jadwalPresensiPegawai)<p class="ph-muted">Tidak ada jadwal presensi aktif untuk hari ini.</p>@endif
                </div>
                <div class="ph-status-footer" aria-label="Status lain pada {{ $labelBulan }}">@foreach (['alfa' => 'Alfa', 'dinas_luar' => 'Dinas luar', 'cuti' => 'Cuti', 'pulang_cepat' => 'Pulang cepat'] as $key => $label)<span>{{ $label }} <strong>{{ $angka($rekap[$key]) }}</strong></span>@endforeach</div>
            </section>
        </div>

        <nav class="ph-actions" aria-label="Layanan pegawai">
            @foreach ($aksiPegawai as $aksi)<a href="{{ $aksi['url'] }}"><i data-lucide="{{ $aksi['ikon'] }}" aria-hidden="true"></i>{{ $aksi['label'] }}<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>@endforeach
            @if ($bolehRekapPribadi)<a href="{{ route('absensi-pegawai-saya.rekap') }}"><i data-lucide="calendar-check-2" aria-hidden="true"></i>Rekap saya<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>@endif
        </nav>

        @include('beranda.partials.perwalian-pegawai')

        @izin('piket_guru.lihat_pribadi')
            @if ($jadwalPiketSaya->isNotEmpty())
                <section class="ph-section"><header class="ph-section-head"><h2><i data-lucide="clipboard-check" aria-hidden="true"></i> Jadwal Piket Saya</h2><a class="ph-link" href="{{ route('jadwal-piket-saya.index') }}">Lihat semua <i data-lucide="arrow-up-right" aria-hidden="true"></i></a></header><div class="ph-piket">@foreach ($jadwalPiketSaya as $piket)<span class="ph-badge {{ $piket->id === $jadwalPiketHariIni?->id ? 'amber' : 'blue' }}">{{ $piket->labelHari() }}</span>@endforeach</div></section>
            @endif
        @endizin

        <section class="ph-section" aria-labelledby="ph-activity-title">
            <header class="ph-section-head"><h2 id="ph-activity-title"><i data-lucide="bell" aria-hidden="true"></i> Aktivitas terbaru</h2><a class="ph-link" href="{{ route('notifikasi.index') }}">Semua notifikasi <i data-lucide="arrow-up-right" aria-hidden="true"></i></a></header>
            <div class="ph-section-body">
                @forelse ($aktivitasPegawai as $aktivitas)
                    <form class="ph-activity-form" method="POST" action="{{ route('notifikasi.buka', $aktivitas) }}">
                        @csrf
                        <button class="ph-activity" type="submit">
                            <span class="ph-activity-dot {{ $aktivitas->dibaca_pada ? '' : 'unread' }}" aria-hidden="true"></span>
                            <span><strong>{{ $aktivitas->judul }}</strong><span class="ph-activity-message">{{ $aktivitas->pesan }}</span></span>
                            <time datetime="{{ $aktivitas->created_at->toIso8601String() }}">{{ $aktivitas->created_at->copy()->locale('id')->diffForHumans() }}</time>
                        </button>
                    </form>
                @empty
                    <div class="ph-empty"><i data-lucide="inbox" aria-hidden="true"></i><p>Belum ada notifikasi untuk akun Anda.</p></div>
                @endforelse
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}" defer></script>
    <script src="{{ asset('js/dashboard-pegawai.js') }}?v={{ filemtime(public_path('js/dashboard-pegawai.js')) }}" defer></script>
@endpush
