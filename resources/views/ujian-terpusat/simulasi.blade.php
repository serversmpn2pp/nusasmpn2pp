@extends('layouts.app')
@section('title', 'Simulasi CBT - NUSA')
@section('content')
    <div class="page-header">
        <div><p class="eyebrow">Ujian & Asesmen</p><h1 class="page-title">Simulasi CBT</h1><p class="page-subtitle">12 soal umum · 6 jenis soal · 20 menit · Tidak masuk nilai akademik</p></div>
        <div class="actions"><a class="button button-muted" href="{{ route('pusat-cbt.index') }}">Kembali</a><a class="button button-primary" href="{{ route('ujian-terpusat.create', ['simulasi' => 1]) }}">Buat kegiatan simulasi</a></div>
    </div>
    <p class="alert">Gunakan kegiatan simulasi terpisah. Setelah sesi, ruang, peserta, dan jadwal disiapkan, buka Tahap 8 lalu pilih Gunakan paket Simulasi CBT. Isi soal sama untuk setiap tingkat dan tidak menguji materi mapel tertentu.</p>
    <div class="section-stack" data-inline-math>
        @foreach ($contoh as $index => $soal)
            <section class="panel panel-pad">
                <div class="section-heading"><h2 class="panel-title">Soal {{ $index + 1 }}</h2><span class="badge badge-muted">{{ \App\Models\SoalCbt::DAFTAR_JENIS[$soal['jenis_soal']] }}</span></div>
                @if (!empty($soal['stimulus']))<p style="margin-top:12px;">{{ $soal['stimulus'] }}</p>@endif
                @if ($index === 1)<figure style="margin:12px 0;"><img src="{{ asset('images/login-sekolah.jpg') }}" alt="Lingkungan sekolah" style="max-width:100%;width:420px;border-radius:6px;"><figcaption>Lingkungan sekolah yang perlu dijaga bersama.</figcaption></figure>@endif
                <x-media-soal :media="isset($soal['media']['tabel']) ? $soal['media'] : []" />
                <p style="margin:12px 0;font-weight:600;">{{ $soal['pertanyaan'] }}</p>
                @foreach ($soal['opsi']['pilihan'] ?? [] as $kode => $teks)<p>{{ $kode }}. {{ $teks }}</p>@endforeach
                @foreach ($soal['opsi']['pernyataan'] ?? [] as $butir)<p>{{ $butir['nomor'] }}. {{ $butir['teks'] }} <span class="help-text">Benar / Salah</span></p>@endforeach
                @if (isset($soal['opsi']['pasangan']))
                    <div class="form-grid"><div><strong>Pernyataan</strong>@foreach ($soal['opsi']['pasangan'] as $butir)<p>{{ $butir['nomor'] }}. {{ $butir['kiri'] }}</p>@endforeach</div>
                    <div><strong>Pilihan pasangan</strong>@foreach (array_merge(array_column($soal['opsi']['pasangan'], 'kanan'), $soal['opsi']['pengecoh']) as $teks)<p>{{ $teks }}</p>@endforeach</div></div>
                @endif
                @if (in_array($soal['jenis_soal'], ['isian_singkat', 'numerik']))<p class="help-text">Jawaban diketik siswa pada kolom jawaban.</p>@endif
            </section>
        @endforeach
    </div>
@endsection
