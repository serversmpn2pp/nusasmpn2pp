@extends('layouts.app')

@section('title', 'Presensi Ujian CBT - NUSA')

@section('content')
    <style>
        .exam-attendance-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin-bottom:24px; }
        .exam-room-list { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        .exam-room-card { display:grid; gap:16px; }
        .exam-room-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
        .exam-room-date { margin:0 0 5px; color:var(--primary); font-size:.78rem; font-weight:800; }
        .exam-room-title { margin:0; font-size:1.08rem; }
        .exam-room-subtitle { margin:5px 0 0; color:var(--muted); font-size:.84rem; }
        .exam-room-progress { overflow:hidden; height:8px; border-radius:999px; background:#e8eef4; }
        .exam-room-progress span { display:block; height:100%; background:var(--primary); }
        .exam-section-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin:26px 0 12px; }
        .exam-section-head h2 { margin:0; font-size:1.02rem; }
        @media(max-width:900px) { .exam-room-list { grid-template-columns:1fr; } }
        @media(max-width:620px) { .exam-attendance-summary { grid-template-columns:1fr; }.exam-room-head { display:grid; }.exam-room-card .button { width:100%; } }
    </style>

    @php
        $semuaRuang = $ruangHariIni->concat($ruangLain);
        $jumlahPeserta = $semuaRuang->sum('jumlah_peserta');
        $jumlahHadir = $semuaRuang->sum('jumlah_hadir');
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Ujian & Asesmen</p>
            <h1 class="page-title">Presensi ujian CBT</h1>
            <p class="page-subtitle">Pilih ruang, lalu pindai QR pada kartu pelajar peserta.</p>
        </div>
        @if ($dapatKelolaSemua)
            <a href="{{ route('ujian-cbt.index') }}" class="button button-muted">Kelola paket CBT</a>
        @endif
    </div>

    <div class="exam-attendance-summary">
        <div class="panel stat"><p class="stat-label">Ruang ditampilkan</p><p class="stat-value">{{ $semuaRuang->count() }}</p></div>
        <div class="panel stat"><p class="stat-label">Peserta</p><p class="stat-value">{{ $jumlahPeserta }}</p></div>
        <div class="panel stat active"><p class="stat-label">Sudah tercatat hadir</p><p class="stat-value">{{ $jumlahHadir }}</p></div>
    </div>

    @foreach ([['judul' => 'Ruang ujian hari ini', 'data' => $ruangHariIni, 'kosong' => 'Belum ada ruang ujian terjadwal hari ini.'], ['judul' => 'Jadwal ruang lainnya', 'data' => $ruangLain, 'kosong' => 'Belum ada ruang ujian lainnya.']] as $bagian)
        <div class="exam-section-head">
            <h2>{{ $bagian['judul'] }}</h2>
            <span class="badge badge-muted">{{ $bagian['data']->count() }} ruang</span>
        </div>

        @if ($bagian['data']->isNotEmpty())
            <section class="exam-room-list">
                @foreach ($bagian['data'] as $ruang)
                    @php
                        $jadwal = $ruang->jadwalUjianCbt;
                        $persentase = $ruang->jumlah_peserta > 0 ? round(($ruang->jumlah_hadir / $ruang->jumlah_peserta) * 100) : 0;
                        $mapel = $jadwal?->mataPelajaran?->nama ?: $ruang->ujianCbt?->mataPelajaran?->nama;
                    @endphp
                    <article class="panel panel-pad exam-room-card">
                        <div class="exam-room-head">
                            <div>
                                <p class="exam-room-date">{{ $jadwal?->tanggal?->locale('id')->translatedFormat('l, d F Y') ?: 'Jadwal belum ditentukan' }}</p>
                                <h3 class="exam-room-title">{{ $ruang->kode }} - {{ $ruang->nama }}</h3>
                                <p class="exam-room-subtitle">{{ $mapel ?: $ruang->ujianCbt?->nama }}{{ $jadwal ? ' · '.$jadwal->labelWaktu() : '' }}</p>
                            </div>
                            <span class="badge {{ $ruang->jumlah_belum_absen ? 'badge-warning' : 'badge-active' }}">{{ $ruang->jumlah_hadir }}/{{ $ruang->jumlah_peserta }} hadir</span>
                        </div>

                        <div class="exam-room-progress" aria-label="{{ $persentase }} persen peserta hadir"><span style="width:{{ $persentase }}%"></span></div>

                        <dl class="quick-facts">
                            <div><dt>Belum tercatat</dt><dd>{{ $ruang->jumlah_belum_absen }}</dd></div>
                            <div><dt>Tidak hadir</dt><dd>{{ $ruang->jumlah_tidak_hadir }}</dd></div>
                            <div><dt>Pengawas utama</dt><dd>{{ $ruang->pengawasUtama?->nama_lengkap ?: '-' }}</dd></div>
                            <div><dt>Lokasi</dt><dd>{{ $ruang->lokasi ?: '-' }}</dd></div>
                        </dl>

                        <a href="{{ route('presensi-ujian-cbt.show', [$ruang->ujianCbt, $ruang]) }}" class="button button-primary">Buka presensi ruang</a>
                    </article>
                @endforeach
            </section>
        @else
            <div class="panel empty-state">{{ $bagian['kosong'] }}</div>
        @endif
    @endforeach
@endsection
