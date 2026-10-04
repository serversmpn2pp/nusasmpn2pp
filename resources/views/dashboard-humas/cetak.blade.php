<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ringkasan Kinerja Humas - NUSA</title>
@include('program-kerja-humas._cetak-style')
<style>.hd-number { text-align:right; } .print-summary th:first-child { width:34%; } .print-summary th:nth-child(2) { width:13%; } small { font-size:9pt; } .hd-month-table th,.hd-month-table td { font-size:9pt; padding:2mm; }</style>
</head><body>
<div class="print-toolbar"><button type="button" onclick="window.print()">Cetak ringkasan</button><a href="{{ route('dashboard-humas.index', $filter) }}">Kembali</a></div>
<main class="print-page">
    <header class="print-header"><img src="{{ asset('images/logo-padang-panjang.png') }}" alt="Logo Padang Panjang"><div><strong>SMP NEGERI 2 PADANG PANJANG</strong><h1>RINGKASAN KINERJA HUMAS</h1></div><img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo sekolah"></header>
    <p><strong>{{ $labelPeriode }}</strong><br>{{ $mulai->format('d-m-Y') }} s.d. {{ $selesai->format('d-m-Y') }}</p>
    <p><small>Dicetak {{ now()->locale('id')->translatedFormat('d F Y H:i') }} WIB &middot; {{ auth()->user()->nama }}</small></p>
    @if(!$metrik)<p>Belum ada ringkasan yang dapat ditampilkan untuk hak akses akun ini.</p>@else
    <h2>Capaian periode</h2><table class="print-summary"><thead><tr><th>Indikator</th><th class="hd-number">Jumlah</th><th>Dasar perhitungan</th></tr></thead><tbody>
        @foreach($metrik as $item)<tr><td>{{ $item['label'] }}</td><td class="hd-number">{{ $item['jumlah'] }}</td><td>{{ $item['dasar'] }}</td></tr>@endforeach
    </tbody></table>
    @foreach($distribusi as $bagian)<h2>{{ $bagian['judul'] }}</h2><table><thead><tr>@foreach($bagian['label'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody><tr>@foreach($bagian['jumlah'] as $jumlah)<td>{{ $jumlah }}</td>@endforeach</tr></tbody></table>@endforeach
    @if($kolomBulanan)<h2>Rekap bulanan</h2>@include('dashboard-humas._bulanan')@endif
    <h2>Perlu perhatian saat ini</h2><p><small>Seluruh periode; kondisi pada {{ today()->format('d-m-Y') }}.</small></p>
    <table><thead><tr><th>Tindak lanjut</th><th style="width:20%" class="hd-number">Jumlah</th></tr></thead><tbody>@forelse($perhatian as $item)<tr><td>{{ $item['label'] }}</td><td class="hd-number">{{ $item['jumlah'] }}</td></tr>@empty<tr><td colspan="2">Tidak ada tindak lanjut yang perlu perhatian dari data yang dapat Anda akses.</td></tr>@endforelse</tbody></table>
    @endif
    <p class="print-note">Ringkasan mengikuti hak akses akun dan status data saat dicetak. Tidak memuat isi atau identitas pelapor, kontak pribadi, maupun berkas privat. Draf tidak dihitung sebagai capaian final.</p>
</main></body></html>
