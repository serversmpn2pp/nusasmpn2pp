@extends('layouts.app')

@section('title', 'Presensi Anak - NUSA')

@section('content')
    <style>
        .child-attendance-page {
            display: grid;
            gap: 18px;
        }

        .child-attendance-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 18px;
        }

        .child-attendance-head h1 {
            margin: 4px 0 0;
        }

        .child-attendance-identity {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: 13px;
            padding: 15px 17px;
            border: 1px solid #dce4eb;
            border-radius: 8px;
            background: #fff;
        }

        .child-attendance-photo {
            width: 54px;
            height: 64px;
            flex: 0 0 auto;
            overflow: hidden;
            border: 2px solid #f1c40f;
            border-radius: 7px;
            background: #f8fafc;
        }

        .child-attendance-photo img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }

        .child-attendance-identity strong,
        .child-attendance-identity span {
            display: block;
        }

        .child-attendance-identity strong {
            color: #172536;
            overflow-wrap: anywhere;
        }

        .child-attendance-identity span {
            margin-top: 4px;
            color: #64748b;
            font-size: .8rem;
        }

        .child-attendance-tools {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 14px;
            padding: 14px;
            border: 1px solid #dce4eb;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(31, 41, 55, .05);
        }

        .child-attendance-tabs {
            display: inline-grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            min-width: 300px;
            overflow: hidden;
            border: 1px solid #cfd9e3;
            border-radius: 7px;
        }

        .child-attendance-tab {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            padding: 9px 14px;
            color: #15477a;
            font-size: .84rem;
            font-weight: 900;
            text-decoration: none;
        }

        .child-attendance-tab + .child-attendance-tab {
            border-left: 1px solid #cfd9e3;
        }

        .child-attendance-tab.active {
            background: #15477a;
            color: #fff;
        }

        .child-attendance-filter {
            display: grid;
            gap: 5px;
        }

        .child-attendance-filter label {
            color: #334155;
            font-size: .76rem;
            font-weight: 900;
        }

        .child-attendance-filter .input {
            min-width: 190px;
        }

        .child-attendance-summary {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 10px;
        }

        .child-attendance-stat {
            min-width: 0;
            padding: 14px;
            border: 1px solid #dce4eb;
            border-radius: 8px;
            background: #fff;
        }

        .child-attendance-stat strong,
        .child-attendance-stat span {
            display: block;
        }

        .child-attendance-stat strong {
            color: #15477a;
            font-size: 1.35rem;
            line-height: 1;
        }

        .child-attendance-stat span {
            margin-top: 7px;
            color: #64748b;
            font-size: .74rem;
            font-weight: 800;
        }

        .child-attendance-stat.warning {
            border-color: #f1c40f;
            background: #fffbea;
        }

        .child-attendance-stat.warning strong {
            color: #a16207;
        }

        .child-attendance-stat.danger {
            border-color: #fecaca;
            background: #fff7f7;
        }

        .child-attendance-stat.danger strong {
            color: #b91c1c;
        }

        .child-attendance-panel {
            overflow: hidden;
            border: 1px solid #dce4eb;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(31, 41, 55, .05);
        }

        .child-attendance-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 15px 17px;
            border-bottom: 1px solid #e5eaf0;
        }

        .child-attendance-panel-head h2 {
            margin: 0;
            color: #172536;
            font-size: 1rem;
        }

        .child-attendance-panel-head span {
            color: #64748b;
            font-size: .78rem;
            font-weight: 800;
        }

        .child-attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .child-attendance-table th,
        .child-attendance-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #edf1f4;
            text-align: left;
            vertical-align: top;
        }

        .child-attendance-table th {
            background: #f8fafc;
            color: #475569;
            font-size: .72rem;
            text-transform: uppercase;
        }

        .child-attendance-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .child-attendance-date strong,
        .child-attendance-date span {
            display: block;
        }

        .child-attendance-date strong {
            color: #172536;
            font-size: .84rem;
        }

        .child-attendance-date span,
        .child-attendance-note {
            margin-top: 3px;
            color: #64748b;
            font-size: .76rem;
        }

        .child-attendance-time {
            color: #172536;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .child-attendance-mobile {
            display: none;
        }

        .child-attendance-card {
            padding: 14px;
            border-bottom: 1px solid #edf1f4;
        }

        .child-attendance-card:last-child {
            border-bottom: 0;
        }

        .child-attendance-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .child-attendance-card-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 12px;
        }

        .child-attendance-fact {
            padding: 10px;
            border: 1px solid #e3e9ef;
            border-radius: 7px;
            background: #f8fafc;
        }

        .child-attendance-fact span,
        .child-attendance-fact strong {
            display: block;
        }

        .child-attendance-fact span {
            color: #64748b;
            font-size: .7rem;
            font-weight: 800;
        }

        .child-attendance-fact strong {
            margin-top: 4px;
            color: #172536;
            font-size: .82rem;
        }

        .child-attendance-empty {
            margin: 0;
            padding: 28px 18px;
            color: #64748b;
            text-align: center;
        }

        @media (max-width: 1000px) {
            .child-attendance-summary {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 720px) {
            .child-attendance-head,
            .child-attendance-tools {
                align-items: stretch;
                flex-direction: column;
            }

            .child-attendance-tabs,
            .child-attendance-filter .input {
                width: 100%;
                min-width: 0;
            }

            .child-attendance-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .child-attendance-desktop {
                display: none;
            }

            .child-attendance-mobile {
                display: block;
            }
        }
    </style>

    @php
        $urlFoto = $siswa?->foto ? asset('storage/'.$siswa->foto) : asset('images/kartu-pelajar/default-user.png');
        $badgeSekolah = fn (string $status) => match ($status) {
            'hadir' => 'badge badge-active',
            'sakit', 'izin', 'belum_tercatat' => 'badge badge-warning',
            'alfa' => 'badge badge-danger',
            default => 'badge badge-muted',
        };
        $badgeIbadah = fn (string $status) => match ($status) {
            'tercatat' => 'badge badge-active',
            'berhalangan' => 'badge badge-muted',
            default => 'badge badge-warning',
        };
        $formatJam = fn ($jam) => $jam ? substr((string) $jam, 0, 5) : '-';
    @endphp

    <div class="child-attendance-page">
        <header class="child-attendance-head">
            <div>
                <p class="eyebrow">Informasi Anak</p>
                <h1 class="page-title">Presensi Anak</h1>
            </div>

            @if ($siswa)
                <div class="child-attendance-identity">
                    <div class="child-attendance-photo">
                        <img src="{{ $urlFoto }}" alt="Foto {{ $siswa->nama_lengkap }}">
                    </div>
                    <div>
                        <strong>{{ $siswa->nama_lengkap }}</strong>
                        <span>{{ $anggotaKelas?->kelas?->nama ?: 'Belum ditempatkan di kelas' }} &middot; NISN {{ $siswa->nisn ?: '-' }}</span>
                    </div>
                </div>
            @endif
        </header>

        @if (! $siswa)
            <div class="alert alert-danger">Akun orang tua ini belum terhubung dengan siswa. Silakan hubungi administrator sekolah.</div>
        @else
            @error('bulan')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror

            <section class="child-attendance-tools">
                <nav class="child-attendance-tabs" aria-label="Jenis presensi">
                    <a href="{{ route('presensi-anak.index', ['tab' => 'sekolah', 'bulan' => $bulan]) }}" class="child-attendance-tab {{ $tab === 'sekolah' ? 'active' : '' }}">Presensi Sekolah</a>
                    <a href="{{ route('presensi-anak.index', ['tab' => 'ibadah', 'bulan' => $bulan]) }}" class="child-attendance-tab {{ $tab === 'ibadah' ? 'active' : '' }}">Presensi Ibadah</a>
                </nav>

                <form method="GET" action="{{ route('presensi-anak.index') }}" class="child-attendance-filter">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <label for="bulan">Bulan</label>
                    <input id="bulan" type="month" name="bulan" value="{{ $bulan }}" min="{{ $bulanMinimum }}" max="{{ $bulanMaksimum }}" class="input" onchange="this.form.submit()">
                </form>
            </section>

            @if ($tab === 'sekolah')
                <section class="child-attendance-summary" aria-label="Ringkasan presensi sekolah">
                    <article class="child-attendance-stat"><strong>{{ $ringkasanSekolah['hadir'] }}</strong><span>Hadir</span></article>
                    <article class="child-attendance-stat warning"><strong>{{ $ringkasanSekolah['sakit'] }}</strong><span>Sakit</span></article>
                    <article class="child-attendance-stat warning"><strong>{{ $ringkasanSekolah['izin'] }}</strong><span>Izin</span></article>
                    <article class="child-attendance-stat danger"><strong>{{ $ringkasanSekolah['alfa'] }}</strong><span>Alfa</span></article>
                    <article class="child-attendance-stat warning"><strong>{{ $ringkasanSekolah['terlambat'] }}</strong><span>Kali terlambat</span></article>
                    <article class="child-attendance-stat"><strong>{{ $ringkasanSekolah['belum_tercatat'] }}</strong><span>Belum tercatat</span></article>
                </section>

                <section class="child-attendance-panel">
                    <header class="child-attendance-panel-head">
                        <h2>Riwayat Presensi Sekolah</h2>
                        <span>{{ $bulanLabel }}</span>
                    </header>

                    @if ($riwayatSekolah->isEmpty())
                        <p class="child-attendance-empty">Belum ada jadwal atau catatan presensi pada bulan ini.</p>
                    @else
                        <div class="child-attendance-desktop table-wrap">
                            <table class="child-attendance-table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Status</th>
                                        <th>Masuk</th>
                                        <th>Pulang</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($riwayatSekolah as $item)
                                        @php($absensi = $item['absensi'])
                                        <tr>
                                            <td class="child-attendance-date">
                                                <strong>{{ $item['tanggal']->locale('id')->translatedFormat('d F Y') }}</strong>
                                                <span>{{ $item['tanggal']->locale('id')->translatedFormat('l') }}</span>
                                            </td>
                                            <td>
                                                <span class="{{ $badgeSekolah($item['status']) }}">{{ $item['label_status'] }}</span>
                                                @if (($absensi?->menit_terlambat ?? 0) > 0)
                                                    <div class="child-attendance-note">Terlambat {{ $absensi->menit_terlambat }} menit</div>
                                                @endif
                                            </td>
                                            <td class="child-attendance-time">{{ $formatJam($absensi?->jam_masuk) }}</td>
                                            <td class="child-attendance-time">
                                                {{ $formatJam($absensi?->jam_pulang) }}
                                                @if (($absensi?->menit_pulang_cepat ?? 0) > 0)
                                                    <div class="child-attendance-note">Lebih cepat {{ $absensi->menit_pulang_cepat }} menit</div>
                                                @endif
                                            </td>
                                            <td class="child-attendance-note">{{ $absensi?->catatan ?: '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="child-attendance-mobile">
                            @foreach ($riwayatSekolah as $item)
                                @php($absensi = $item['absensi'])
                                <article class="child-attendance-card">
                                    <div class="child-attendance-card-head">
                                        <div class="child-attendance-date">
                                            <strong>{{ $item['tanggal']->locale('id')->translatedFormat('d F Y') }}</strong>
                                            <span>{{ $item['tanggal']->locale('id')->translatedFormat('l') }}</span>
                                        </div>
                                        <span class="{{ $badgeSekolah($item['status']) }}">{{ $item['label_status'] }}</span>
                                    </div>
                                    <div class="child-attendance-card-grid">
                                        <div class="child-attendance-fact"><span>Jam masuk</span><strong>{{ $formatJam($absensi?->jam_masuk) }}</strong></div>
                                        <div class="child-attendance-fact"><span>Jam pulang</span><strong>{{ $formatJam($absensi?->jam_pulang) }}</strong></div>
                                        <div class="child-attendance-fact"><span>Keterlambatan</span><strong>{{ ($absensi?->menit_terlambat ?? 0) > 0 ? $absensi->menit_terlambat.' menit' : '-' }}</strong></div>
                                        <div class="child-attendance-fact"><span>Pulang cepat</span><strong>{{ ($absensi?->menit_pulang_cepat ?? 0) > 0 ? $absensi->menit_pulang_cepat.' menit' : '-' }}</strong></div>
                                    </div>
                                    @if ($absensi?->catatan)
                                        <p class="child-attendance-note">{{ $absensi->catatan }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @else
                <section class="child-attendance-summary" aria-label="Ringkasan presensi ibadah">
                    <article class="child-attendance-stat"><strong>{{ $ringkasanIbadah['kegiatan_terjadwal'] }}</strong><span>Kegiatan terjadwal</span></article>
                    <article class="child-attendance-stat"><strong>{{ $ringkasanIbadah['tercatat'] }}</strong><span>Tercatat</span></article>
                    <article class="child-attendance-stat warning"><strong>{{ $ringkasanIbadah['berhalangan'] }}</strong><span>Berhalangan</span></article>
                    <article class="child-attendance-stat danger"><strong>{{ $ringkasanIbadah['belum_tercatat'] }}</strong><span>Belum tercatat</span></article>
                </section>

                <section class="child-attendance-panel">
                    <header class="child-attendance-panel-head">
                        <h2>Riwayat Presensi Ibadah</h2>
                        <span>{{ $bulanLabel }}</span>
                    </header>

                    @if ($riwayatIbadah->isEmpty())
                        <p class="child-attendance-empty">Belum ada kegiatan ibadah yang dijadwalkan pada bulan ini.</p>
                    @else
                        <div class="child-attendance-desktop table-wrap">
                            <table class="child-attendance-table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Kegiatan</th>
                                        <th>Status</th>
                                        <th>Waktu tercatat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($riwayatIbadah as $item)
                                        @php($catatanIbadah = $item['presensi'] ?: $item['presensi_berhalangan'])
                                        <tr>
                                            <td class="child-attendance-date">
                                                <strong>{{ $item['tanggal']->locale('id')->translatedFormat('d F Y') }}</strong>
                                                <span>{{ $item['tanggal']->locale('id')->translatedFormat('l') }}</span>
                                            </td>
                                            <td>
                                                <strong>{{ $item['jadwal']->kegiatanIbadah?->nama ?: 'Kegiatan ibadah' }}</strong>
                                                <div class="child-attendance-note">Pelaksanaan {{ $item['jadwal']->formatJam($item['jadwal']->jam_pelaksanaan) }}</div>
                                            </td>
                                            <td><span class="{{ $badgeIbadah($item['status']) }}">{{ $item['label_status'] }}</span></td>
                                            <td class="child-attendance-time">{{ $formatJam($catatanIbadah?->waktu_scan) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="child-attendance-mobile">
                            @foreach ($riwayatIbadah as $item)
                                @php($catatanIbadah = $item['presensi'] ?: $item['presensi_berhalangan'])
                                <article class="child-attendance-card">
                                    <div class="child-attendance-card-head">
                                        <div class="child-attendance-date">
                                            <strong>{{ $item['jadwal']->kegiatanIbadah?->nama ?: 'Kegiatan ibadah' }}</strong>
                                            <span>{{ $item['tanggal']->locale('id')->translatedFormat('l, d F Y') }}</span>
                                        </div>
                                        <span class="{{ $badgeIbadah($item['status']) }}">{{ $item['label_status'] }}</span>
                                    </div>
                                    <div class="child-attendance-card-grid">
                                        <div class="child-attendance-fact"><span>Pelaksanaan</span><strong>{{ $item['jadwal']->formatJam($item['jadwal']->jam_pelaksanaan) }}</strong></div>
                                        <div class="child-attendance-fact"><span>Waktu tercatat</span><strong>{{ $formatJam($catatanIbadah?->waktu_scan) }}</strong></div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        @endif
    </div>
@endsection
