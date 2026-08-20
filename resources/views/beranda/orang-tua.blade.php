@extends('layouts.app')

@section('title', 'Dashboard Orang Tua - NUSA')

@section('content')
    <style>
        .parent-dashboard {
            display: grid;
            gap: 18px;
        }

        .parent-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 22px;
            align-items: center;
            padding: 22px;
            border-radius: 8px;
            background: #15477a;
            color: #fff;
            box-shadow: 0 14px 30px rgba(21, 71, 122, .15);
        }

        .parent-identity {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: 17px;
        }

        .parent-photo {
            width: 82px;
            height: 98px;
            flex: 0 0 auto;
            overflow: hidden;
            border: 3px solid #f1c40f;
            border-radius: 8px;
            background: #fff;
        }

        .parent-photo img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }

        .parent-hero .eyebrow {
            margin: 0 0 5px;
            color: #f1c40f;
        }

        .parent-name {
            margin: 0;
            overflow-wrap: anywhere;
            color: #fff;
            font-size: 2rem;
            line-height: 1.15;
            letter-spacing: 0;
        }

        .parent-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 7px 16px;
            margin-top: 9px;
            color: #dbeafe;
            font-size: .9rem;
        }

        .parent-today {
            min-width: 235px;
            padding-left: 22px;
            border-left: 1px solid rgba(255, 255, 255, .25);
            text-align: right;
        }

        .parent-today strong {
            display: block;
            margin-top: 6px;
            color: #f1c40f;
            font-size: 1.05rem;
        }

        .parent-today span {
            color: #dbeafe;
            font-size: .86rem;
        }

        .parent-status {
            display: inline-flex;
            min-height: 30px;
            align-items: center;
            justify-content: center;
            margin-top: 12px;
            padding: 6px 10px;
            border-radius: 6px;
            background: #fff;
            color: #15477a;
            font-size: .78rem;
            font-weight: 900;
        }

        .parent-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .parent-stat,
        .parent-panel {
            border: 1px solid #dce4eb;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(31, 41, 55, .06);
        }

        .parent-stat {
            min-width: 0;
            padding: 16px;
        }

        .parent-stat span,
        .parent-stat small {
            display: block;
            color: #64748b;
        }

        .parent-stat span {
            font-size: .76rem;
            font-weight: 800;
        }

        .parent-stat strong {
            display: block;
            margin: 6px 0;
            color: #15477a;
            font-size: 1.3rem;
            line-height: 1.15;
            overflow-wrap: anywhere;
        }

        .parent-stat small {
            font-size: .72rem;
        }

        .parent-stat.warning {
            border-color: #f1c40f;
            background: #fffbea;
        }

        .parent-stat.danger strong {
            color: #b91c1c;
        }

        .parent-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(300px, .8fr);
            gap: 18px;
            align-items: start;
        }

        .parent-stack {
            display: grid;
            gap: 18px;
        }

        .parent-panel {
            overflow: hidden;
        }

        .parent-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 15px 17px;
            border-bottom: 1px solid #e5eaf0;
        }

        .parent-panel-head h2 {
            margin: 0;
            color: #172536;
            font-size: 1rem;
            letter-spacing: 0;
        }

        .parent-panel-head span {
            color: #64748b;
            font-size: .78rem;
            font-weight: 800;
        }

        .parent-panel-body {
            padding: 17px;
        }

        .parent-action {
            display: inline-flex;
            min-height: 38px;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border: 1px solid #d5dee7;
            border-radius: 7px;
            background: #fff;
            color: #15477a;
            font-size: .82rem;
            font-weight: 900;
            text-decoration: none;
        }

        .parent-attendance {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            overflow: hidden;
            border: 1px solid #e1e7ed;
            border-radius: 8px;
            background: #e1e7ed;
            gap: 1px;
        }

        .parent-attendance-item {
            min-width: 0;
            padding: 13px;
            background: #f8fafc;
        }

        .parent-attendance-item strong,
        .parent-attendance-item span {
            display: block;
        }

        .parent-attendance-item strong {
            color: #15477a;
            font-size: 1.25rem;
            line-height: 1;
        }

        .parent-attendance-item span {
            margin-top: 6px;
            color: #64748b;
            font-size: .74rem;
            font-weight: 800;
        }

        .parent-attendance-item.warning strong {
            color: #b45309;
        }

        .parent-attendance-item.danger strong {
            color: #b91c1c;
        }

        .parent-schedule-row {
            display: grid;
            grid-template-columns: 46px 108px minmax(0, 1fr);
            gap: 13px;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #edf1f4;
        }

        .parent-schedule-row:first-child {
            padding-top: 0;
        }

        .parent-schedule-row:last-child,
        .parent-profile-row:last-child,
        .parent-notice:last-child,
        .parent-point-row:last-child,
        .parent-worship-row:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .parent-schedule-number {
            display: inline-flex;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #eef5fb;
            color: #15477a;
            font-weight: 900;
        }

        .parent-schedule-time {
            color: #475569;
            font-size: .78rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .parent-schedule-subject {
            min-width: 0;
        }

        .parent-schedule-subject strong {
            display: block;
            color: #172536;
            overflow-wrap: anywhere;
        }

        .parent-schedule-subject small {
            display: block;
            margin-top: 3px;
            color: #64748b;
        }

        .parent-worship-row {
            display: grid;
            gap: 10px;
            padding: 13px 0;
            border-bottom: 1px solid #edf1f4;
        }

        .parent-worship-head,
        .parent-worship-progress-label,
        .parent-point-total,
        .parent-point-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }

        .parent-worship-head strong {
            color: #172536;
        }

        .parent-worship-head small {
            display: block;
            margin-top: 3px;
            color: #64748b;
        }

        .parent-worship-progress-label {
            margin-bottom: 6px;
            color: #64748b;
            font-size: .76rem;
            font-weight: 800;
        }

        .parent-worship-progress-label strong {
            color: #15477a;
        }

        .parent-worship-progress {
            height: 8px;
            overflow: hidden;
            border-radius: 4px;
            background: #e8eef4;
        }

        .parent-worship-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: #15477a;
        }

        .parent-profile-row {
            display: grid;
            grid-template-columns: 108px minmax(0, 1fr);
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #edf1f4;
        }

        .parent-profile-row span {
            color: #64748b;
            font-size: .8rem;
            font-weight: 800;
        }

        .parent-profile-row strong {
            color: #172536;
            overflow-wrap: anywhere;
        }

        .parent-point-total {
            padding-bottom: 13px;
            border-bottom: 1px solid #e5eaf0;
        }

        .parent-point-total strong {
            color: #15477a;
            font-size: 2rem;
            line-height: 1;
        }

        .parent-point-total span,
        .parent-point-row small {
            color: #64748b;
            font-size: .76rem;
        }

        .parent-point-row {
            align-items: flex-start;
            padding: 10px 0;
            border-bottom: 1px solid #edf1f4;
        }

        .parent-point-row p {
            margin: 0;
            color: #334155;
            font-size: .82rem;
        }

        .parent-point-value {
            flex: 0 0 auto;
            color: #b91c1c;
            font-weight: 900;
        }

        .parent-point-value.reduction {
            color: #15803d;
        }

        .parent-notice {
            display: grid;
            grid-template-columns: 8px minmax(0, 1fr);
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #edf1f4;
        }

        .parent-notice-dot {
            width: 8px;
            height: 8px;
            margin-top: 5px;
            border-radius: 50%;
            background: #f1c40f;
        }

        .parent-notice.read .parent-notice-dot {
            background: #cbd5e1;
        }

        .parent-notice strong {
            color: #172536;
            font-size: .84rem;
        }

        .parent-notice p {
            margin: 3px 0;
            color: #64748b;
            font-size: .78rem;
            line-height: 1.4;
        }

        .parent-notice time {
            color: #94a3b8;
            font-size: .72rem;
        }

        .parent-empty {
            margin: 0;
            padding: 16px;
            color: #64748b;
            text-align: center;
        }

        @media (max-width: 960px) {
            .parent-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .parent-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 680px) {
            .parent-hero {
                grid-template-columns: 1fr;
                padding: 17px;
            }

            .parent-today {
                min-width: 0;
                padding: 14px 0 0;
                border-top: 1px solid rgba(255, 255, 255, .25);
                border-left: 0;
                text-align: left;
            }

            .parent-photo {
                width: 68px;
                height: 84px;
            }

            .parent-name {
                font-size: 1.45rem;
            }

            .parent-attendance {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .parent-schedule-row {
                grid-template-columns: 42px minmax(0, 1fr);
            }

            .parent-schedule-time {
                grid-column: 2;
                grid-row: 2;
            }

            .parent-schedule-subject {
                grid-column: 2;
                grid-row: 1;
            }

            .parent-profile-row {
                grid-template-columns: 1fr;
                gap: 3px;
            }
        }

        @media (max-width: 430px) {
            .parent-summary {
                grid-template-columns: 1fr;
            }

            .parent-identity {
                align-items: flex-start;
            }
        }
    </style>

    @php
        $labelKehadiranHariIni = match ($absensiHariIni?->status_kehadiran) {
            'hadir' => $absensiHariIni->menit_terlambat > 0 ? 'Terlambat '.$absensiHariIni->menit_terlambat.' menit' : 'Hadir tepat waktu',
            'sakit' => 'Sakit',
            'izin' => 'Izin',
            'alfa' => 'Alfa',
            default => $kodeHari === 'minggu' ? 'Hari Minggu' : 'Belum tercatat',
        };
        $statusPerluPerhatian = in_array($absensiHariIni?->status_kehadiran, ['alfa'], true)
            || ($absensiHariIni?->menit_terlambat ?? 0) > 0;
    @endphp

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="parent-dashboard">
        @if (! $siswaLogin)
            <section class="panel panel-pad">
                <p class="eyebrow">Dashboard Orang Tua</p>
                <h1 class="page-title">Akun belum terhubung ke data siswa</h1>
                <p class="help-text" style="margin-top: 8px;">
                    Hubungi administrator sekolah agar akun orang tua ini dihubungkan dengan siswa yang benar.
                </p>
            </section>
        @else
            <section class="parent-hero">
                <div class="parent-identity">
                    <div class="parent-photo">
                        <img src="{{ $urlFotoSiswa }}" alt="Foto {{ $siswaLogin->nama_lengkap }}">
                    </div>
                    <div>
                        <p class="eyebrow">Dashboard Orang Tua</p>
                        <h1 class="parent-name">{{ $siswaLogin->nama_lengkap }}</h1>
                        <div class="parent-meta">
                            <span>NISN {{ $siswaLogin->nisn ?: '-' }}</span>
                            <span>{{ $kelasAktif?->nama ?: 'Belum ditempatkan di kelas' }}</span>
                            <span>{{ $tahunPelajaranAktif?->nama ?: 'Tahun pelajaran belum tersedia' }}</span>
                        </div>
                    </div>
                </div>

                <div class="parent-today">
                    <span>{{ $hariIni->locale('id')->translatedFormat('l') }}</span>
                    <strong>{{ $hariIni->locale('id')->translatedFormat('d F Y') }}</strong>
                    <div class="parent-status">{{ $labelKehadiranHariIni }}</div>
                </div>
            </section>

            <section class="parent-summary" aria-label="Ringkasan anak">
                <article class="parent-stat {{ $statusPerluPerhatian ? 'warning' : '' }}">
                    <span>Presensi hari ini</span>
                    <strong>{{ $labelKehadiranHariIni }}</strong>
                    <small>{{ $absensiHariIni?->jam_masuk ? 'Masuk '.substr((string) $absensiHariIni->jam_masuk, 0, 5) : 'Belum ada jam masuk' }}</small>
                </article>
                <article class="parent-stat">
                    <span>Pelajaran hari ini</span>
                    <strong>{{ $jadwalHariIni->count() }}</strong>
                    <small>{{ $kelasAktif?->nama ?: 'Kelas belum tersedia' }}</small>
                </article>
                <article class="parent-stat">
                    <span>Nilai dipublikasikan</span>
                    <strong>{{ $jumlahNilaiDipublikasikan }}</strong>
                    <small>Mata pelajaran dengan nilai resmi</small>
                </article>
                <article class="parent-stat {{ $ringkasanPoin['total'] > 0 ? 'warning danger' : '' }}">
                    <span>Poin resmi</span>
                    <strong>{{ $ringkasanPoin['total'] }}</strong>
                    <small>{{ $ringkasanPoin['total'] > 0 ? 'Perlu perhatian orang tua' : 'Tidak ada poin aktif' }}</small>
                </article>
            </section>

            <div class="parent-grid">
                <div class="parent-stack">
                    <article class="parent-panel">
                        <header class="parent-panel-head">
                            <h2>Jadwal Pelajaran Hari Ini</h2>
                            <a href="{{ route('akademik-anak.index', ['tab' => 'jadwal']) }}" class="parent-action">Lihat jadwal</a>
                        </header>
                        <div class="parent-panel-body">
                            @forelse ($jadwalHariIni as $jadwal)
                                <div class="parent-schedule-row">
                                    <span class="parent-schedule-number">{{ $jadwal->jamPelajaran?->nomor_jam ?: '-' }}</span>
                                    <span class="parent-schedule-time">
                                        {{ $jadwal->jamPelajaran?->formatJam($jadwal->jamPelajaran?->jam_mulai) }}
                                        -
                                        {{ $jadwal->jamPelajaran?->formatJam($jadwal->jamPelajaran?->jam_selesai) }}
                                    </span>
                                    <div class="parent-schedule-subject">
                                        <strong>{{ $jadwal->mataPelajaranTerjadwal()?->nama ?: 'Mata pelajaran belum ditentukan' }}</strong>
                                        <small>{{ $jadwal->guruMataPelajaran?->pegawai?->nama_lengkap ?: 'Guru belum ditentukan' }}</small>
                                    </div>
                                </div>
                            @empty
                                <p class="parent-empty">{{ $kelasAktif ? 'Tidak ada jadwal pelajaran untuk hari ini.' : 'Kelas aktif belum ditentukan.' }}</p>
                            @endforelse
                        </div>
                    </article>

                    <article class="parent-panel">
                        <header class="parent-panel-head">
                            <h2>Presensi Bulan Ini</h2>
                            <span>{{ $labelBulan }}</span>
                        </header>
                        <div class="parent-panel-body">
                            <div class="parent-attendance">
                                <div class="parent-attendance-item"><strong>{{ $ringkasanKehadiran['hadir'] }}</strong><span>Hadir</span></div>
                                <div class="parent-attendance-item warning"><strong>{{ $ringkasanKehadiran['sakit'] }}</strong><span>Sakit</span></div>
                                <div class="parent-attendance-item warning"><strong>{{ $ringkasanKehadiran['izin'] }}</strong><span>Izin</span></div>
                                <div class="parent-attendance-item danger"><strong>{{ $ringkasanKehadiran['alfa'] }}</strong><span>Alfa</span></div>
                                <div class="parent-attendance-item warning"><strong>{{ $ringkasanKehadiran['terlambat'] }}</strong><span>Kali terlambat</span></div>
                                <div class="parent-attendance-item warning"><strong>{{ $ringkasanKehadiran['menit_terlambat'] }}</strong><span>Menit terlambat</span></div>
                                <div class="parent-attendance-item warning"><strong>{{ $ringkasanKehadiran['pulang_cepat'] }}</strong><span>Pulang cepat</span></div>
                                <div class="parent-attendance-item"><strong>{{ $ringkasanKehadiran['total_catatan'] }}</strong><span>Hari tercatat</span></div>
                            </div>
                        </div>
                    </article>

                    <article class="parent-panel">
                        <header class="parent-panel-head">
                            <h2>Presensi Ibadah Anak</h2>
                            <span>{{ $labelBulan }}</span>
                        </header>
                        <div class="parent-panel-body">
                            @forelse ($ringkasanIbadahSaya as $ibadah)
                                @php
                                    $presensiIbadahHariIni = $ibadah['presensi_hari_ini'];
                                    $kelasStatusIbadah = $presensiIbadahHariIni
                                        ? 'badge badge-active'
                                        : ($ibadah['dijadwalkan_hari_ini'] ? 'badge badge-warning' : 'badge badge-muted');
                                    $detailIbadahHariIni = $presensiIbadahHariIni
                                        ? 'Tercatat pukul '.substr((string) $presensiIbadahHariIni->waktu_scan, 0, 5)
                                        : ($ibadah['dijadwalkan_hari_ini'] ? 'Belum tercatat hari ini' : 'Tidak dijadwalkan hari ini');
                                @endphp
                                <div class="parent-worship-row">
                                    <div class="parent-worship-head">
                                        <div>
                                            <strong>{{ $ibadah['kegiatan']?->nama ?: 'Kegiatan ibadah' }}</strong>
                                            <small>{{ $detailIbadahHariIni }}</small>
                                        </div>
                                        <span class="{{ $kelasStatusIbadah }}">{{ $ibadah['tercatat'] }}/{{ $ibadah['target'] }}</span>
                                    </div>
                                    <div>
                                        <div class="parent-worship-progress-label">
                                            <span>Capaian bulan ini</span>
                                            <strong>{{ number_format($ibadah['persentase'], 1, ',', '.') }}%</strong>
                                        </div>
                                        <div class="parent-worship-progress" role="progressbar" aria-label="Capaian {{ $ibadah['kegiatan']?->nama }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $ibadah['persentase'] }}">
                                            <span style="width: {{ min($ibadah['persentase'], 100) }}%;"></span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="parent-empty">Belum ada kegiatan ibadah aktif pada tahun pelajaran ini.</p>
                            @endforelse
                        </div>
                    </article>
                </div>

                <aside class="parent-stack">
                    <article class="parent-panel">
                        <header class="parent-panel-head">
                            <h2>Informasi Anak</h2>
                            <a href="{{ route('kata-sandi.edit') }}" class="parent-action">Ganti Password</a>
                        </header>
                        <div class="parent-panel-body">
                            <div class="parent-profile-row"><span>Kelas</span><strong>{{ $kelasAktif?->nama ?: '-' }}</strong></div>
                            <div class="parent-profile-row"><span>Nomor absen</span><strong>{{ $anggotaKelasAktif?->nomor_absen ?: '-' }}</strong></div>
                            <div class="parent-profile-row"><span>Wali kelas</span><strong>{{ $waliKelas?->nama_lengkap ?: 'Belum ditentukan' }}</strong></div>
                            <div class="parent-profile-row"><span>Guru wali</span><strong>{{ $guruWali?->nama_lengkap ?: 'Belum ditentukan' }}</strong></div>
                            <div class="parent-profile-row"><span>Orang tua/wali</span><strong>{{ $orangTua?->nama_lengkap ?: auth()->user()?->nama }}</strong></div>
                        </div>
                    </article>

                    <article class="parent-panel">
                        <header class="parent-panel-head">
                            <h2>Nilai Anak</h2>
                            <a href="{{ route('akademik-anak.index', ['tab' => 'nilai']) }}" class="parent-action">Lihat nilai</a>
                        </header>
                        <div class="parent-panel-body">
                            <div class="parent-point-total">
                                <div>
                                    <strong>{{ $jumlahNilaiDipublikasikan }}</strong>
                                    <span>Mata pelajaran telah dipublikasikan</span>
                                </div>
                                <span class="badge badge-active">Terpublikasi</span>
                            </div>
                        </div>
                    </article>

                    <article class="parent-panel">
                        <header class="parent-panel-head">
                            <h2>Poin & Pembinaan</h2>
                            <a href="{{ route('pembinaan-poin-anak.index') }}" class="parent-action">Lihat detail</a>
                        </header>
                        <div class="parent-panel-body">
                            <div class="parent-point-total">
                                <div>
                                    <strong>{{ $ringkasanPoin['total'] }}</strong>
                                    <span>Total poin resmi</span>
                                </div>
                                <span class="badge {{ $ringkasanPoin['total'] > 0 ? 'badge-warning' : 'badge-active' }}">
                                    {{ $ringkasanPoin['total'] > 0 ? 'Perlu perhatian' : 'Baik' }}
                                </span>
                            </div>
                            <div class="parent-point-history">
                                @forelse ($riwayatPoinTerbaru as $transaksi)
                                    <div class="parent-point-row">
                                        <div>
                                            <p>{{ $transaksi->keterangan ?: str($transaksi->jenis)->headline() }}</p>
                                            <small>{{ $transaksi->tercatat_pada?->locale('id')->translatedFormat('d M Y') }}</small>
                                        </div>
                                        <span class="parent-point-value {{ $transaksi->poin < 0 ? 'reduction' : '' }}">
                                            {{ $transaksi->poin > 0 ? '+' : '' }}{{ $transaksi->poin }}
                                        </span>
                                    </div>
                                @empty
                                    <p class="parent-empty">Belum ada catatan poin resmi.</p>
                                @endforelse
                            </div>
                        </div>
                    </article>

                    <article class="parent-panel">
                        <header class="parent-panel-head">
                            <h2>Notifikasi Terbaru</h2>
                            <span>{{ $notifikasiDashboard->whereNull('dibaca_pada')->count() }} baru</span>
                        </header>
                        <div class="parent-panel-body">
                            @forelse ($notifikasiDashboard as $notifikasi)
                                <div class="parent-notice {{ $notifikasi->masihBelumDibaca() ? '' : 'read' }}">
                                    <span class="parent-notice-dot" aria-hidden="true"></span>
                                    <div>
                                        <strong>{{ $notifikasi->judul }}</strong>
                                        <p>{{ $notifikasi->pesan }}</p>
                                        <time datetime="{{ $notifikasi->created_at->toIso8601String() }}">{{ $notifikasi->created_at->diffForHumans() }}</time>
                                    </div>
                                </div>
                            @empty
                                <p class="parent-empty">Belum ada notifikasi baru.</p>
                            @endforelse
                        </div>
                    </article>
                </aside>
            </div>
        @endif
    </div>
@endsection
