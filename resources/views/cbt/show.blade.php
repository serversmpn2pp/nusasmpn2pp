@extends('cbt.layout')

@section('title', 'Konfirmasi Ujian - CBT NUSA')

@section('body')
    @php
        $siswa = $peserta->anggotaKelas?->siswa;
        $ujian = $peserta->ujianCbt;
        $sesi = $peserta->sesiUjianCbt;
        $kelas = $peserta->kelasUjianCbt?->kelas;
        $labelTombol = $peserta->status === 'sedang_mengerjakan' ? 'Lanjutkan ujian' : 'Mulai ujian';
    @endphp

    <header class="cbt-topbar">
        <div class="topbar-inner">
            <div class="brand">
                <span class="brand-mark"><img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA"></span>
                <span>
                    <span class="brand-title">CBT NUSA</span>
                    <span class="brand-subtitle">SMP Negeri 2 Padang Panjang</span>
                </span>
            </div>
            <form action="{{ route('cbt.logout') }}" method="POST">
                @csrf
                <button type="submit" class="button button-muted">
                    Kembali ke Ujian Saya
                </button>
            </form>
        </div>
    </header>

    <main class="cbt-shell">
        <div class="page-header">
            <div>
                <p class="eyebrow">Konfirmasi peserta</p>
                <h1 class="page-title">{{ $ujian->nama }}</h1>
            </div>
            <span class="badge {{ $peserta->status === 'sedang_mengerjakan' ? 'badge-warning' : 'badge-success' }}">{{ $peserta->labelStatus() }}</span>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="panel panel-pad" style="display: grid; gap: 18px;">
            <section class="info-grid">
                <div class="info-item">
                    <p class="info-label">Nama peserta</p>
                    <p class="info-value">{{ $siswa?->nama_lengkap ?: '-' }}</p>
                </div>
                <div class="info-item">
                    <p class="info-label">Kelas</p>
                    <p class="info-value">{{ $kelas?->nama ?: '-' }}</p>
                </div>
                <div class="info-item">
                    <p class="info-label">NISN</p>
                    <p class="info-value">{{ $siswa?->nisn ?: '-' }}</p>
                </div>
                <div class="info-item">
                    <p class="info-label">Mata pelajaran</p>
                    <p class="info-value">{{ $ujian->mataPelajaran?->nama ?: '-' }}</p>
                </div>
                <div class="info-item">
                    <p class="info-label">Jenis ujian</p>
                    <p class="info-value">{{ $ujian->jenisUjianCbt?->nama ?: '-' }}</p>
                </div>
                <div class="info-item">
                    <p class="info-label">Sesi</p>
                    <p class="info-value">{{ $sesi?->nama ?: 'Mengikuti jadwal paket' }}</p>
                </div>
                <div class="info-item">
                    <p class="info-label">Durasi</p>
                    <p class="info-value">{{ $ujian->durasi_menit }} menit</p>
                </div>
                <div class="info-item">
                    <p class="info-label">Soal</p>
                    <p class="info-value">{{ $jumlahSoal }} soal</p>
                </div>
            </section>

            <section class="panel" style="box-shadow: none; padding: 16px; background: #fbfdff;">
                <h2 class="panel-title">Petunjuk ujian</h2>
                <p class="muted" style="margin: 10px 0 0; white-space: pre-line;">{{ filled($ujian->petunjuk) ? $ujian->petunjuk : 'Baca setiap soal dengan teliti, kerjakan secara jujur, dan simpan jawaban sebelum selesai.' }}</p>
            </section>

            <div class="actions" style="justify-content: space-between;">
                <div class="muted" style="font-size: .9rem; font-weight: 800;">
                    Jawaban tersimpan: {{ $jumlahJawaban }} dari {{ $jumlahSoal }} soal
                </div>
                <form action="{{ route('cbt.ujian.mulai') }}" method="POST" onsubmit="return confirm('Mulai atau lanjutkan ujian sekarang?')">
                    @csrf
                    <button type="submit" class="button button-primary">{{ $labelTombol }}</button>
                </form>
            </div>
        </div>
    </main>
@endsection
