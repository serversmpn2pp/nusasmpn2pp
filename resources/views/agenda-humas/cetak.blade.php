<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $jenisCetak === 'daftar-hadir' ? 'Daftar Hadir' : 'Notulen' }} - {{ $agendaHumas->judul }}</title>
    @include('agenda-humas._cetak-style')
</head>
<body>
    <div class="toolbar"><a href="{{ route('agenda-humas.show', [$agendaHumas, 'tab' => $jenisCetak === 'daftar-hadir' ? 'peserta' : 'notulen']) }}">Kembali ke agenda</a><button type="button" onclick="window.print()">Cetak / Simpan PDF</button><span>A4 portrait</span></div>
    @include('agenda-humas._cetak-lembar')
    @if ($jenisCetak === 'daftar-hadir')
        @include('agenda-humas._cetak-presensi-script')
    @endif
</body>
</html>
