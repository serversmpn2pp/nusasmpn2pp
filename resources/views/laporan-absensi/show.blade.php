@extends('layouts.app')

@section('title', 'Rincian Presensi Siswa - NUSA')

@section('content')
    @php
        $anggota = $item['anggota_kelas'];
        $parameter = array_filter([
            'tahun_pelajaran_id' => $laporan['tahunPelajaranId'], 'kelas_id' => $laporan['kelasId'],
            'periode' => $laporan['periode'], 'tanggal' => $laporan['tanggal'], 'bulan' => $laporan['bulan'],
            'semester' => $laporan['semester'], 'tanggal_mulai' => $laporan['tanggalMulai'], 'tanggal_selesai' => $laporan['tanggalSelesai'],
        ], fn ($nilai) => filled($nilai));
        $labelFilter = ['semua' => 'Semua hari', 'sakit' => 'Sakit', 'izin' => 'Izin', 'alfa' => 'Alfa', 'terlambat' => 'Terlambat', 'hadir' => 'Hadir', 'belum_scan' => 'Belum dikonfirmasi', 'pengecualian' => 'Pengecualian presensi'];
        $badgeStatus = fn ($status) => match ($status) {
            'hadir' => 'badge-active', 'sakit' => 'badge-warning', 'izin' => 'presensi-badge-izin', 'alfa' => 'badge-danger', default => 'badge-muted',
        };
        $urlRincian = route('laporan-absensi.show', ['anggotaKelas' => $anggota, ...$parameter]);
    @endphp

    <style>
        .presensi-detail-icon { width: 18px; height: 18px; flex: 0 0 18px; }
        .presensi-detail-back { gap: 8px; }
        .presensi-identity { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; padding: 0 0 22px; border-bottom: 1px solid var(--line); }
        .presensi-identity h2 { margin: 0 0 5px; font-size: 1.2rem; overflow-wrap: anywhere; }
        .presensi-identity p { margin: 0; color: var(--muted); font-size: .9rem; }
        .presensi-period { text-align: right; max-width: 420px; }
        .presensi-period strong { display: block; color: var(--primary); font-size: .93rem; }
        .presensi-summary { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); margin: 0 0 24px; padding: 20px 0; border-bottom: 1px solid var(--line); gap: 16px; }
        .presensi-summary dt { color: var(--muted); font-size: .84rem; }
        .presensi-summary dd { margin: 5px 0 0; font-weight: 800; font-size: 1.45rem; font-variant-numeric: tabular-nums; }
        .presensi-summary dd span { font-weight: 500; font-size: .8rem; color: var(--muted); }
        .presensi-summary .is-alfa dd { color: var(--danger); }
        .presensi-summary .is-late dd { color: #946200; }
        .presensi-history { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
        .presensi-history-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 20px; border-bottom: 1px solid var(--line); }
        .presensi-history-head h2 { margin: 0; font-size: 1.02rem; }
        .presensi-history-head p { margin: 3px 0 0; font-size: .85rem; color: var(--muted); }
        .presensi-detail-filter { display: flex; flex-wrap: wrap; align-items: end; gap: 10px; }
        .presensi-detail-filter .field { min-width: 200px; }
        .presensi-detail-filter .button { width: auto; }
        .presensi-detail-table { min-width: 780px; }
        .presensi-detail-table th, .presensi-detail-table td { padding: 14px 18px; }
        .presensi-detail-table td:last-child { min-width: 200px; max-width: 350px; }
        .presensi-detail-table .person-meta { font-size: .82rem; }
        .presensi-detail-table .date-cell { min-width: 155px; }
        .presensi-source { margin: 6px 0 0; color: var(--muted); font-size: .8rem; max-width: 185px; }
        .presensi-history .badge { white-space: normal; text-align: left; }
        .presensi-badge-izin { color: #176144; background: #eaf7ef; }
        .presensi-late { color: #946200; font-weight: 800; white-space: nowrap; }
        .presensi-note { margin: 0; overflow-wrap: anywhere; font-size: .88rem; }
        .presensi-detail-mobile { display: none; }
        .presensi-day { padding: 18px 20px; border-bottom: 1px solid var(--line); }
        .presensi-day:last-child { border-bottom: 0; }
        .presensi-day-head { display: flex; align-items: start; justify-content: space-between; gap: 12px; }
        .presensi-day .quick-facts { margin: 16px 0; grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .presensi-day .presensi-source { max-width: none; }
        .presensi-day .presensi-note { margin-top: 10px; }
        @media (max-width: 1100px) {
            .presensi-summary { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .presensi-detail-desktop { display: none; }
            .presensi-detail-mobile { display: block; }
        }
        @media (max-width: 620px) {
            .presensi-period { text-align: left; }
            .presensi-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .presensi-history-head, .presensi-day { padding: 16px; }
            .presensi-detail-filter { width: 100%; }
            .presensi-detail-filter .field { width: 100%; min-width: 0; }
            .presensi-detail-filter .button { flex: 1; }
            .presensi-day .quick-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>

    <div class="page-header">
        <div><p class="eyebrow">Presensi siswa</p><h1 class="page-title">Rincian presensi</h1></div>
        <a href="{{ route('laporan-absensi.index', $parameter) }}" class="button button-muted presensi-detail-back" data-presensi-kembali>
            <img src="{{ asset('images/icons/arrow-left.svg') }}" alt="" aria-hidden="true" class="presensi-detail-icon"> Laporan presensi
        </a>
    </div>

    <section class="presensi-identity" aria-label="Identitas siswa">
        <div><h2>{{ $anggota->siswa?->nama_lengkap ?: '-' }}</h2><p>{{ $anggota->kelas?->nama }} &middot; No. absen {{ $anggota->nomor_absen ?: '-' }} &middot; {{ $anggota->tahunPelajaran?->nama }}</p><p>NIS {{ $anggota->siswa?->nis ?: '-' }} &middot; NISN {{ $anggota->siswa?->nisn ?: '-' }}</p></div>
        <div class="presensi-period"><strong>{{ $laporan['labelPeriode'] }}</strong><p>{{ $laporan['jumlahHariEfektif'] }} hari presensi aktif</p></div>
    </section>

    <dl class="presensi-summary" aria-label="Ringkasan periode">
        <div><dt>Hadir</dt><dd>{{ $item['hadir'] }} <span>hari</span></dd></div>
        <div><dt>Sakit</dt><dd>{{ $item['sakit'] }} <span>hari</span></dd></div>
        <div><dt>Izin</dt><dd>{{ $item['izin'] }} <span>hari</span></dd></div>
        <div class="is-alfa"><dt>Alfa</dt><dd>{{ $item['alfa'] }} <span>hari</span></dd></div>
        <div class="is-late"><dt>Terlambat</dt><dd>{{ $item['terlambat'] }} <span>kali</span></dd></div>
        <div class="is-late"><dt>Total terlambat</dt><dd>{{ $item['menit_terlambat'] }} <span>menit</span></dd></div>
    </dl>

    <section class="presensi-history" aria-label="Riwayat presensi siswa">
        <div class="presensi-history-head">
            <div><h2>Riwayat harian</h2><p>{{ $rincian->count() }} hari &middot; {{ $labelFilter[$statusRincian] }}</p></div>
            <form action="{{ route('laporan-absensi.show', $anggota) }}" method="GET" class="presensi-detail-filter">
                @foreach ($parameter as $kunci => $nilai)<input type="hidden" name="{{ $kunci }}" value="{{ $nilai }}">@endforeach
                <div class="field"><label for="status-rincian">Status / kejadian</label><select id="status-rincian" name="status_rincian" class="select">
                    @foreach ($labelFilter as $kode => $label)<option value="{{ $kode }}" @selected($statusRincian === $kode)>{{ $label }}</option>@endforeach
                </select></div>
                <button type="submit" class="button button-dark">Tampilkan</button>
                @if ($statusRincian !== 'semua')<a href="{{ $urlRincian }}" class="button button-muted">Reset</a>@endif
            </form>
        </div>
        @if ($rincian->isEmpty())
            <div class="empty-state">Tidak ada riwayat {{ $statusRincian === 'semua' ? 'presensi' : strtolower($labelFilter[$statusRincian]) }} pada periode ini.</div>
        @else
            <div class="presensi-detail-desktop table-wrap">
                <table class="employee-table presensi-detail-table">
                    <thead><tr><th>Hari / tanggal</th><th>Status</th><th>Datang</th><th>Pulang</th><th>Terlambat</th><th>Catatan</th></tr></thead>
                    <tbody>
                        @foreach ($rincian as $baris)
                            <tr data-presensi-row data-tanggal="{{ $baris['tanggal'] }}" data-status="{{ $baris['status'] }}">
                                <td class="date-cell"><p class="person-name">{{ $baris['hari_label'] }}</p><p class="person-meta">{{ $baris['tanggal_panjang'] }}</p></td>
                                <td><span class="badge {{ $badgeStatus($baris['status']) }}">{{ $baris['status_label'] }}</span><p class="presensi-source">{{ $baris['sumber_label'] }}</p></td>
                                <td>{{ $baris['jam_masuk'] ?: '-' }}</td>
                                <td>{{ $baris['jam_pulang'] ?: '-' }}@if ($baris['belum_pulang'])<p class="person-meta">Belum scan pulang</p>@endif</td>
                                <td>@if ($baris['menit_terlambat'] > 0)<span class="presensi-late">{{ $baris['menit_terlambat'] }} menit</span>@else<span class="muted">-</span>@endif</td>
                                <td><p class="presensi-note">{{ $baris['catatan'] ?: '-' }}</p>@if ($baris['menit_pulang_cepat'] > 0)<p class="person-meta">Pulang cepat {{ $baris['menit_pulang_cepat'] }} menit</p>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="presensi-detail-mobile">
                @foreach ($rincian as $baris)
                    <article class="presensi-day" data-presensi-row data-tanggal="{{ $baris['tanggal'] }}" data-status="{{ $baris['status'] }}">
                        <div class="presensi-day-head"><div><p class="person-name">{{ $baris['hari_label'] }}</p><p class="person-meta">{{ $baris['tanggal_panjang'] }}</p></div><span class="badge {{ $badgeStatus($baris['status']) }}">{{ $baris['status_label'] }}</span></div>
                        <p class="presensi-source">{{ $baris['sumber_label'] }}</p>
                        <dl class="quick-facts">
                            <div><dt>Datang</dt><dd>{{ $baris['jam_masuk'] ?: '-' }}</dd></div>
                            <div><dt>Pulang</dt><dd>{{ $baris['jam_pulang'] ?: '-' }}@if ($baris['belum_pulang'])<p class="person-meta">Belum scan pulang</p>@endif</dd></div>
                            <div><dt>Terlambat</dt><dd class="{{ $baris['menit_terlambat'] > 0 ? 'presensi-late' : '' }}">{{ $baris['menit_terlambat'] > 0 ? $baris['menit_terlambat'].' menit' : '-' }}</dd></div>
                        </dl>
                        @if ($baris['catatan'])<p class="presensi-note">{{ $baris['catatan'] }}</p>@endif
                        @if ($baris['menit_pulang_cepat'] > 0)<p class="person-meta">Pulang cepat {{ $baris['menit_pulang_cepat'] }} menit</p>@endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
