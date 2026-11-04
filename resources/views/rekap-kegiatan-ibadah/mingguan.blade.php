@extends('layouts.app')
@section('admin-module', 'kehadiran')
@section('title', 'Evaluasi Ibadah Mingguan - NUSA')

@section('content')
    <div class="ibadah-mingguan">
        <div class="page-header">
            <div><p class="eyebrow">Kehadiran / Ibadah Siswa</p><h1 class="page-title">Evaluasi Ibadah Mingguan</h1></div>
            <div class="actions">
                <a href="{{ route('rekap-kegiatan-ibadah.index', $parameter) }}" class="button button-muted"><i data-lucide="calendar-days" aria-hidden="true"></i> Rekap harian</a>
                @if ($jumlahHari > 0)
                    <a href="{{ route('rekap-kegiatan-ibadah.mingguan.cetak', $parameter) }}" class="button button-primary" target="_blank" rel="noopener"><i data-lucide="printer" aria-hidden="true"></i> Cetak evaluasi</a>
                @endif
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="GET" action="{{ route('rekap-kegiatan-ibadah.mingguan') }}" class="feature-filter iw-filter">
            <div class="field"><label for="iw-tanggal">Tanggal dalam minggu</label><input class="input" id="iw-tanggal" name="tanggal" type="date" max="{{ now()->toDateString() }}" value="{{ $tanggal }}"></div>
            <div class="field"><label for="iw-kegiatan">Kegiatan ibadah</label><select class="select" id="iw-kegiatan" name="kegiatan_ibadah_id">
                @foreach ($daftarKegiatan as $item)<option value="{{ $item->id }}" @selected($item->id === $kegiatan?->id)>{{ $item->nama }}</option>@endforeach
            </select></div>
            <div class="field"><label for="iw-kelas">Kelas</label><select class="select" id="iw-kelas" name="kelas_id">
                <option value="">Semua kelas dalam cakupan</option>
                @foreach ($daftarKelas as $item)<option value="{{ $item->id }}" @selected($item->id === $kelas?->id)>{{ $item->nama }}</option>@endforeach
            </select></div>
            <button type="submit" class="button button-muted">Tampilkan</button>
        </form>

        <dl class="iw-metrics">
            <div><dt>Hari dievaluasi</dt><dd>{{ $jumlahHari }}</dd></div>
            <div><dt>Siswa perlu konfirmasi</dt><dd>{{ $jumlahSiswa }}</dd></div>
            <div><dt>Total belum tercatat</dt><dd>{{ $jumlahKejadian }}</dd></div>
        </dl>

        <section class="panel iw-report" aria-labelledby="iw-judul">
            <header class="iw-band">
                <div><h2 id="iw-judul">Hadir di sekolah, ibadah belum tercatat</h2><p>{{ $kegiatan?->nama ?? 'Kegiatan belum tersedia' }} &middot; {{ $periode }} &middot; {{ $kelas?->nama ?? 'Semua kelas dalam cakupan' }}</p></div>
                <nav class="actions" aria-label="Navigasi minggu">
                    <a href="{{ route('rekap-kegiatan-ibadah.mingguan', [...$parameter, 'tanggal' => $awal->subWeek()->toDateString()]) }}" class="button button-muted button-sm" aria-label="Minggu sebelumnya" title="Minggu sebelumnya"><i data-lucide="chevron-left" aria-hidden="true"></i></a>
                    @if ($akhir->toDateString() < now()->toDateString())
                        <a href="{{ route('rekap-kegiatan-ibadah.mingguan', [...$parameter, 'tanggal' => $awal->addWeek()->toDateString()]) }}" class="button button-muted button-sm" aria-label="Minggu berikutnya" title="Minggu berikutnya"><i data-lucide="chevron-right" aria-hidden="true"></i></a>
                    @endif
                </nav>
            </header>
            <div class="iw-notes">@include('rekap-kegiatan-ibadah._mingguan-catatan')</div>
            @include('rekap-kegiatan-ibadah._mingguan-tabel')
        </section>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ibadah-mingguan.css') }}?v={{ filemtime(public_path('css/ibadah-mingguan.css')) }}">
@endpush
