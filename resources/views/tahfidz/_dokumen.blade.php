<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <link rel="stylesheet" href="{{ asset('css/tahfidz-cetak.css') }}?v={{ filemtime(public_path('css/tahfidz-cetak.css')) }}">
    <style>@page { size: @yield('paper', 'A4 portrait'); margin: 0; }</style>
    <script src="{{ asset('vendor/lucide/lucide.min.js') }}" defer></script>
</head>
<body>
    <nav class="tf-print-toolbar" aria-label="Tindakan dokumen">
        <a href="{{ $kembali }}"><i data-lucide="arrow-left" aria-hidden="true"></i> Kembali</a>
        <button type="button" onclick="window.print()"><i data-lucide="printer" aria-hidden="true"></i> Cetak / Simpan PDF</button>
        @yield('toolbar')
    </nav>
    @yield('document')
    <script>document.addEventListener('DOMContentLoaded', () => window.lucide?.createIcons());</script>
</body>
</html>
