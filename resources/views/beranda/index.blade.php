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

        .dashboard-action {
            display: flex;
            min-height: 68px;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 13px 14px;
            color: var(--primary-dark);
            font-weight: 900;
            box-shadow: 0 1px 2px rgba(21, 71, 122, .05);
        }

        .dashboard-action:hover {
            border-color: #b9cde2;
            background: var(--primary-soft);
        }

        .dashboard-action-mark {
            display: grid;
            width: 34px;
            height: 34px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 8px;
            background: var(--accent-soft);
            color: var(--accent-text);
            font-size: .78rem;
            font-weight: 900;
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

        @media (max-width: 1180px) {
            .dashboard-actions {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .dashboard-stat-grid,
            .mini-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 900px) {
            .dashboard-hero,
            .dashboard-grid,
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

        <section class="dashboard-actions" aria-label="Aksi cepat">
            @izin('siswa.kelola')
                <a href="{{ route('siswa.create') }}" class="dashboard-action">
                    <span>Tambah Siswa</span>
                    <span class="dashboard-action-mark">SW</span>
                </a>
            @endizin
            @izin('pegawai.kelola')
                <a href="{{ route('pegawai.create') }}" class="dashboard-action">
                    <span>Tambah Pegawai</span>
                    <span class="dashboard-action-mark">PG</span>
                </a>
            @endizin
            @izin('absensi.scan')
                <a href="{{ route('scan-absensi.index') }}" target="_blank" rel="noopener" class="dashboard-action">
                    <span>Scan Siswa</span>
                    <span class="dashboard-action-mark">SA</span>
                </a>
                <a href="{{ route('scan-absensi-pegawai.index') }}" target="_blank" rel="noopener" class="dashboard-action">
                    <span>Scan Pegawai</span>
                    <span class="dashboard-action-mark">SP</span>
                </a>
            @endizin
            @izin('nilai.input')
                <a href="{{ route('input-nilai.index') }}" class="dashboard-action">
                    <span>Input Nilai</span>
                    <span class="dashboard-action-mark">IN</span>
                </a>
            @endizin
            @izin('bk.kelola')
                <a href="{{ route('laporan-pembinaan-siswa.create') }}" class="dashboard-action">
                    <span>Laporan BK</span>
                    <span class="dashboard-action-mark">BK</span>
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
                        <h2>Pembinaan/BK</h2>
                        @izin('bk.lihat', 'bk.kelola')
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
                                <div class="empty-state">Tidak ada laporan pembinaan yang perlu perhatian.</div>
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
        <div class="page-header">
            <div>
                <p class="eyebrow">NUSA</p>
                <h1 class="page-title">Beranda</h1>
            </div>

            <div class="actions">
                <a href="{{ route('kata-sandi.edit') }}" class="button button-muted">Ganti password</a>
            </div>
        </div>

        <section class="panel panel-pad">
            <p class="eyebrow">Selamat datang</p>
            <h2 class="panel-title">{{ auth()->user()->nama }}</h2>
            <p class="help-text" style="margin-top: 8px;">
                Akun pegawai sudah aktif. Fitur guru, wali kelas, dan monitoring akan dibuka bertahap sesuai hak akses.
            </p>
        </section>
    @endif
@endsection
