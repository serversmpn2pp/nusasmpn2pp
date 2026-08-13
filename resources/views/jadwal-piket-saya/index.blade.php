@extends('layouts.app')

@section('title', 'Jadwal Piket Saya - NUSA')

@section('content')
    <style>
        .today-picket { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:18px; margin-bottom:18px; border:1px solid #d4aa00; border-left:6px solid var(--accent); border-radius:8px; background:#fff9d9; padding:20px; }
        .today-picket h2 { margin:4px 0 0; color:var(--primary-dark); font-size:1.25rem; }
        .week-picket { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
        .week-day { min-height:132px; border:1px solid var(--line); border-radius:8px; background:#fff; padding:16px; box-shadow:var(--shadow); }
        .week-day.active { border-color:var(--primary); background:var(--primary-soft); }
        .week-day h3 { margin:0; font-size:1rem; }.week-day p { margin:10px 0 0; color:var(--muted); }
        @media(max-width:760px){ .today-picket { grid-template-columns:1fr; } .week-picket { grid-template-columns:1fr 1fr; } }
        @media(max-width:480px){ .week-picket { grid-template-columns:1fr; } }
    </style>

    <div class="page-header"><div><p class="eyebrow">Kehadiran Siswa</p><h1 class="page-title">Jadwal Piket Saya</h1><p class="page-subtitle">Jadwal mingguan pada {{ $tahunPelajaranAktif?->nama ?? 'tahun pelajaran aktif' }}.</p></div></div>

    @if ($dapatMencatatHariIni)
        <section class="today-picket">
            <div><p class="eyebrow">Tugas Hari Ini</p><h2>Anda bertugas sebagai Guru Piket</h2><p class="help-text">Periksa siswa yang belum scan dan catat keterangan Sakit atau Izin bila diperlukan.</p></div>
            <a href="{{ route('piket-kehadiran-siswa.index') }}" class="button button-primary">Buka kehadiran siswa</a>
        </section>
    @else
        <section class="panel panel-pad" style="margin-bottom:18px;">
            <p class="eyebrow">Tugas Hari Ini</p>
            <h2 class="panel-title">Tidak ada jadwal piket hari ini</h2>
            <p class="help-text" style="margin-top:7px;">Halaman pencatatan Sakit/Izin hanya terbuka pada hari Anda bertugas.</p>
        </section>
    @endif

    @unless ($guruMapelAktif)
        <div class="alert alert-danger">Penugasan guru mata pelajaran Anda pada tahun aktif tidak ditemukan. Hubungi administrator jika jadwal piket seharusnya masih berlaku.</div>
    @endunless

    <section class="week-picket">
        @foreach ($daftarHari as $kode => $label)
            @php $jadwal = $jadwalSaya->firstWhere('hari', $kode); @endphp
            <article class="week-day {{ $kodeHariIni === $kode ? 'active' : '' }}">
                <div class="section-heading"><h3>{{ $label }}</h3>@if ($kodeHariIni === $kode)<span class="badge badge-active">Hari ini</span>@endif</div>
                @if ($jadwal)
                    <p><strong>Terjadwal piket</strong></p>
                    @if ($jadwal->keterangan)<p>{{ $jadwal->keterangan }}</p>@endif
                @else
                    <p>Tidak ada jadwal piket.</p>
                @endif
            </article>
        @endforeach
    </section>
@endsection
