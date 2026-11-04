<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Evaluasi Ibadah Mingguan - NUSA</title>
    <link rel="stylesheet" href="{{ asset('css/ibadah-mingguan.css') }}?v={{ filemtime(public_path('css/ibadah-mingguan.css')) }}">
</head>
<body class="iw-print">
    <div class="iw-toolbar">
        <a href="{{ route('rekap-kegiatan-ibadah.mingguan', $parameter) }}">Kembali ke evaluasi</a>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>
    <main class="iw-sheet">
        <header class="iw-kop">
            <img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA" width="46" height="46">
            <div><p>SMP Negeri 2 Padang Panjang</p><h1>Evaluasi Ibadah Mingguan</h1></div>
        </header>
        <div class="iw-print-meta">
            <strong>{{ $kegiatan?->nama ?? 'Kegiatan belum tersedia' }}</strong>
            <p>{{ $periode }} &middot; {{ $tahun?->nama ?? 'Tahun pelajaran belum tersedia' }} &middot; {{ $kelas?->nama ?? 'Semua kelas dalam cakupan' }}</p>
            <p>Dokumen internal &middot; {{ $jumlahHari }} hari dievaluasi &middot; {{ $jumlahSiswa }} siswa perlu konfirmasi &middot; {{ $jumlahKejadian }} kali belum tercatat</p>
        </div>
        <div class="iw-notes">@include('rekap-kegiatan-ibadah._mingguan-catatan')</div>
        @include('rekap-kegiatan-ibadah._mingguan-tabel')
        <p class="iw-print-footer">Dicetak {{ now()->format('d/m/Y H:i') }} WIB</p>
    </main>
</body>
</html>
