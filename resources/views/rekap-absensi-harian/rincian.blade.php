@extends('layouts.app')
@section('admin-module', 'kehadiran')

@section('title', 'Rincian Presensi Harian - NUSA')

@section('content')
    @php
        $statusLabel = [
            'hadir' => 'Hadir', 'sakit' => 'Sakit', 'izin' => 'Izin', 'alfa' => 'Alfa',
            'belum_scan' => 'Belum dikonfirmasi', 'pengecualian' => 'Pengecualian presensi',
        ];
        $badge = match ($status) {
            'hadir' => 'badge-active',
            'izin', 'sakit' => 'badge-warning',
            'alfa' => 'badge-danger',
            default => 'badge-muted',
        };
        $tanggalLabel = \Carbon\Carbon::parse($tanggal)->locale('id')->translatedFormat('l, d F Y');
    @endphp

    <style>
        .daily-attendance-facts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 205px), 1fr));
            gap: 12px;
            margin: 16px 0 0;
        }
        .daily-attendance-facts > div {
            padding: 12px 16px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: var(--ui-blue-soft);
        }
        .daily-attendance-facts > div:nth-child(2) { background: var(--ui-teal-soft); }
        .daily-attendance-facts dt { color: var(--ui-muted); font-size: 13px; }
        .daily-attendance-facts dd { margin: 4px 0 0; overflow-wrap: anywhere; }
        .daily-attendance-facts strong { font-variant-numeric: tabular-nums; }
        .daily-attendance-history { list-style: none; padding: 0; margin: 0; }
        .daily-attendance-history li { padding: 12px 0 12px 14px; border-inline-start: 2px solid var(--feature-accent); }
        .daily-attendance-history li + li { border-top: 1px solid var(--line); }
        .daily-attendance-note { margin: 6px 0 0; white-space: pre-line; overflow-wrap: anywhere; }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Presensi siswa</p>
            <h1 class="page-title">Rincian presensi harian</h1>
        </div>
        <div class="actions">
            <a href="{{ route('rekap-absensi-harian.index', ['tanggal' => $tanggal, 'kelas_id' => $anggotaKelas->kelas_id]) }}" class="button button-muted">
                <i data-lucide="arrow-left" aria-hidden="true"></i> Kembali ke rekap
            </a>
            @if ($akses['dapat'])
                <a href="{{ route('rekap-absensi-harian.koreksi.edit', ['anggotaKelas' => $anggotaKelas, 'tanggal' => $tanggal]) }}" class="button button-primary">
                    <i data-lucide="square-pen" aria-hidden="true"></i> {{ $absensi?->menungguVerifikasiScan() ? 'Verifikasi scan' : 'Koreksi' }}
                </a>
            @endif
        </div>
    </div>

    <div class="section-stack">
        <section class="panel panel-pad feature-section" aria-labelledby="daily-attendance-student">
            <h2 class="panel-title" id="daily-attendance-student">{{ $anggotaKelas->siswa?->nama_lengkap ?? '-' }}</h2>
            <p class="person-meta">{{ $anggotaKelas->kelas?->nama }} &middot; {{ $tanggalLabel }}</p>
            <span class="badge {{ $badge }}">{{ $statusLabel[$status] ?? ucfirst($status) }}</span>
            @if ($absensi?->menungguVerifikasiScan())
                <span class="badge badge-warning">Perlu verifikasi</span>
            @endif
            <dl class="daily-attendance-facts">
                @foreach (['masuk' => 'Masuk', 'pulang' => 'Pulang'] as $jenis => $label)
                    @php
                        $jam = $absensi?->{'jam_'.$jenis};
                        $sumber = $sumberJam['sumber_'.$jenis.'_label'];
                    @endphp
                    <div>
                        <dt>{{ $label }}</dt>
                        <dd><strong>{{ $jam ? substr($jam, 0, 5) : '-' }}</strong>@if ($sumber) &middot; {{ $sumber }}@endif</dd>
                    </div>
                @endforeach
                <div><dt>Keterlambatan</dt><dd>{{ $absensi?->menit_terlambat ? $absensi->menit_terlambat.' menit' : '-' }}</dd></div>
                <div><dt>Pulang cepat</dt><dd>{{ $absensi?->menit_pulang_cepat ? $absensi->menit_pulang_cepat.' menit' : '-' }}</dd></div>
            </dl>
            @php($catatan = $absensi?->catatanKehadiran())
            @if ($catatan && ! $riwayat->contains('catatan', $catatan))
                <p class="daily-attendance-note">{{ $catatan }}</p>
            @endif
        </section>

        <section class="panel panel-pad feature-section" aria-labelledby="daily-attendance-history-title">
            <h2 class="panel-title" id="daily-attendance-history-title">Riwayat perubahan</h2>
            @if ($riwayat->isEmpty())
                <p class="empty-state">Belum ada riwayat koreksi pada tanggal ini.</p>
            @else
                <ol class="daily-attendance-history">
                    @foreach ($riwayat as $perubahan)
                        <li>
                            <p class="person-name">{{ $perubahan['sumber_label'] }}</p>
                            <p class="person-meta">
                                {{ $perubahan['dibuat_oleh'] ?? 'Akun tidak tercatat' }}
                                @if ($perubahan['dibuat_pada'])
                                    &middot; <time datetime="{{ $perubahan['dibuat_pada'] }}">{{ $perubahan['dibuat_pada_label'] }}</time>
                                @endif
                            </p>
                            <p class="person-meta">{{ $statusLabel[$perubahan['status_sebelum']] ?? 'Belum tercatat' }} &rarr; {{ $statusLabel[$perubahan['status_sesudah']] ?? ucfirst($perubahan['status_sesudah']) }}</p>
                            @if ($perubahan['catatan'])
                                <p class="daily-attendance-note">{{ $perubahan['catatan'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
@endsection
