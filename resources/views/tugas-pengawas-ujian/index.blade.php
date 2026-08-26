@extends('layouts.app')

@section('title', 'Tugas Pengawas Saya - NUSA')

@push('styles')
    <style>
        .supervisor-summary { grid-template-columns:repeat(3,minmax(0,1fr)); }
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
        .supervisor-empty { padding:34px 22px; text-align:center; }
        @media (max-width:760px) {
            .supervisor-summary { grid-template-columns:1fr; }
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
            <p class="page-subtitle">Lihat ruang yang Anda awasi, lalu unggah foto daftar hadir dan berita acara setelah ujian selesai.</p>
        </div>
    </div>

    @if (session('berhasil')) <div class="alert">{{ session('berhasil') }}</div> @endif

    <div class="stats-grid supervisor-summary">
        <div class="panel stat"><p class="stat-label">Seluruh tugas</p><p class="stat-value">{{ $ringkasan['jumlah'] }}</p></div>
        <div class="panel stat active"><p class="stat-label">Tugas hari ini</p><p class="stat-value">{{ $ringkasan['hari_ini'] }}</p></div>
        <div class="panel stat warning"><p class="stat-label">Bukti perlu dilengkapi</p><p class="stat-value">{{ $ringkasan['perlu_bukti'] }}</p></div>
    </div>

    <div class="supervisor-list">
        @forelse ($tugas as $penugasan)
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
                    @if ($operasional)
                        <span class="badge {{ $kelasStatus }}">{{ $operasional->labelStatusBukti() }}</span>
                        <a href="{{ route('tugas-pengawas-ujian.show', $operasional) }}" class="button button-primary">Buka tugas</a>
                    @else
                        <span class="badge badge-warning">Paket belum diterbitkan</span>
                        <span class="help-text">Halaman bukti tersedia setelah paket soal siap.</span>
                    @endif
                </div>
            </article>
        @empty
            <section class="panel supervisor-empty">
                <strong>Belum ada tugas pengawas.</strong>
                <p class="help-text" style="margin-top:7px;">Tugas akan muncul otomatis setelah panitia menempatkan Anda sebagai pengawas utama atau pendamping.</p>
            </section>
        @endforelse
    </div>
@endsection
