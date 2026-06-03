@extends('cbt.layout')

@section('title', 'Ujian Selesai - CBT NUSA')

@section('body')
    @php
        $siswa = $peserta->anggotaKelas?->siswa;
        $ujian = $peserta->ujianCbt;
        $kelas = $peserta->kelasUjianCbt?->kelas;
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
        </div>
    </header>

    <main class="cbt-shell">
        <section class="panel panel-pad" style="display: grid; gap: 18px; max-width: 760px; margin: 34px auto 0; text-align: center;">
            <div>
                <span class="badge badge-success">Ujian selesai</span>
                <h1 class="page-title" style="margin-top: 12px;">Terima kasih, {{ $siswa?->nama_lengkap ?: 'peserta' }}.</h1>
                <p class="muted" style="margin: 10px auto 0; max-width: 560px;">Jawaban kamu sudah tersimpan di NUSA. Silakan hubungi pengawas jika ada kendala teknis yang perlu dicatat.</p>
            </div>

            <div class="info-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr)); text-align: left;">
                <div class="info-item">
                    <p class="info-label">Paket ujian</p>
                    <p class="info-value">{{ $ujian->nama }}</p>
                </div>
                <div class="info-item">
                    <p class="info-label">Kelas</p>
                    <p class="info-value">{{ $kelas?->nama ?: '-' }}</p>
                </div>
                <div class="info-item">
                    <p class="info-label">Jawaban</p>
                    <p class="info-value">{{ $jumlahJawaban }} / {{ $jumlahSoal }}</p>
                </div>
            </div>

            <form action="{{ route('cbt.logout') }}" method="POST">
                @csrf
                <button type="submit" class="button button-primary">Kembali ke login CBT</button>
            </form>
        </section>
    </main>
@endsection
