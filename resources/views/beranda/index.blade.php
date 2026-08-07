@extends('layouts.app')

@section('title', 'Dashboard - NUSA')

@section('content')
    <style>
        .dashboard-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(260px, .65fr);
            gap: 0;
            margin-bottom: 18px;
            overflow: hidden;
            border: 1px solid rgba(21, 71, 122, .18);
            border-radius: 8px;
            background: var(--primary);
            color: #fff;
            box-shadow: var(--shadow);
        }

        .dashboard-hero-main,
        .dashboard-hero-side {
            padding: 26px;
        }

        .dashboard-hero-side {
            display: grid;
            align-content: center;
            gap: 12px;
            border-left: 1px solid rgba(255, 255, 255, .18);
            background: rgba(255, 255, 255, .08);
        }

        .dashboard-hero .eyebrow {
            color: var(--accent);
        }

        .dashboard-title {
            margin: 0;
            max-width: 760px;
            font-size: clamp(1.65rem, 4vw, 2.3rem);
            font-weight: 900;
            letter-spacing: 0;
            line-height: 1.1;
        }

        .dashboard-subtitle,
        .dashboard-date,
        .dashboard-year-note {
            margin: 8px 0 0;
            color: rgba(255, 255, 255, .78);
            font-weight: 700;
        }

        .dashboard-year {
            margin: 0;
            color: #fff;
            font-size: 1.25rem;
            font-weight: 900;
            line-height: 1.15;
        }

        .dashboard-actions {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 18px;
        }

        .dashboard-actions-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 0 10px;
            color: var(--primary-dark);
            font-size: 1rem;
            font-weight: 900;
            line-height: 1.2;
        }

        .dashboard-actions-title::after {
            height: 1px;
            flex: 1;
            background: var(--line);
            content: '';
        }

        .dashboard-actions.employee-actions {
            grid-template-columns: repeat(auto-fit, minmax(168px, 1fr));
        }

        .dashboard-action {
            display: grid;
            min-height: 74px;
            grid-template-columns: 36px minmax(0, 1fr) 26px;
            align-items: center;
            gap: 10px;
            border: 1px solid #b9cde2;
            border-bottom: 3px solid var(--primary);
            border-radius: 8px;
            background: #fff;
            padding: 12px;
            color: var(--primary-dark);
            font-weight: 900;
            box-shadow: 0 2px 5px rgba(21, 71, 122, .09);
            cursor: pointer;
            transition: background-color .18s ease, border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .dashboard-action:hover {
            border-color: var(--primary);
            background: var(--primary-soft);
            color: var(--primary-dark);
            box-shadow: 0 5px 12px rgba(21, 71, 122, .16);
            transform: translateY(-2px);
        }

        .dashboard-action:active {
            box-shadow: 0 1px 3px rgba(21, 71, 122, .12);
            transform: translateY(1px);
        }

        .dashboard-action:focus-visible {
            outline: 3px solid rgba(241, 196, 15, .65);
            outline-offset: 3px;
        }

        .dashboard-action-label {
            min-width: 0;
            line-height: 1.25;
        }

        .dashboard-action-mark {
            display: grid;
            width: 36px;
            height: 36px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 8px;
            background: var(--accent-soft);
            color: var(--accent-text);
            font-size: .78rem;
            font-weight: 900;
        }

        .dashboard-action-arrow {
            display: grid;
            width: 26px;
            height: 26px;
            place-items: center;
            border-radius: 50%;
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 1.05rem;
            font-weight: 900;
            line-height: 1;
        }

        .dashboard-action--highlight {
            border-color: #d4aa00;
            border-bottom-color: #9f7d00;
            background: var(--accent);
            color: #17324d;
        }

        .dashboard-action--highlight:hover {
            border-color: #9f7d00;
            background: #e4b90d;
            color: #17324d;
        }

        .dashboard-action--highlight .dashboard-action-mark {
            background: var(--primary);
            color: #fff;
        }

        .dashboard-action--highlight .dashboard-action-arrow {
            background: rgba(21, 71, 122, .12);
            color: var(--primary-dark);
        }

        .dashboard-stat-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .dashboard-stat {
            min-height: 118px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 16px;
            box-shadow: var(--shadow);
        }

        .dashboard-stat strong {
            display: block;
            color: var(--primary);
            font-size: 1.85rem;
            font-weight: 900;
            line-height: 1;
        }

        .dashboard-stat span {
            display: block;
            margin-top: 8px;
            color: var(--muted);
            font-size: .86rem;
            font-weight: 800;
        }

        .dashboard-stat small {
            display: block;
            margin-top: 8px;
            color: #52525b;
            font-weight: 700;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(0, .92fr);
            gap: 16px;
            align-items: start;
        }

        .dashboard-stack {
            display: grid;
            gap: 16px;
        }

        .dashboard-panel {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            box-shadow: var(--shadow);
        }

        .dashboard-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid var(--line);
            padding: 15px 17px;
        }

        .dashboard-panel-head h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 900;
        }

        .dashboard-panel-body {
            padding: 17px;
        }

        .mini-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .mini-grid.cols-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .mini-card {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--soft);
            padding: 12px;
        }

        .mini-card strong {
            display: block;
            color: var(--primary-dark);
            font-size: 1.35rem;
            font-weight: 900;
            line-height: 1;
        }

        .mini-card span {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: .82rem;
            font-weight: 800;
        }

        .attention-list,
        .dashboard-list {
            display: grid;
            gap: 10px;
        }

        .attention-group {
            display: grid;
            gap: 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 12px;
        }

        .attention-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .attention-title h3 {
            margin: 0;
            font-size: .92rem;
            font-weight: 900;
        }

        .dashboard-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 12px;
        }

        .dashboard-list-item p {
            margin: 0;
            font-weight: 900;
        }

        .dashboard-list-item small {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-weight: 700;
        }

        .split-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .employee-dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, .72fr);
            gap: 16px;
            align-items: start;
        }

        .employee-hero .dashboard-hero-side {
            gap: 14px;
        }

        .dashboard-chart {
            display: grid;
            gap: 12px;
        }

        .chart-row {
            display: grid;
            grid-template-columns: minmax(88px, .24fr) minmax(0, 1fr) minmax(34px, auto);
            gap: 10px;
            align-items: center;
        }

        .chart-label {
            color: var(--primary-dark);
            font-size: .84rem;
            font-weight: 900;
        }

        .chart-track {
            position: relative;
            height: 12px;
            overflow: hidden;
            border-radius: 999px;
            background: #e8eef5;
        }

        .chart-fill {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: var(--chart-color, var(--primary));
        }

        .chart-row strong {
            color: var(--primary-dark);
            font-size: .9rem;
            font-weight: 900;
            text-align: right;
        }

        .class-chip-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .class-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border: 1px solid rgba(21, 71, 122, .18);
            border-radius: 999px;
            background: var(--primary-soft);
            padding: 8px 11px;
            color: var(--primary-dark);
            font-size: .84rem;
            font-weight: 900;
        }

        .class-chip span {
            display: inline-grid;
            min-width: 24px;
            height: 24px;
            place-items: center;
            border-radius: 999px;
            background: #fff;
            color: var(--primary);
            font-size: .74rem;
        }

        .dashboard-note {
            margin: 10px 0 0;
            color: rgba(255, 255, 255, .78);
            font-weight: 700;
        }

        @media (max-width: 1450px) {
            .dashboard-actions,
            .dashboard-actions.employee-actions {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 1180px) {
            .dashboard-stat-grid,
            .mini-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 900px) {
            .dashboard-hero,
            .dashboard-grid,
            .employee-dashboard-grid,
            .split-list {
                grid-template-columns: 1fr;
            }

            .dashboard-hero-side {
                border-left: 0;
                border-top: 1px solid rgba(255, 255, 255, .18);
            }
        }

        @media (max-width: 620px) {
            .dashboard-actions,
            .dashboard-actions.employee-actions,
            .dashboard-stat-grid,
            .mini-grid,
            .mini-grid.cols-3 {
                grid-template-columns: 1fr;
            }

            .dashboard-hero-main,
            .dashboard-hero-side {
                padding: 20px;
            }
        }
    </style>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if (auth()->user()->administrator())
        @php
            $formatAngka = fn (int|float $nilai) => number_format($nilai, 0, ',', '.');
            $waktuScan = fn ($waktu) => $waktu ? $waktu->format('H:i') : '-';
            $statusPembinaanBadge = fn (string $status) => match ($status) {
                'baru' => 'badge badge-warning',
                'perlu_tindak_lanjut' => 'badge badge-danger',
                'diproses' => 'badge badge-active',
                'selesai' => 'badge badge-muted',
                default => 'badge badge-muted',
            };
        @endphp

        <section class="dashboard-hero">
            <div class="dashboard-hero-main">
                <p class="eyebrow">Dashboard Administrator</p>
                <h1 class="dashboard-title">NUSA SMP Negeri 2 Padang Panjang</h1>
                <p class="dashboard-subtitle">Pantau data sekolah, absensi, akademik, dan pembinaan dari satu layar kerja.</p>
            </div>

            <div class="dashboard-hero-side">
                <p class="dashboard-date">{{ $hariIni->locale('id')->translatedFormat('l, d F Y') }}</p>
                <div>
                    <p class="dashboard-year">{{ $tahunPelajaranAktif?->nama ?? 'Tahun pelajaran belum dipilih' }}</p>
                    <p class="dashboard-year-note">{{ $formatAngka($ringkasanUtama['kelas_aktif']) }} kelas aktif</p>
                </div>
                <span class="badge badge-inactive">Administrator</span>
            </div>
        </section>

        <h2 class="dashboard-actions-title">Akses Cepat</h2>
        <section class="dashboard-actions" aria-label="Akses cepat administrator">
            @izin('siswa.kelola')
                <a href="{{ route('siswa.create') }}" class="dashboard-action">
                    <span class="dashboard-action-mark" aria-hidden="true">SW</span>
                    <span class="dashboard-action-label">Tambah Siswa</span>
                    <span class="dashboard-action-arrow" aria-hidden="true">&rarr;</span>
                </a>
            @endizin
            @izin('pegawai.kelola')
                <a href="{{ route('pegawai.create') }}" class="dashboard-action">
                    <span class="dashboard-action-mark" aria-hidden="true">PG</span>
                    <span class="dashboard-action-label">Tambah Pegawai</span>
                    <span class="dashboard-action-arrow" aria-hidden="true">&rarr;</span>
                </a>
            @endizin
            @izin('absensi.scan')
                <a href="{{ route('scan-absensi.index') }}" target="_blank" rel="noopener" class="dashboard-action">
                    <span class="dashboard-action-mark" aria-hidden="true">SA</span>
                    <span class="dashboard-action-label">Scan Siswa</span>
                    <span class="dashboard-action-arrow" aria-hidden="true">&rarr;</span>
                </a>
                <a href="{{ route('scan-absensi-pegawai.index') }}" target="_blank" rel="noopener" class="dashboard-action">
                    <span class="dashboard-action-mark" aria-hidden="true">SP</span>
                    <span class="dashboard-action-label">Scan Pegawai</span>
                    <span class="dashboard-action-arrow" aria-hidden="true">&rarr;</span>
                </a>
            @endizin
            @izin('nilai.input')
                <a href="{{ route('input-nilai.index') }}" class="dashboard-action">
                    <span class="dashboard-action-mark" aria-hidden="true">IN</span>
                    <span class="dashboard-action-label">Input Nilai</span>
                    <span class="dashboard-action-arrow" aria-hidden="true">&rarr;</span>
                </a>
            @endizin
            @izin('bk.kelola')
                <a href="{{ route('laporan-pembinaan-siswa.create') }}" class="dashboard-action">
                    <span class="dashboard-action-mark" aria-hidden="true">BK</span>
                    <span class="dashboard-action-label">Laporan BK</span>
                    <span class="dashboard-action-arrow" aria-hidden="true">&rarr;</span>
                </a>
            @endizin
        </section>

        <section class="dashboard-stat-grid" aria-label="Ringkasan data sekolah">
            <article class="dashboard-stat">
                <strong>{{ $formatAngka($ringkasanUtama['siswa_aktif']) }}</strong>
                <span>Siswa aktif</span>
                <small>{{ $formatAngka($ringkasanAbsensi['siswa_dipantau']) }} siswa dipantau hari ini</small>
            </article>
            <article class="dashboard-stat">
                <strong>{{ $formatAngka($ringkasanUtama['pegawai_aktif']) }}</strong>
                <span>Pegawai aktif</span>
                <small>{{ $formatAngka($ringkasanAbsensiPegawai['pegawai_dipantau']) }} pegawai dipantau hari ini</small>
            </article>
            <article class="dashboard-stat">
                <strong>{{ $formatAngka($ringkasanUtama['kelas_aktif']) }}</strong>
                <span>Kelas aktif</span>
                <small>{{ $tahunPelajaranAktif?->nama ?? 'Belum ada tahun aktif' }}</small>
            </article>
            <article class="dashboard-stat">
                <strong>{{ $formatAngka($ringkasanUtama['mata_pelajaran_aktif']) }}</strong>
                <span>Mata pelajaran aktif</span>
                <small>{{ $formatAngka($ringkasanAkademik['guru_mapel_aktif']) }} penugasan guru mapel</small>
            </article>
        </section>

        <section class="dashboard-grid">
            <div class="dashboard-stack">
                <article class="dashboard-panel">
                    <div class="dashboard-panel-head">
                        <h2>Absensi Siswa Hari Ini</h2>
                        <a href="{{ route('rekap-absensi-harian.index') }}" class="button button-muted button-sm">Lihat</a>
                    </div>
                    <div class="dashboard-panel-body">
                        <div class="mini-grid">
                            @foreach ([
                                ['label' => 'Hadir', 'value' => $ringkasanAbsensi['hadir']],
                                ['label' => 'Terlambat', 'value' => $ringkasanAbsensi['terlambat']],
                                ['label' => 'Alfa', 'value' => $ringkasanAbsensi['alfa']],
                                ['label' => 'Belum scan', 'value' => $ringkasanAbsensi['belum_scan']],
                                ['label' => 'Izin', 'value' => $ringkasanAbsensi['izin']],
                                ['label' => 'Sakit', 'value' => $ringkasanAbsensi['sakit']],
                                ['label' => 'Pulang cepat', 'value' => $ringkasanAbsensi['pulang_cepat']],
                                ['label' => 'Scan berhasil', 'value' => $ringkasanAbsensi['scan_berhasil']],
                            ] as $item)
                                <div class="mini-card">
                                    <strong>{{ $formatAngka($item['value']) }}</strong>
                                    <span>{{ $item['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </article>

                <article class="dashboard-panel">
                    <div class="dashboard-panel-head">
                        <h2>Absensi Pegawai Hari Ini</h2>
                        <a href="{{ route('rekap-absensi-pegawai-harian.index') }}" class="button button-muted button-sm">Lihat</a>
                    </div>
                    <div class="dashboard-panel-body">
                        <div class="mini-grid">
                            @foreach ([
                                ['label' => 'Hadir', 'value' => $ringkasanAbsensiPegawai['hadir']],
                                ['label' => 'Terlambat', 'value' => $ringkasanAbsensiPegawai['terlambat']],
                                ['label' => 'Alfa', 'value' => $ringkasanAbsensiPegawai['alfa']],
                                ['label' => 'Belum scan', 'value' => $ringkasanAbsensiPegawai['belum_scan']],
                                ['label' => 'Izin/sakit/dinas', 'value' => $ringkasanAbsensiPegawai['izin_sakit_dinas']],
                                ['label' => 'Pulang cepat', 'value' => $ringkasanAbsensiPegawai['pulang_cepat']],
                                ['label' => 'Scan berhasil', 'value' => $ringkasanAbsensiPegawai['scan_berhasil']],
                            ] as $item)
                                <div class="mini-card">
                                    <strong>{{ $formatAngka($item['value']) }}</strong>
                                    <span>{{ $item['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </article>

                <article class="dashboard-panel">
                    <div class="dashboard-panel-head">
                        <h2>Perlu Perhatian</h2>
                        @izin('bk.lihat', 'bk.kelola')
                            <a href="{{ route('laporan-pembinaan-siswa.index') }}" class="button button-muted button-sm">BK</a>
                        @endizin
                    </div>
                    <div class="dashboard-panel-body">
                        <div class="attention-list">
                            <section class="attention-group">
                                <div class="attention-title">
                                    <h3>Siswa terlambat</h3>
                                    <span class="badge badge-warning">{{ $formatAngka($ringkasanAbsensi['terlambat']) }}</span>
                                </div>
                                @forelse ($siswaTerlambatHariIni as $absensi)
                                    <div class="dashboard-list-item">
                                        <div>
                                            <p>{{ $absensi->siswa?->nama_lengkap ?? 'Siswa tidak ditemukan' }}</p>
                                            <small>{{ $absensi->kelas?->nama ?? '-' }} - terlambat {{ $absensi->menit_terlambat }} menit</small>
                                        </div>
                                    </div>
                                @empty
                                    <p class="help-text">Belum ada siswa terlambat tercatat hari ini.</p>
                                @endforelse
                            </section>

                            <section class="attention-group">
                                <div class="attention-title">
                                    <h3>Siswa alfa / belum scan</h3>
                                    <span class="badge badge-danger">{{ $formatAngka($ringkasanAbsensi['alfa'] + $ringkasanAbsensi['belum_scan']) }}</span>
                                </div>
                                @forelse ($siswaAlfaHariIni as $absensi)
                                    <div class="dashboard-list-item">
                                        <div>
                                            <p>{{ $absensi->siswa?->nama_lengkap ?? 'Siswa tidak ditemukan' }}</p>
                                            <small>{{ $absensi->kelas?->nama ?? '-' }} - alfa</small>
                                        </div>
                                    </div>
                                @empty
                                    @forelse ($siswaBelumScanHariIni as $anggota)
                                        <div class="dashboard-list-item">
                                            <div>
                                                <p>{{ $anggota->siswa?->nama_lengkap ?? 'Siswa tidak ditemukan' }}</p>
                                                <small>{{ $anggota->kelas?->nama ?? '-' }} - belum ada catatan absensi</small>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="help-text">Belum ada siswa alfa atau belum scan yang perlu ditampilkan.</p>
                                    @endforelse
                                @endforelse
                            </section>

                            <section class="attention-group">
                                <div class="attention-title">
                                    <h3>Pegawai terlambat / belum scan</h3>
                                    <span class="badge badge-warning">{{ $formatAngka($ringkasanAbsensiPegawai['terlambat'] + $ringkasanAbsensiPegawai['belum_scan']) }}</span>
                                </div>
                                @forelse ($pegawaiTerlambatHariIni as $absensi)
                                    <div class="dashboard-list-item">
                                        <div>
                                            <p>{{ $absensi->pegawai?->nama_lengkap ?? 'Pegawai tidak ditemukan' }}</p>
                                            <small>Terlambat {{ $absensi->menit_terlambat }} menit</small>
                                        </div>
                                    </div>
                                @empty
                                    @forelse ($pegawaiBelumScanHariIni as $pegawai)
                                        <div class="dashboard-list-item">
                                            <div>
                                                <p>{{ $pegawai->nama_lengkap }}</p>
                                                <small>{{ $pegawai->nip ?: 'NIP belum diisi' }} - belum ada catatan absensi</small>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="help-text">Belum ada pegawai terlambat atau belum scan yang perlu ditampilkan.</p>
                                    @endforelse
                                @endforelse
                            </section>
                        </div>
                    </div>
                </article>
            </div>

            <div class="dashboard-stack">
                <article class="dashboard-panel">
                    <div class="dashboard-panel-head">
                        <h2>Akademik</h2>
                        @izin('nilai.rekap')
                            <a href="{{ route('rekap-nilai-rapor.index') }}" class="button button-muted button-sm">Rapor</a>
                        @endizin
                    </div>
                    <div class="dashboard-panel-body">
                        <div class="mini-grid cols-3">
                            <div class="mini-card">
                                <strong>{{ $formatAngka($ringkasanAkademik['guru_mapel_aktif']) }}</strong>
                                <span>Guru mapel aktif</span>
                            </div>
                            <div class="mini-card">
                                <strong>{{ $formatAngka($ringkasanAkademik['komponen_nilai_aktif']) }}</strong>
                                <span>Komponen nilai</span>
                            </div>
                            <div class="mini-card">
                                <strong>{{ $formatAngka($ringkasanAkademik['nilai_masuk']) }}</strong>
                                <span>Nilai masuk</span>
                            </div>
                            <div class="mini-card">
                                <strong>{{ $formatAngka($ringkasanAkademik['komponen_belum_terisi']) }}</strong>
                                <span>Komponen belum terisi</span>
                            </div>
                            <div class="mini-card">
                                <strong>{{ $formatAngka($ringkasanAkademik['kelas_terisi']) }}</strong>
                                <span>Kelas terisi siswa</span>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="dashboard-panel">
                    <div class="dashboard-panel-head">
                        <h2>Pembinaan & Poin Siswa</h2>
                        @izin('bk.lihat', 'bk.kelola', 'poin_siswa.lihat')
                            <a href="{{ route('laporan-pembinaan-siswa.index') }}" class="button button-muted button-sm">Lihat</a>
                        @endizin
                    </div>
                    <div class="dashboard-panel-body">
                        <div class="mini-grid">
                            <div class="mini-card">
                                <strong>{{ $formatAngka($ringkasanPembinaan['baru']) }}</strong>
                                <span>Laporan baru</span>
                            </div>
                            <div class="mini-card">
                                <strong>{{ $formatAngka($ringkasanPembinaan['diproses']) }}</strong>
                                <span>Diproses</span>
                            </div>
                            <div class="mini-card">
                                <strong>{{ $formatAngka($ringkasanPembinaan['perlu_tindak_lanjut']) }}</strong>
                                <span>Perlu tindak lanjut</span>
                            </div>
                            <div class="mini-card">
                                <strong>{{ $formatAngka($ringkasanPembinaan['selesai_bulan_ini']) }}</strong>
                                <span>Selesai bulan ini</span>
                            </div>
                            <div class="mini-card">
                                <strong>{{ $formatAngka($ringkasanPembinaan['menunggu_bk']) }}</strong>
                                <span>Menunggu pemeriksaan BK</span>
                            </div>
                            <div class="mini-card">
                                <strong>{{ $formatAngka($ringkasanPembinaan['menunggu_wakil']) }}</strong>
                                <span>Menunggu pengesahan Wakil</span>
                            </div>
                            <div class="mini-card">
                                <strong>{{ $formatAngka($ringkasanPembinaan['pembinaan_ditetapkan']) }}</strong>
                                <span>Ditetapkan pembinaan</span>
                            </div>
                            <div class="mini-card">
                                <strong>{{ $formatAngka($ringkasanPembinaan['poin_aktif']) }}</strong>
                                <span>Total poin aktif</span>
                            </div>
                            <div class="mini-card">
                                <strong>{{ $formatAngka($ringkasanPembinaan['sanksi_menunggu']) }}</strong>
                                <span>Sanksi belum selesai</span>
                            </div>
                        </div>

                        <div class="dashboard-list" style="margin-top: 12px;">
                            @forelse ($laporanPembinaanPerluPerhatian as $laporan)
                                <div class="dashboard-list-item">
                                    <div>
                                        <p>{{ $laporan->siswa?->nama_lengkap ?? 'Siswa tidak ditemukan' }}</p>
                                        <small>{{ $laporan->kategoriPembinaanSiswa?->nama ?? '-' }} - {{ $laporan->tanggal_kejadian?->format('d/m/Y') ?? '-' }}</small>
                                    </div>
                                    <span class="{{ $statusPembinaanBadge($laporan->status) }}">{{ $laporan->labelStatus() }}</span>
                                </div>
                            @empty
                                <div class="empty-state">Tidak ada laporan pembinaan atau pelanggaran yang perlu perhatian.</div>
                            @endforelse
                        </div>
                    </div>
                </article>

                <article class="dashboard-panel">
                    <div class="dashboard-panel-head">
                        <h2>Scan Terakhir</h2>
                        @izin('absensi.laporan')
                            <a href="{{ route('laporan-absensi.index') }}" class="button button-muted button-sm">Laporan</a>
                        @endizin
                    </div>
                    <div class="dashboard-panel-body">
                        <div class="split-list">
                            <div>
                                <p class="person-name" style="margin-bottom: 10px;">Siswa</p>
                                <div class="dashboard-list">
                                    @forelse ($logScanTerakhir as $logScan)
                                        <div class="dashboard-list-item">
                                            <div>
                                                <p>{{ $logScan->siswa?->nama_lengkap ?? 'Siswa tidak ditemukan' }}</p>
                                                <small>{{ $logScan->nisn ?: '-' }} - {{ $waktuScan($logScan->waktu_scan) }}</small>
                                            </div>
                                            <span class="badge {{ $logScan->berhasil ? 'badge-active' : 'badge-danger' }}">
                                                {{ $logScan->berhasil ? 'Berhasil' : 'Gagal' }}
                                            </span>
                                        </div>
                                    @empty
                                        <p class="help-text">Belum ada scan siswa.</p>
                                    @endforelse
                                </div>
                            </div>

                            <div>
                                <p class="person-name" style="margin-bottom: 10px;">Pegawai</p>
                                <div class="dashboard-list">
                                    @forelse ($logScanPegawaiTerakhir as $logScan)
                                        <div class="dashboard-list-item">
                                            <div>
                                                <p>{{ $logScan->pegawai?->nama_lengkap ?? 'Pegawai tidak ditemukan' }}</p>
                                                <small>{{ $logScan->nip ?: '-' }} - {{ $waktuScan($logScan->waktu_scan) }}</small>
                                            </div>
                                            <span class="badge {{ $logScan->berhasil ? 'badge-active' : 'badge-danger' }}">
                                                {{ $logScan->berhasil ? 'Berhasil' : 'Gagal' }}
                                            </span>
                                        </div>
                                    @empty
                                        <p class="help-text">Belum ada scan pegawai.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </section>
    @else
        @php
            $formatAngka = fn (int|float $nilai) => number_format($nilai, 0, ',', '.');
            $statusPembinaanBadge = fn (string $status) => match ($status) {
                'baru' => 'badge badge-warning',
                'perlu_tindak_lanjut' => 'badge badge-danger',
                'diproses' => 'badge badge-active',
                'selesai' => 'badge badge-muted',
                default => 'badge badge-muted',
            };
            $labelPeranWali = $kelasWali->isNotEmpty() && $jumlahSiswaGuruWali > 0
                ? 'Wali Kelas dan Guru Wali'
                : ($kelasWali->isNotEmpty() ? 'Wali Kelas' : ($jumlahSiswaGuruWali > 0 ? 'Guru Wali' : 'Pegawai'));
        @endphp

        <section class="dashboard-hero employee-hero">
            <div class="dashboard-hero-main">
                <p class="eyebrow">Dashboard Pegawai</p>
                <h1 class="dashboard-title">
                    Selamat datang, {{ isset($pegawaiLogin) ? ($pegawaiLogin?->nama_lengkap ?? auth()->user()->nama) : auth()->user()->nama }}
                </h1>
                <p class="dashboard-subtitle">
                    Rekap pribadi bulan {{ $labelBulan }} tersaji ringkas untuk membantu memantau kehadiran.
                </p>
            </div>

            <div class="dashboard-hero-side">
                <p class="dashboard-date">{{ $hariIni->locale('id')->translatedFormat('l, d F Y') }}</p>
                <div>
                    <p class="dashboard-year">{{ $tahunPelajaranAktif?->nama ?? 'Tahun pelajaran belum aktif' }}</p>
                    <p class="dashboard-note">{{ $pegawaiLogin?->nip ?: 'NIP belum diisi' }}</p>
                </div>
                <span class="badge badge-inactive">{{ $labelPeranWali }}</span>
            </div>
        </section>

        <h2 class="dashboard-actions-title">Akses Cepat</h2>
        <section class="dashboard-actions employee-actions" aria-label="Akses cepat pegawai">
            @if ($pegawaiLogin)
                @izin('poin_siswa.lapor')
                    <a href="{{ route('laporan-pembinaan-siswa.create') }}" class="dashboard-action dashboard-action--highlight">
                        <span class="dashboard-action-mark" aria-hidden="true">LK</span>
                        <span class="dashboard-action-label">Laporkan Kejadian</span>
                        <span class="dashboard-action-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                @endizin
            @endif
            <a href="{{ route('kata-sandi.edit') }}" class="dashboard-action">
                <span class="dashboard-action-mark" aria-hidden="true">PW</span>
                <span class="dashboard-action-label">Ganti Password</span>
                <span class="dashboard-action-arrow" aria-hidden="true">&rarr;</span>
            </a>
            @if ($pegawaiLogin)
                <a href="{{ route('profil-pegawai.edit') }}" class="dashboard-action">
                    <span class="dashboard-action-mark" aria-hidden="true">PR</span>
                    <span class="dashboard-action-label">Profil Saya</span>
                    <span class="dashboard-action-arrow" aria-hidden="true">&rarr;</span>
                </a>
            @endif
            @izin('absensi_pegawai.pribadi', 'absensi.lihat', 'absensi.koreksi', 'absensi.laporan')
                <a href="{{ route('rekap-absensi-pegawai-harian.index') }}" class="dashboard-action">
                    <span class="dashboard-action-mark" aria-hidden="true">RS</span>
                    <span class="dashboard-action-label">Rekap Saya</span>
                    <span class="dashboard-action-arrow" aria-hidden="true">&rarr;</span>
                </a>
            @endizin
            @izin('absensi_pegawai.pribadi', 'absensi.laporan')
                <a href="{{ route('laporan-absensi-pegawai-bulanan.index') }}" class="dashboard-action">
                    <span class="dashboard-action-mark" aria-hidden="true">LS</span>
                    <span class="dashboard-action-label">Laporan Saya</span>
                    <span class="dashboard-action-arrow" aria-hidden="true">&rarr;</span>
                </a>
            @endizin
            @if ($kelasWali->isNotEmpty())
                @izin('absensi.lihat', 'absensi.koreksi')
                    <a href="{{ route('rekap-absensi-harian.index') }}" class="dashboard-action">
                        <span class="dashboard-action-mark" aria-hidden="true">RK</span>
                        <span class="dashboard-action-label">Rekap Kelas</span>
                        <span class="dashboard-action-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                @endizin
            @endif
            @if ($memilikiPerwalian)
                @izin('poin_siswa.lihat', 'poin_siswa.lapor')
                    <a href="{{ route('laporan-pembinaan-siswa.index') }}" class="dashboard-action">
                        <span class="dashboard-action-mark" aria-hidden="true">BK</span>
                        <span class="dashboard-action-label">Pembinaan & Poin</span>
                        <span class="dashboard-action-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                @endizin
            @endif
        </section>

        @if (! $pegawaiLogin)
            <section class="panel panel-pad">
                <p class="eyebrow">Data pegawai</p>
                <h2 class="panel-title">Akun belum terhubung ke data pegawai</h2>
                <p class="help-text" style="margin-top: 8px;">Hubungkan akun ini dengan data pegawai agar rekap absensi pribadi dapat tampil.</p>
            </section>
        @endif

        <section class="employee-dashboard-grid">
            <div class="dashboard-stack">
                <article class="dashboard-panel">
                    <div class="dashboard-panel-head">
                        <h2>Absensi Saya Bulan Ini</h2>
                        <span class="badge badge-muted">{{ $labelBulan }}</span>
                    </div>
                    <div class="dashboard-panel-body">
                        <div class="mini-grid">
                            @foreach ([
                                ['label' => 'Hadir', 'value' => $ringkasanAbsensiPegawaiPribadi['hadir']],
                                ['label' => 'Sakit', 'value' => $ringkasanAbsensiPegawaiPribadi['sakit']],
                                ['label' => 'Izin', 'value' => $ringkasanAbsensiPegawaiPribadi['izin']],
                                ['label' => 'Alfa', 'value' => $ringkasanAbsensiPegawaiPribadi['alfa']],
                                ['label' => 'Dinas luar', 'value' => $ringkasanAbsensiPegawaiPribadi['dinas_luar']],
                                ['label' => 'Cuti', 'value' => $ringkasanAbsensiPegawaiPribadi['cuti']],
                                ['label' => 'Terlambat', 'value' => $ringkasanAbsensiPegawaiPribadi['terlambat']],
                                ['label' => 'Pulang cepat', 'value' => $ringkasanAbsensiPegawaiPribadi['pulang_cepat']],
                            ] as $item)
                                <div class="mini-card">
                                    <strong>{{ $formatAngka($item['value']) }}</strong>
                                    <span>{{ $item['label'] }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="dashboard-chart" style="margin-top: 16px;">
                            @foreach ($rekapAbsensiPegawaiBulan as $item)
                                @php
                                    $lebar = $maksGrafikPegawai > 0 ? round(($item['jumlah'] / $maksGrafikPegawai) * 100, 2) : 0;
                                @endphp
                                <div class="chart-row">
                                    <span class="chart-label">{{ $item['label'] }}</span>
                                    <span class="chart-track" aria-hidden="true">
                                        <span class="chart-fill" style="width: {{ $lebar }}%; --chart-color: {{ $item['warna'] }};"></span>
                                    </span>
                                    <strong>{{ $formatAngka($item['jumlah']) }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </article>

                @if ($kelasWali->isNotEmpty())
                    <article class="dashboard-panel">
                        <div class="dashboard-panel-head">
                            <h2>Kehadiran Siswa Wali</h2>
                            <span class="badge badge-muted">{{ $labelBulan }}</span>
                        </div>
                        <div class="dashboard-panel-body">
                            <div class="mini-grid">
                                @foreach ([
                                    ['label' => 'Siswa aktif', 'value' => $ringkasanAbsensiSiswaWali['jumlah_siswa']],
                                    ['label' => 'Hadir', 'value' => $ringkasanAbsensiSiswaWali['hadir']],
                                    ['label' => 'Sakit', 'value' => $ringkasanAbsensiSiswaWali['sakit']],
                                    ['label' => 'Izin', 'value' => $ringkasanAbsensiSiswaWali['izin']],
                                    ['label' => 'Alfa', 'value' => $ringkasanAbsensiSiswaWali['alfa']],
                                    ['label' => 'Terlambat', 'value' => $ringkasanAbsensiSiswaWali['terlambat']],
                                    ['label' => 'Pulang cepat', 'value' => $ringkasanAbsensiSiswaWali['pulang_cepat']],
                                    ['label' => 'Catatan absensi', 'value' => $ringkasanAbsensiSiswaWali['total_catatan']],
                                ] as $item)
                                    <div class="mini-card">
                                        <strong>{{ $formatAngka($item['value']) }}</strong>
                                        <span>{{ $item['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>

                            <div class="dashboard-chart" style="margin-top: 16px;">
                                @foreach ($rekapAbsensiSiswaWaliBulan as $item)
                                    @php
                                        $lebar = $maksGrafikSiswaWali > 0 ? round(($item['jumlah'] / $maksGrafikSiswaWali) * 100, 2) : 0;
                                    @endphp
                                    <div class="chart-row">
                                        <span class="chart-label">{{ $item['label'] }}</span>
                                        <span class="chart-track" aria-hidden="true">
                                            <span class="chart-fill" style="width: {{ $lebar }}%; --chart-color: {{ $item['warna'] }};"></span>
                                        </span>
                                        <strong>{{ $formatAngka($item['jumlah']) }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </article>
                @endif
            </div>

            <div class="dashboard-stack">
                <article class="dashboard-panel">
                    <div class="dashboard-panel-head">
                        <h2>Data Pegawai</h2>
                        @if ($pegawaiLogin)
                            <a href="{{ route('profil-pegawai.edit') }}" class="button button-muted button-sm">Edit</a>
                        @endif
                    </div>
                    <div class="dashboard-panel-body">
                        <div class="dashboard-list">
                            <div class="dashboard-list-item">
                                <div>
                                    <p>{{ $pegawaiLogin?->nama_lengkap ?? auth()->user()->nama }}</p>
                                    <small>{{ $pegawaiLogin?->jabatan_utama ?: ($pegawaiLogin?->jenis_pegawai ?: 'Pegawai') }}</small>
                                </div>
                            </div>
                            <div class="dashboard-list-item">
                                <div>
                                    <p>{{ $pegawaiLogin?->nip ?: 'NIP belum diisi' }}</p>
                                    <small>{{ $pegawaiLogin?->email ?: 'Email belum diisi' }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>

                @if ($kelasWali->isNotEmpty())
                    <article class="dashboard-panel">
                        <div class="dashboard-panel-head">
                            <h2>Kelas Wali</h2>
                            <span class="badge badge-active">{{ $formatAngka($kelasWali->count()) }} kelas</span>
                        </div>
                        <div class="dashboard-panel-body">
                            <div class="class-chip-list">
                                @foreach ($kelasWali as $kelas)
                                    <span class="class-chip">
                                        {{ $kelas->nama }}
                                        <span>{{ $formatAngka((int) $kelas->jumlah_siswa_aktif) }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </article>

                @endif

                @if ($memilikiPerwalian)
                    <article class="dashboard-panel">
                        <div class="dashboard-panel-head">
                            <h2>Pembinaan & Poin Siswa Wali</h2>
                            <span class="badge badge-muted">{{ $labelBulan }}</span>
                        </div>
                        <div class="dashboard-panel-body">
                            <div class="mini-grid">
                                @foreach ([
                                    ['label' => 'Total laporan', 'value' => $ringkasanPembinaanWali['total_laporan']],
                                    ['label' => 'Siswa terlapor', 'value' => $ringkasanPembinaanWali['siswa_terlapor']],
                                    ['label' => 'Menunggu keputusan BK', 'value' => $ringkasanPembinaanWali['menunggu_bk']],
                                    ['label' => 'Menunggu pengesahan Wakil', 'value' => $ringkasanPembinaanWali['menunggu_wakil']],
                                    ['label' => 'Poin aktif', 'value' => $ringkasanPembinaanWali['poin_aktif']],
                                ] as $item)
                                    <div class="mini-card">
                                        <strong>{{ $formatAngka($item['value']) }}</strong>
                                        <span>{{ $item['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>

                            <div class="dashboard-chart" style="margin-top: 16px;">
                                @foreach ($rekapPembinaanWaliBulan as $item)
                                    @php
                                        $lebar = $maksGrafikPembinaanWali > 0 ? round(($item['jumlah'] / $maksGrafikPembinaanWali) * 100, 2) : 0;
                                    @endphp
                                    <div class="chart-row">
                                        <span class="chart-label">{{ $item['label'] }}</span>
                                        <span class="chart-track" aria-hidden="true">
                                            <span class="chart-fill" style="width: {{ $lebar }}%; --chart-color: {{ $item['warna'] }};"></span>
                                        </span>
                                        <strong>{{ $formatAngka($item['jumlah']) }}</strong>
                                    </div>
                                @endforeach
                            </div>

                            <div class="dashboard-list" style="margin-top: 14px;">
                                @forelse ($laporanPembinaanWali as $laporan)
                                    <div class="dashboard-list-item">
                                        <div>
                                            <p>{{ $laporan->siswa?->nama_lengkap ?? 'Siswa tidak ditemukan' }}</p>
                                            <small>{{ $laporan->kelas?->nama ?? '-' }} - {{ $laporan->kategoriPembinaanSiswa?->nama ?? '-' }} - {{ $laporan->tanggal_kejadian?->format('d/m/Y') ?? '-' }}</small>
                                        </div>
                                        <span class="{{ $statusPembinaanBadge($laporan->status) }}">{{ $laporan->labelStatus() }}</span>
                                    </div>
                                @empty
                                    <div class="empty-state">Belum ada laporan pembinaan untuk siswa wali bulan ini.</div>
                                @endforelse
                            </div>
                        </div>
                    </article>
                @else
                    <article class="dashboard-panel">
                        <div class="dashboard-panel-head">
                            <h2>Perwalian Siswa</h2>
                            <span class="badge badge-muted">Belum aktif</span>
                        </div>
                        <div class="dashboard-panel-body">
                            <p class="help-text">Belum ada kelas atau siswa aktif yang menjadi tanggung jawab perwalian akun ini.</p>
                        </div>
                    </article>
                @endif
            </div>
        </section>
    @endif
@endsection
