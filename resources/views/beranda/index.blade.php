@extends('layouts.app')

@section('title', 'Dashboard - NUSA')

@section('content')
    <style>
        .dashboard-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(260px, .65fr);
            gap: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(21, 71, 122, .16);
            border-radius: 8px;
            background: var(--primary);
            color: #fff;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .dashboard-hero-main {
            padding: 28px;
        }

        .dashboard-hero-side {
            display: grid;
            align-content: center;
            gap: 12px;
            border-left: 1px solid rgba(255, 255, 255, .18);
            background: rgba(255, 255, 255, .08);
            padding: 24px;
        }

        .dashboard-hero .eyebrow {
            color: var(--accent);
        }

        .dashboard-title {
            margin: 0;
            max-width: 720px;
            font-size: clamp(1.7rem, 4vw, 2.45rem);
            font-weight: 900;
            line-height: 1.08;
        }

        .dashboard-subtitle {
            margin: 12px 0 0;
            max-width: 680px;
            color: rgba(255, 255, 255, .78);
            font-weight: 700;
        }

        .dashboard-date {
            margin: 0;
            color: rgba(255, 255, 255, .75);
            font-size: .88rem;
            font-weight: 800;
        }

        .dashboard-year {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 900;
            line-height: 1.15;
        }

        .quick-action-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin: 16px 0 24px;
        }

        .quick-action {
            display: flex;
            min-height: 70px;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 14px;
            color: var(--primary-dark);
            font-weight: 900;
            box-shadow: 0 1px 2px rgba(21, 71, 122, .05);
        }

        .quick-action span:last-child {
            display: grid;
            width: 34px;
            height: 34px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 8px;
            background: var(--accent-soft);
            color: var(--accent-text);
            font-size: .8rem;
        }

        .dashboard-stat-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .dashboard-stat {
            min-height: 116px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 16px;
            box-shadow: var(--shadow);
        }

        .dashboard-stat strong {
            display: block;
            color: var(--primary);
            font-size: 1.75rem;
            font-weight: 900;
            line-height: 1.05;
        }

        .dashboard-stat span {
            display: block;
            margin-top: 8px;
            color: var(--muted);
            font-size: .86rem;
            font-weight: 800;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr);
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
            padding: 16px 18px;
        }

        .dashboard-panel-head h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 900;
        }

        .dashboard-panel-body {
            padding: 18px;
        }

        .attendance-mini-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .attendance-mini {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--soft);
            padding: 12px;
        }

        .attendance-mini strong {
            display: block;
            color: var(--primary-dark);
            font-size: 1.35rem;
            font-weight: 900;
            line-height: 1.05;
        }

        .attendance-mini span {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            font-size: .82rem;
            font-weight: 800;
        }

        .dashboard-list {
            display: grid;
            gap: 10px;
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
            margin-top: 2px;
            color: var(--muted);
            font-weight: 700;
        }

        .dashboard-stack {
            display: grid;
            gap: 16px;
        }

        @media (max-width: 1100px) {
            .dashboard-stat-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .quick-action-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .dashboard-hero,
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-hero-main,
            .dashboard-hero-side {
                padding: 20px;
            }

            .dashboard-hero-side {
                border-left: 0;
                border-top: 1px solid rgba(255, 255, 255, .18);
            }

            .dashboard-stat-grid,
            .attendance-mini-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 520px) {
            .quick-action-grid,
            .dashboard-stat-grid,
            .attendance-mini-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if (auth()->user()->administrator())
        <section class="dashboard-hero">
            <div class="dashboard-hero-main">
                <p class="eyebrow">Dashboard Administrator</p>
                <h1 class="dashboard-title">NUSA SMP Negeri 2 Padang Panjang</h1>
                <p class="dashboard-subtitle">Data utama sekolah, akademik, dan absensi tersaji dalam satu layar kerja.</p>
            </div>

            <div class="dashboard-hero-side">
                <p class="dashboard-date">{{ $hariIni->locale('id')->translatedFormat('l, d F Y') }}</p>
                <p class="dashboard-year">{{ $tahunPelajaranAktif?->nama ?? 'Tahun pelajaran belum dipilih' }}</p>
                <span class="badge badge-inactive">Administrator</span>
            </div>
        </section>

        <div class="quick-action-grid">
            <a href="{{ route('siswa.index') }}" class="quick-action">
                <span>Data Siswa</span>
                <span>SW</span>
            </a>
            <a href="{{ route('pegawai.index') }}" class="quick-action">
                <span>Data Pegawai</span>
                <span>PG</span>
            </a>
            <a href="{{ route('scan-absensi.index') }}" target="_blank" rel="noopener" class="quick-action">
                <span>Scan Absensi</span>
                <span>SA</span>
            </a>
            <a href="{{ route('laporan-absensi.index') }}" class="quick-action">
                <span>Laporan Absensi</span>
                <span>LA</span>
            </a>
        </div>

        <section class="dashboard-stat-grid" aria-label="Ringkasan NUSA">
            @foreach ([
                ['label' => 'Siswa aktif', 'value' => $ringkasanUtama['siswa_aktif']],
                ['label' => 'Pegawai aktif', 'value' => $ringkasanUtama['pegawai_aktif']],
                ['label' => 'Kelas aktif', 'value' => $ringkasanUtama['kelas_aktif']],
                ['label' => 'Mata pelajaran', 'value' => $ringkasanUtama['mata_pelajaran_aktif']],
                ['label' => 'Guru mapel', 'value' => $ringkasanUtama['guru_mapel_aktif']],
                ['label' => 'Nilai masuk', 'value' => $ringkasanUtama['nilai_masuk']],
            ] as $stat)
                <article class="dashboard-stat">
                    <strong>{{ number_format($stat['value'], 0, ',', '.') }}</strong>
                    <span>{{ $stat['label'] }}</span>
                </article>
            @endforeach
        </section>

        <section class="dashboard-grid">
            <div class="dashboard-stack">
                <article class="dashboard-panel">
                    <div class="dashboard-panel-head">
                        <h2>Absensi Hari Ini</h2>
                        <a href="{{ route('rekap-absensi-harian.index') }}" class="button button-muted button-sm">Lihat</a>
                    </div>
                    <div class="dashboard-panel-body">
                        <div class="attendance-mini-grid">
                            @foreach ([
                                ['label' => 'Hadir', 'value' => $ringkasanAbsensi['hadir']],
                                ['label' => 'Izin', 'value' => $ringkasanAbsensi['izin']],
                                ['label' => 'Sakit', 'value' => $ringkasanAbsensi['sakit']],
                                ['label' => 'Alfa', 'value' => $ringkasanAbsensi['alfa']],
                                ['label' => 'Terlambat', 'value' => $ringkasanAbsensi['terlambat']],
                                ['label' => 'Pulang cepat', 'value' => $ringkasanAbsensi['pulang_cepat']],
                            ] as $item)
                                <div class="attendance-mini">
                                    <strong>{{ number_format($item['value'], 0, ',', '.') }}</strong>
                                    <span>{{ $item['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </article>

                <article class="dashboard-panel">
                    <div class="dashboard-panel-head">
                        <h2>Akademik</h2>
                        <a href="{{ route('rekap-nilai-rapor.index') }}" class="button button-muted button-sm">Rapor</a>
                    </div>
                    <div class="dashboard-panel-body">
                        <div class="attendance-mini-grid">
                            <div class="attendance-mini">
                                <strong>{{ number_format($ringkasanAkademik['komponen_nilai_aktif'], 0, ',', '.') }}</strong>
                                <span>Komponen nilai</span>
                            </div>
                            <div class="attendance-mini">
                                <strong>{{ number_format($ringkasanAkademik['kelas_terisi'], 0, ',', '.') }}</strong>
                                <span>Kelas terisi</span>
                            </div>
                            <div class="attendance-mini">
                                <strong>{{ number_format($ringkasanAkademik['scan_berhasil_hari_ini'], 0, ',', '.') }}</strong>
                                <span>Scan berhasil</span>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <div class="dashboard-stack">
                <article class="dashboard-panel">
                    <div class="dashboard-panel-head">
                        <h2>Scan Terakhir</h2>
                        <a href="{{ route('laporan-absensi.index') }}" class="button button-muted button-sm">Laporan</a>
                    </div>
                    <div class="dashboard-panel-body">
                        @if ($logScanTerakhir->isEmpty())
                            <div class="empty-state">Belum ada scan hari ini.</div>
                        @else
                            <div class="dashboard-list">
                                @foreach ($logScanTerakhir as $logScan)
                                    <div class="dashboard-list-item">
                                        <div>
                                            <p>{{ $logScan->siswa?->nama_lengkap ?? 'Siswa tidak ditemukan' }}</p>
                                            <small>{{ $logScan->nisn }} · {{ $logScan->waktu_scan?->format('H:i') ?? '-' }}</small>
                                        </div>
                                        <span class="badge {{ $logScan->berhasil ? 'badge-active' : 'badge-danger' }}">
                                            {{ $logScan->berhasil ? 'Berhasil' : 'Gagal' }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </article>

                <article class="dashboard-panel">
                    <div class="dashboard-panel-head">
                        <h2>Data Terbaru</h2>
                        <a href="{{ route('pegawai.index') }}" class="button button-muted button-sm">Kelola</a>
                    </div>
                    <div class="dashboard-panel-body">
                        <div class="dashboard-list">
                            @foreach ($siswaTerbaru as $siswa)
                                <div class="dashboard-list-item">
                                    <div>
                                        <p>{{ $siswa->nama_lengkap }}</p>
                                        <small>Siswa · {{ $siswa->nisn ?? 'NISN belum diisi' }}</small>
                                    </div>
                                    <span class="badge {{ $siswa->aktif ? 'badge-active' : 'badge-inactive' }}">
                                        {{ $siswa->aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                            @endforeach

                            @foreach ($pegawaiTerbaru as $pegawai)
                                <div class="dashboard-list-item">
                                    <div>
                                        <p>{{ $pegawai->nama_lengkap }}</p>
                                        <small>Pegawai · {{ $pegawai->nip ?? 'NIP belum diisi' }}</small>
                                    </div>
                                    <span class="badge {{ $pegawai->aktif ? 'badge-active' : 'badge-inactive' }}">
                                        {{ $pegawai->aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                            @endforeach
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
