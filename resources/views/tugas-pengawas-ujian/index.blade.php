@extends('layouts.app')

@section('title', 'Tugas Pengawas Saya - NUSA')

@push('styles')
    <style>
        .supervisor-summary { grid-template-columns:repeat(4,minmax(0,1fr)); }
        .supervisor-view-tabs { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; width:min(100%,520px); margin-top:20px; }
        .supervisor-view-tabs a { display:flex; align-items:center; justify-content:space-between; gap:10px; min-width:0; min-height:50px; padding:10px 15px; border:1px solid #b9c9d9; border-radius:7px; background:#fff; box-shadow:0 1px 2px rgba(16,47,80,.08); color:var(--primary-dark); font-size:.88rem; font-weight:800; line-height:1.2; text-decoration:none; }
        .supervisor-view-tabs a:hover:not([aria-current="page"]) { border-color:var(--primary); background:var(--primary-soft); }
        .supervisor-view-tabs a[aria-current="page"] { border-color:var(--primary); background:var(--primary); color:#fff; box-shadow:0 2px 5px rgba(16,47,80,.16); }
        .supervisor-view-tabs a:focus-visible { outline:3px solid var(--accent); outline-offset:2px; }
        .supervisor-view-tabs a > span:first-child { min-width:0; overflow-wrap:anywhere; }
        .supervisor-view-tabs .supervisor-view-count { display:grid; flex:0 0 auto; min-width:27px; height:27px; place-items:center; border-radius:5px; background:var(--primary-soft); color:var(--primary-dark); font-size:.75rem; font-variant-numeric:tabular-nums; }
        .supervisor-view-tabs a[aria-current="page"] .supervisor-view-count { background:rgba(255,255,255,.22); color:#fff; }
        .supervisor-section-head { display:flex; align-items:end; justify-content:space-between; gap:16px; margin:24px 0 10px; padding-bottom:9px; border-bottom:1px solid var(--line); }
        .supervisor-section-head h2 { margin:0; color:var(--primary-dark); font-size:1.05rem; }
        .supervisor-section-head p { margin:3px 0 0; color:var(--muted); font-size:.78rem; }
        .supervisor-list { display:grid; gap:14px; margin-top:18px; }
        .supervisor-task { display:grid; grid-template-columns:84px minmax(0,1fr) auto; gap:18px; align-items:center; padding:18px 20px; }
        .supervisor-date { display:grid; place-items:center; min-height:72px; border:1px solid #bfd4e8; border-radius:7px; background:var(--primary-soft); color:var(--primary-dark); text-align:center; }
        .supervisor-date strong,.supervisor-date span { display:block; }
        .supervisor-date strong { font-size:1.65rem; line-height:1; }
        .supervisor-date span { margin-top:5px; font-size:.74rem; font-weight:800; text-transform:uppercase; }
        .supervisor-task-main { min-width:0; }
        .supervisor-task-title { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .supervisor-task-title h2 { margin:0; font-size:1.05rem; }
        .supervisor-task-meta { display:flex; flex-wrap:wrap; gap:7px 14px; margin:8px 0 0; color:var(--muted); font-size:.82rem; font-weight:650; }
        .supervisor-task-note { margin:8px 0 0; color:var(--primary-dark); font-size:.8rem; }
        .supervisor-task-action { display:grid; justify-items:end; gap:9px; min-width:150px; }
        .supervisor-task.is-retake { border-left:5px solid #d9a900; }
        .supervisor-task.is-retake .supervisor-date { border-color:#ead26b; background:#fff9dc; color:#654d00; }
        .supervisor-empty { padding:34px 22px; text-align:center; }
        .supervisor-empty a { display:inline-block; margin-top:12px; }
        @media (max-width:760px) {
            .supervisor-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .supervisor-view-tabs a { padding:9px 11px; }
            .supervisor-task { grid-template-columns:66px minmax(0,1fr); gap:13px; padding:15px; }
            .supervisor-date { min-height:66px; }
            .supervisor-task-action { grid-column:1 / -1; width:100%; justify-items:stretch; }
            .supervisor-task-action .button { width:100%; text-align:center; }
        }
    </style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Ujian & Asesmen</p>
            <h1 class="page-title">Tugas Pengawas Saya</h1>
        </div>
    </div>

    @if (session('berhasil')) <div class="alert">{{ session('berhasil') }}</div> @endif

    <div class="stats-grid supervisor-summary">
        <div class="panel stat"><p class="stat-label">Perlu dikerjakan</p><p class="stat-value">{{ $ringkasan['perlu'] }}</p></div>
        <div class="panel stat active"><p class="stat-label">Perlu hari ini</p><p class="stat-value">{{ $ringkasan['hari_ini'] }}</p></div>
        <div class="panel stat warning"><p class="stat-label">Bukti belum selesai</p><p class="stat-value">{{ $ringkasan['perlu_bukti'] }}</p></div>
        <div class="panel stat"><p class="stat-label">Riwayat</p><p class="stat-value">{{ $ringkasan['riwayat'] }}</p></div>
    </div>

    <nav class="supervisor-view-tabs" aria-label="Tampilan tugas pengawas">
        <a href="{{ route('tugas-pengawas-ujian.index') }}" @if($tabTugas === 'perlu') aria-current="page" @endif><span>Perlu dikerjakan</span><span class="supervisor-view-count">{{ $ringkasan['perlu'] }}</span></a>
        <a href="{{ route('tugas-pengawas-ujian.index', ['tab' => 'riwayat']) }}" @if($tabTugas === 'riwayat') aria-current="page" @endif><span>Riwayat tugas</span><span class="supervisor-view-count">{{ $ringkasan['riwayat'] }}</span></a>
    </nav>

    @if ($tugasSusulan->isEmpty() && $tugas->isEmpty())
        <section class="panel supervisor-empty" style="margin-top:18px;">
            <strong>{{ $tabTugas === 'riwayat' ? 'Belum ada riwayat tugas.' : 'Tidak ada tugas yang perlu dikerjakan.' }}</strong>
            @if($tabTugas === 'perlu' && $ringkasan['riwayat'] > 0)
                <a href="{{ route('tugas-pengawas-ujian.index', ['tab' => 'riwayat']) }}" class="button button-muted">Lihat riwayat tugas</a>
            @elseif($tabTugas === 'perlu')
                <p class="help-text" style="margin-top:7px;">Tugas muncul setelah panitia menempatkan Anda sebagai pengawas.</p>
            @endif
        </section>
    @else
        @if ($tugasSusulan->isNotEmpty())
            <div class="supervisor-section-head"><div><h2>{{ $tabTugas === 'riwayat' ? 'Riwayat ujian susulan' : 'Tugas ujian susulan' }}</h2><p>Jadwal khusus bagi siswa yang tidak mengikuti ujian utama.</p></div><span class="badge badge-warning">{{ $tugasSusulan->count() }} tugas</span></div>
            <div class="supervisor-list" style="margin-top:0;">
                @foreach ($tugasSusulan as $susulan)
                    <article class="panel supervisor-task is-retake">
                        <div class="supervisor-date">
                            <strong>{{ $susulan['mulai']?->format('d') ?: '-' }}</strong>
                            <span>{{ $susulan['mulai']?->locale('id')->translatedFormat('M Y') ?: 'Belum ada' }}</span>
                        </div>
                        <div class="supervisor-task-main">
                            <div class="supervisor-task-title">
                                @if($susulan['mulai']?->isToday())<span class="badge badge-active">Hari ini</span>@endif
                                <span class="badge badge-warning">Susulan</span>
                                <h2>{{ $susulan['ujian']?->mataPelajaran?->nama ?: 'Mata pelajaran belum ditentukan' }} · Tingkat {{ $susulan['ujian']?->tingkat ?: '-' }}</h2>
                            </div>
                            <p class="supervisor-task-meta">
                                <span>{{ $susulan['mulai']?->locale('id')->translatedFormat('l, d F Y') ?: '-' }}</span>
                                <span>{{ $susulan['mulai']?->format('H:i') ?: '-' }}-{{ $susulan['selesai']?->format('H:i') ?: '-' }}</span>
                                <span>{{ $susulan['ruang'] ?: 'Ruang belum ditentukan' }}</span>
                                <span>{{ $susulan['jumlah'] }} peserta</span>
                            </p>
                            <p class="supervisor-task-note">{{ $susulan['jadwal']?->kegiatanUjianCbt?->nama ?: 'Ujian Terpusat' }} · {{ $susulan['jumlah_selesai'] }}/{{ $susulan['jumlah'] }} siswa selesai</p>
                        </div>
                        <div class="supervisor-task-action">
                            <span class="badge {{ $susulan['kelas_status'] }}">{{ $susulan['label_status'] }}</span>
                            <a href="{{ route('tugas-pengawas-ujian.susulan.show', $susulan['kode']) }}" class="button {{ $tabTugas === 'riwayat' ? 'button-muted' : 'button-primary' }}">{{ $tabTugas === 'riwayat' ? 'Lihat tugas susulan' : 'Buka tugas susulan' }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        @if ($tugas->isNotEmpty())
            <div class="supervisor-section-head"><div><h2>{{ $tabTugas === 'riwayat' ? 'Riwayat ujian reguler' : 'Tugas ujian reguler' }}</h2><p>Pengawasan ruang pada jadwal ujian utama.</p></div><span class="badge badge-muted">{{ $tugas->count() }} tugas</span></div>
            <div class="supervisor-list" style="margin-top:0;">
        @foreach ($tugas as $penugasan)
            @php
                $jadwal = $penugasan->jadwalUjianCbt;
                $ruang = $penugasan->ruangKegiatanUjianCbt;
                $operasional = $penugasan->ruangOperasional;
                $utama = (int) auth()->user()?->pegawai_id === (int) $penugasan->pengawas_utama_pegawai_id;
                $kelasStatus = match($operasional?->status_bukti) {
                    'valid' => 'badge-active',
                    'menunggu_pemeriksaan', 'siap_dikirim' => 'badge-warning',
                    'perlu_diulang' => 'badge-danger',
                    default => 'badge-muted',
                };
            @endphp
            <article class="panel supervisor-task">
                <div class="supervisor-date">
                    <strong>{{ $jadwal?->tanggal?->format('d') ?: '-' }}</strong>
                    <span>{{ $jadwal?->tanggal?->locale('id')->translatedFormat('M Y') ?: 'Belum ada' }}</span>
                </div>
                <div class="supervisor-task-main">
                    <div class="supervisor-task-title">
                        @if($jadwal?->tanggal?->isToday())<span class="badge badge-active">Hari ini</span>@endif
                        <h2>{{ $jadwal?->mataPelajaran?->nama ?: 'Mata pelajaran belum ditentukan' }} · Tingkat {{ $jadwal?->tingkat ?: '-' }}</h2>
                        <span class="badge {{ $utama ? 'badge-active' : 'badge-muted' }}">{{ $utama ? 'Pengawas utama' : 'Pendamping' }}</span>
                    </div>
                    <p class="supervisor-task-meta">
                        <span>{{ $jadwal?->tanggal?->locale('id')->translatedFormat('l, d F Y') ?: '-' }}</span>
                        <span>{{ $jadwal?->labelWaktu() ?: '-' }}</span>
                        <span>{{ $ruang?->kode }} - {{ $ruang?->nama }}</span>
                        <span>{{ $operasional?->peserta_ujian_cbt_count ?? 0 }} peserta</span>
                    </p>
                    <p class="supervisor-task-note">{{ $jadwal?->kegiatanUjianCbt?->nama ?: 'Ujian Terpusat' }}{{ $penugasan->catatan ? ' · '.$penugasan->catatan : '' }}</p>
                </div>
                <div class="supervisor-task-action">
                    @if ($jadwal?->status === 'dibatalkan')
                        <span class="badge badge-muted">Dibatalkan</span>
                    @elseif ($operasional)
                        <span class="badge {{ $kelasStatus }}">{{ $operasional->labelStatusBukti() }}</span>
                        <a href="{{ route('tugas-pengawas-ujian.show', $operasional) }}" class="button {{ $tabTugas === 'riwayat' ? 'button-muted' : 'button-primary' }}">{{ $tabTugas === 'riwayat' ? 'Lihat tugas' : 'Buka tugas' }}</a>
                    @else
                        <span class="badge badge-warning">Paket belum diterbitkan</span>
                        <span class="help-text">Ruang pengawas tersedia setelah paket soal diterbitkan.</span>
                    @endif
                </div>
            </article>
        @endforeach
            </div>
        @endif
    @endif
@endsection
