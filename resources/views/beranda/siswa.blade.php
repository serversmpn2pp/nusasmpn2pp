@extends('layouts.app')

@section('title', 'Dashboard Siswa - NUSA')

@section('content')
    <style>
        .student-dashboard {
            display: grid;
            gap: 18px;
        }

        .student-hero {
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

        .student-identity {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: 17px;
        }

        .student-photo {
            width: 82px;
            height: 98px;
            flex: 0 0 auto;
            overflow: hidden;
            border: 3px solid #f1c40f;
            border-radius: 8px;
            background: #fff;
        }

        .student-photo img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }

        .student-hero .eyebrow {
            margin: 0 0 5px;
            color: #f1c40f;
        }

        .student-name {
            margin: 0;
            overflow-wrap: anywhere;
            color: #fff;
            font-size: 2rem;
            line-height: 1.15;
            letter-spacing: 0;
        }

        .student-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 7px 16px;
            margin: 9px 0 0;
            color: #dbeafe;
            font-size: .9rem;
        }

        .student-today {
            min-width: 235px;
            padding-left: 22px;
            border-left: 1px solid rgba(255, 255, 255, .25);
            text-align: right;
        }

        .student-today strong {
            display: block;
            margin-top: 6px;
            color: #f1c40f;
            font-size: 1.05rem;
        }

        .student-today span {
            color: #dbeafe;
            font-size: .86rem;
        }

        .student-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
            margin-top: 12px;
            padding: 6px 10px;
            border-radius: 6px;
            background: #fff;
            color: #15477a;
            font-size: .78rem;
            font-weight: 900;
        }

        .student-dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(300px, .8fr);
            gap: 18px;
            align-items: start;
        }

        .student-stack {
            display: grid;
            gap: 18px;
        }

        .student-panel {
            overflow: hidden;
            border: 1px solid #dce4eb;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(31, 41, 55, .06);
        }

        .student-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 15px 17px;
            border-bottom: 1px solid #e5eaf0;
        }

        .student-panel-head h2 {
            margin: 0;
            color: #172536;
            font-size: 1rem;
            letter-spacing: 0;
        }

        .student-panel-head span {
            color: #64748b;
            font-size: .78rem;
            font-weight: 800;
        }

        .student-panel-body {
            padding: 17px;
        }

        .attendance-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            border: 1px solid #e1e7ed;
            border-radius: 8px;
            background: #f8fafc;
        }

        .attendance-item {
            min-width: 0;
            padding: 13px;
            border-right: 1px solid #e1e7ed;
        }

        .attendance-item:nth-child(4n) {
            border-right: 0;
        }

        .attendance-item:nth-child(n+5) {
            border-top: 1px solid #e1e7ed;
        }

        .attendance-item strong {
            display: block;
            color: #15477a;
            font-size: 1.25rem;
            line-height: 1;
        }

        .attendance-item span {
            display: block;
            margin-top: 6px;
            color: #64748b;
            font-size: .76rem;
            font-weight: 800;
        }

        .attendance-item.warning strong {
            color: #b45309;
        }

        .attendance-item.danger strong {
            color: #b91c1c;
        }

        .worship-list {
            display: grid;
        }

        .worship-row {
            display: grid;
            gap: 12px;
            padding: 15px 0;
            border-bottom: 1px solid #edf1f4;
        }

        .worship-row:first-child {
            padding-top: 0;
        }

        .worship-row:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .worship-head,
        .worship-progress-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }

        .worship-head strong {
            display: block;
            color: #172536;
            font-size: .94rem;
        }

        .worship-head > div {
            min-width: 0;
        }

        .worship-head > .badge {
            max-width: 46%;
            flex: 0 0 auto;
            white-space: normal;
            text-align: center;
        }

        .worship-head small {
            display: block;
            margin-top: 3px;
            color: #64748b;
            font-size: .75rem;
        }

        .worship-progress-label {
            color: #64748b;
            font-size: .76rem;
            font-weight: 800;
        }

        .worship-progress-label strong {
            color: #15477a;
            font-size: .82rem;
        }

        .worship-progress {
            height: 8px;
            overflow: hidden;
            border-radius: 4px;
            background: #e8eef4;
        }

        .worship-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: #15477a;
        }

        .worship-facts {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1px;
            overflow: hidden;
            border: 1px solid #e1e7ed;
            border-radius: 7px;
            background: #e1e7ed;
        }

        .worship-fact {
            min-width: 0;
            padding: 10px;
            background: #f8fafc;
        }

        .worship-fact strong,
        .worship-fact span {
            display: block;
        }

        .worship-fact strong {
            color: #172536;
            font-size: 1rem;
        }

        .worship-fact span {
            margin-top: 3px;
            color: #64748b;
            font-size: .72rem;
            font-weight: 800;
        }

        .schedule-list {
            display: grid;
        }

        .schedule-row {
            display: grid;
            grid-template-columns: 46px 108px minmax(0, 1fr);
            gap: 13px;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #edf1f4;
        }

        .schedule-row:first-child {
            padding-top: 0;
        }

        .schedule-row:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .schedule-number {
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

        .schedule-time {
            color: #475569;
            font-size: .78rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .schedule-subject {
            min-width: 0;
        }

        .schedule-subject strong,
        .profile-value {
            color: #172536;
            overflow-wrap: anywhere;
        }

        .schedule-subject small {
            display: block;
            margin-top: 3px;
            color: #64748b;
        }

        .profile-list {
            display: grid;
        }

        .profile-row {
            display: grid;
            grid-template-columns: 110px minmax(0, 1fr);
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #edf1f4;
        }

        .profile-row:last-child {
            border-bottom: 0;
        }

        .profile-label {
            color: #64748b;
            font-size: .8rem;
            font-weight: 800;
        }

        .point-total {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 14px;
            border-bottom: 1px solid #e5eaf0;
        }

        .point-total strong {
            color: #15477a;
            font-size: 2rem;
            line-height: 1;
        }

        .point-total span {
            color: #64748b;
            font-size: .82rem;
        }

        .point-facts {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 12px;
        }

        .point-fact {
            padding: 7px 11px;
            border-right: 1px solid #e5eaf0;
        }

        .point-fact:last-child {
            border-right: 0;
        }

        .point-fact strong {
            display: block;
            color: #172536;
        }

        .point-fact span {
            color: #64748b;
            font-size: .75rem;
        }

        .activity-list {
            display: grid;
            margin-top: 13px;
        }

        .activity-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 9px 0;
            border-top: 1px solid #edf1f4;
        }

        .activity-row p {
            margin: 0;
            color: #334155;
            font-size: .82rem;
            line-height: 1.4;
        }

        .activity-row small {
            display: block;
            margin-top: 3px;
            color: #64748b;
        }

        .activity-value {
            flex: 0 0 auto;
            color: #b91c1c;
            font-weight: 900;
        }

        .activity-value.reduction {
            color: #15803d;
        }

        .notice-list {
            display: grid;
        }

        .notice-row {
            display: grid;
            grid-template-columns: 8px minmax(0, 1fr);
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #edf1f4;
        }

        .notice-row:last-child {
            border-bottom: 0;
        }

        .notice-dot {
            width: 8px;
            height: 8px;
            margin-top: 5px;
            border-radius: 50%;
            background: #f1c40f;
        }

        .notice-row.read .notice-dot {
            background: #cbd5e1;
        }

        .notice-row strong {
            display: block;
            color: #172536;
            font-size: .84rem;
        }

        .notice-row p {
            margin: 3px 0;
            color: #64748b;
            font-size: .78rem;
            line-height: 1.4;
        }

        .notice-row time {
            color: #94a3b8;
            font-size: .72rem;
        }

        .student-empty {
            margin: 0;
            padding: 20px;
            color: #64748b;
            text-align: center;
        }

        .student-action {
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

        @media (max-width: 960px) {
            .student-dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 680px) {
            .student-hero {
                grid-template-columns: 1fr;
                padding: 17px;
            }

            .student-today {
                min-width: 0;
                padding: 14px 0 0;
                border-top: 1px solid rgba(255, 255, 255, .25);
                border-left: 0;
                text-align: left;
            }

            .student-photo {
                width: 68px;
                height: 84px;
            }

            .student-name {
                font-size: 1.45rem;
            }

            .worship-head {
                align-items: flex-start;
            }

            .attendance-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .attendance-item:nth-child(2n) {
                border-right: 0;
            }

            .attendance-item:nth-child(n+3) {
                border-top: 1px solid #e1e7ed;
            }

            .schedule-row {
                grid-template-columns: 42px minmax(0, 1fr);
            }

            .schedule-time {
                grid-column: 2;
                grid-row: 2;
            }

            .schedule-subject {
                grid-column: 2;
                grid-row: 1;
            }

            .profile-row {
                grid-template-columns: 1fr;
                gap: 3px;
            }
        }
    </style>

    @php
        $labelKehadiranHariIni = match ($absensiHariIni?->status_kehadiran) {
            'hadir' => $absensiHariIni->menit_terlambat > 0 ? 'Hadir, terlambat ' . $absensiHariIni->menit_terlambat . ' menit' : 'Hadir tepat waktu',
            'sakit' => 'Sakit',
            'izin' => 'Izin',
            'alfa' => 'Alfa',
            default => $kodeHari === 'minggu' ? 'Hari Minggu' : 'Belum tercatat',
        };
    @endphp

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="student-dashboard">
        @if (! $siswaLogin)
            <section class="panel panel-pad">
                <p class="eyebrow">Dashboard Siswa</p>
                <h1 class="page-title">Akun belum terhubung ke data siswa</h1>
                <p class="help-text" style="margin-top: 8px;">
                    Hubungi administrator sekolah agar akun ini dihubungkan dengan data siswa yang benar.
                </p>
            </section>
        @else
            <section class="student-hero">
                <div class="student-identity">
                    <div class="student-photo">
                        <img src="{{ $urlFotoSiswa }}" alt="Foto {{ $siswaLogin->nama_lengkap }}">
                    </div>
                    <div>
                        <p class="eyebrow">Dashboard Siswa</p>
                        <h1 class="student-name">{{ $siswaLogin->nama_lengkap }}</h1>
                        <div class="student-meta">
                            <span>NISN {{ $siswaLogin->nisn ?: '-' }}</span>
                            <span>{{ $kelasAktif?->nama ?: 'Belum ditempatkan di kelas' }}</span>
                            <span>{{ $tahunPelajaranAktif?->nama ?: 'Tahun pelajaran belum tersedia' }}</span>
                        </div>
                    </div>
                </div>

                <div class="student-today">
                    <span>{{ $hariIni->locale('id')->translatedFormat('l') }}</span>
                    <strong>{{ $hariIni->locale('id')->translatedFormat('d F Y') }}</strong>
                    <div class="student-status">{{ $labelKehadiranHariIni }}</div>
                </div>
            </section>

            <div class="student-dashboard-grid">
                <div class="student-stack">
                    <article class="student-panel">
                        <header class="student-panel-head">
                            <h2>Jadwal Pelajaran Hari Ini</h2>
                            <span>{{ $hariIni->locale('id')->translatedFormat('l') }}</span>
                        </header>
                        <div class="student-panel-body">
                            <div class="schedule-list">
                                @forelse ($jadwalHariIni as $jadwal)
                                    <div class="schedule-row">
                                        <span class="schedule-number">{{ $jadwal->jamPelajaran?->nomor_jam ?: '-' }}</span>
                                        <span class="schedule-time">
                                            {{ $jadwal->jamPelajaran?->formatJam($jadwal->jamPelajaran?->jam_mulai) }}
                                            -
                                            {{ $jadwal->jamPelajaran?->formatJam($jadwal->jamPelajaran?->jam_selesai) }}
                                        </span>
                                        <div class="schedule-subject">
                                            <strong>{{ $jadwal->mataPelajaranTerjadwal()?->nama ?: 'Mata pelajaran belum ditentukan' }}</strong>
                                            <small>{{ $jadwal->guruMataPelajaran?->pegawai?->nama_lengkap ?: ($jadwal->mataPelajaran?->kelompok ?: 'Guru belum ditentukan') }}</small>
                                        </div>
                                    </div>
                                @empty
                                    <p class="student-empty">
                                        {{ $kelasAktif ? 'Tidak ada jadwal pelajaran untuk hari ini.' : 'Kelas aktif belum ditentukan.' }}
                                    </p>
                                @endforelse
                            </div>
                        </div>
                    </article>

                    <article class="student-panel">
                        <header class="student-panel-head">
                            <h2>Kehadiran Bulan Ini</h2>
                            <span>{{ $labelBulan }}</span>
                        </header>
                        <div class="student-panel-body">
                            <div class="attendance-summary">
                                <div class="attendance-item">
                                    <strong>{{ $ringkasanKehadiran['hadir'] }}</strong>
                                    <span>Hadir</span>
                                </div>
                                <div class="attendance-item warning">
                                    <strong>{{ $ringkasanKehadiran['sakit'] }}</strong>
                                    <span>Sakit</span>
                                </div>
                                <div class="attendance-item warning">
                                    <strong>{{ $ringkasanKehadiran['izin'] }}</strong>
                                    <span>Izin</span>
                                </div>
                                <div class="attendance-item danger">
                                    <strong>{{ $ringkasanKehadiran['alfa'] }}</strong>
                                    <span>Alfa</span>
                                </div>
                                <div class="attendance-item warning">
                                    <strong>{{ $ringkasanKehadiran['terlambat'] }}</strong>
                                    <span>Kali terlambat</span>
                                </div>
                                <div class="attendance-item warning">
                                    <strong>{{ $ringkasanKehadiran['menit_terlambat'] }}</strong>
                                    <span>Menit terlambat</span>
                                </div>
                                <div class="attendance-item warning">
                                    <strong>{{ $ringkasanKehadiran['pulang_cepat'] }}</strong>
                                    <span>Pulang cepat</span>
                                </div>
                                <div class="attendance-item">
                                    <strong>{{ $ringkasanKehadiran['total_catatan'] }}</strong>
                                    <span>Hari tercatat</span>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="student-panel">
                        <header class="student-panel-head">
                            <h2>Ibadah Saya</h2>
                            <span>{{ $labelBulan }}</span>
                        </header>
                        <div class="student-panel-body">
                            <div class="worship-list">
                                @forelse ($ringkasanIbadahSaya as $ibadah)
                                    @php
                                        $presensiHariIni = $ibadah['presensi_hari_ini'];
                                        $kelasStatusIbadah = $presensiHariIni
                                            ? 'badge badge-active'
                                            : ($ibadah['dijadwalkan_hari_ini'] ? 'badge badge-warning' : 'badge badge-muted');
                                        $detailHariIni = $presensiHariIni
                                            ? 'Pukul '.substr((string) $presensiHariIni->waktu_scan, 0, 5).' - '.str($presensiHariIni->sumber)->headline()
                                            : ($ibadah['dijadwalkan_hari_ini'] ? 'Menunggu presensi hari ini' : 'Tidak ada jadwal hari ini');
                                    @endphp
                                    <section class="worship-row">
                                        <div class="worship-head">
                                            <div>
                                                <strong>{{ $ibadah['kegiatan']?->nama ?: 'Kegiatan ibadah' }}</strong>
                                                <small>{{ $detailHariIni }}</small>
                                            </div>
                                            <span class="{{ $kelasStatusIbadah }}">{{ $ibadah['status_hari_ini'] }}</span>
                                        </div>

                                        <div>
                                            <div class="worship-progress-label">
                                                <span>Capaian bulan ini</span>
                                                <strong>{{ number_format($ibadah['persentase'], 1, ',', '.') }}%</strong>
                                            </div>
                                            <div class="worship-progress" role="progressbar" aria-label="Capaian {{ $ibadah['kegiatan']?->nama }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $ibadah['persentase'] }}">
                                                <span style="width: {{ min($ibadah['persentase'], 100) }}%;"></span>
                                            </div>
                                        </div>

                                        <div class="worship-facts">
                                            <div class="worship-fact">
                                                <strong>{{ $ibadah['target'] }}</strong>
                                                <span>Hari kegiatan</span>
                                            </div>
                                            <div class="worship-fact">
                                                <strong>{{ $ibadah['tercatat'] }}</strong>
                                                <span>Tercatat</span>
                                            </div>
                                            <div class="worship-fact">
                                                <strong>{{ $ibadah['belum'] }}</strong>
                                                <span>Belum tercatat</span>
                                            </div>
                                        </div>
                                    </section>
                                @empty
                                    <p class="student-empty">Belum ada kegiatan ibadah aktif pada tahun pelajaran ini.</p>
                                @endforelse
                            </div>
                        </div>
                    </article>
                </div>

                <aside class="student-stack">
                    <article class="student-panel">
                        <header class="student-panel-head">
                            <h2>Informasi Sekolah</h2>
                            <a href="{{ route('kata-sandi.edit') }}" class="student-action">Ganti Password</a>
                        </header>
                        <div class="student-panel-body">
                            <div class="profile-list">
                                <div class="profile-row">
                                    <span class="profile-label">Kelas</span>
                                    <strong class="profile-value">{{ $kelasAktif?->nama ?: '-' }}</strong>
                                </div>
                                <div class="profile-row">
                                    <span class="profile-label">Nomor absen</span>
                                    <strong class="profile-value">{{ $anggotaKelasAktif?->nomor_absen ?: '-' }}</strong>
                                </div>
                                <div class="profile-row">
                                    <span class="profile-label">Wali kelas</span>
                                    <strong class="profile-value">{{ $waliKelas?->nama_lengkap ?: 'Belum ditentukan' }}</strong>
                                </div>
                                <div class="profile-row">
                                    <span class="profile-label">Guru wali</span>
                                    <strong class="profile-value">{{ $guruWali?->nama_lengkap ?: 'Belum ditentukan' }}</strong>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="student-panel">
                        <header class="student-panel-head">
                            <h2>Nilai Saya</h2>
                            <a href="{{ route('nilai-saya.index') }}" class="student-action">Lihat Nilai</a>
                        </header>
                        <div class="student-panel-body">
                            <div class="point-total">
                                <div>
                                    <strong>{{ $jumlahNilaiDipublikasikan }}</strong>
                                    <span>Mata pelajaran telah dipublikasikan</span>
                                </div>
                                <span class="badge badge-active">Data resmi</span>
                            </div>
                        </div>
                    </article>

                    <article class="student-panel">
                        <header class="student-panel-head">
                            <h2>Poin Saya</h2>
                            <a href="{{ route('progress-kasus-siswa.index') }}" class="student-action">Lihat Progress</a>
                        </header>
                        <div class="student-panel-body">
                            <div class="point-total">
                                <div>
                                    <strong>{{ $ringkasanPoin['total'] }}</strong>
                                    <span>Total poin tahun pelajaran ini</span>
                                </div>
                                <span class="badge {{ $ringkasanPoin['total'] > 0 ? 'badge-warning' : 'badge-active' }}">
                                    {{ $ringkasanPoin['total'] > 0 ? 'Perlu perhatian' : 'Baik' }}
                                </span>
                            </div>
                            <div class="point-facts">
                                <div class="point-fact">
                                    <strong>{{ $ringkasanPoin['pelanggaran'] }}</strong>
                                    <span>Pelanggaran disahkan</span>
                                </div>
                                <div class="point-fact">
                                    <strong>{{ $ringkasanPoin['pengurangan'] }}</strong>
                                    <span>Poin dikurangi</span>
                                </div>
                            </div>

                            <div class="activity-list">
                                @foreach ($riwayatPoinTerbaru as $transaksi)
                                    <div class="activity-row">
                                        <div>
                                            <p>{{ $transaksi->keterangan ?: str($transaksi->jenis)->headline() }}</p>
                                            <small>{{ $transaksi->tercatat_pada?->locale('id')->translatedFormat('d M Y') }}</small>
                                        </div>
                                        <span class="activity-value {{ $transaksi->poin < 0 ? 'reduction' : '' }}">
                                            {{ $transaksi->poin > 0 ? '+' : '' }}{{ $transaksi->poin }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </article>

                    <article class="student-panel">
                        <header class="student-panel-head">
                            <h2>Notifikasi Terbaru</h2>
                            <span>{{ $notifikasiDashboard->whereNull('dibaca_pada')->count() }} baru</span>
                        </header>
                        <div class="student-panel-body">
                            <div class="notice-list">
                                @forelse ($notifikasiDashboard as $notifikasi)
                                    <div class="notice-row {{ $notifikasi->masihBelumDibaca() ? '' : 'read' }}">
                                        <span class="notice-dot" aria-hidden="true"></span>
                                        <div>
                                            <strong>{{ $notifikasi->judul }}</strong>
                                            <p>{{ $notifikasi->pesan }}</p>
                                            <time datetime="{{ $notifikasi->created_at->toIso8601String() }}">
                                                {{ $notifikasi->created_at->diffForHumans() }}
                                            </time>
                                        </div>
                                    </div>
                                @empty
                                    <p class="student-empty">Belum ada notifikasi baru.</p>
                                @endforelse
                            </div>
                        </div>
                    </article>
                </aside>
            </div>
        @endif
    </div>
@endsection
