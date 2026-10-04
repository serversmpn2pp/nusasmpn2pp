<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ringkasan Portofolio Akreditasi</title>
@include('program-kerja-humas._cetak-style')
<style>.ak-print small { font-size:9pt; }.ak-print th:first-child { width:13%; }.ak-print th:last-child { width:26%; } .ak-print td { overflow-wrap:anywhere; } @media print { @page { size:A4 portrait; margin:12mm; } }</style>
</head><body>
<div class="print-toolbar"><button type="button" onclick="window.print()">Cetak ringkasan</button><a href="{{ route('akreditasi-humas.show', $portofolio) }}">Kembali</a></div>
<main class="print-page ak-print">
    <header class="print-header"><img src="{{ asset('images/logo-padang-panjang.png') }}" alt="Logo Padang Panjang"><div><strong>SMP NEGERI 2 PADANG PANJANG</strong><h1>PORTOFOLIO AKREDITASI HUMAS</h1></div><img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo sekolah"></header>
    <h2>{{ $portofolio->nama }}</h2><p>{{ $portofolio->instrumen }} &middot; {{ $portofolio->tahunPelajaran->nama }}<br>Penanggung jawab: {{ $portofolio->penanggung_jawab }}<br>Status: {{ \App\Models\PortofolioAkreditasiHumas::STATUS[$portofolio->status] }} &middot; Versi {{ $portofolio->versi }}</p>
    <p>{{ $ringkasan['terpenuhi'] }} / {{ $ringkasan['berlaku'] }} butir berlaku terpenuhi &middot; {{ $ringkasan['tidak_berlaku'] }} tidak berlaku &middot; {{ $ringkasan['bukti'] }} bukti tertaut</p>
    @if($ringkasan['hilang'])<p><strong>{{ $ringkasan['hilang'] }} berkas tidak tersedia atau format tidak didukung.</strong></p>@endif
    <table><thead><tr><th>Kode</th><th>Butir / bukti</th><th>Pemeriksaan</th></tr></thead><tbody>
        @forelse($portofolio->butir as $b)<tr><td>{{ $b->kode }}</td><td><strong>{{ $b->judul }}</strong><br><small>{{ $b->bukti->count() }} / {{ $b->target_bukti }} bukti</small>
            @if(auth()->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']))
                @foreach($b->bukti as $bukti)<br><small>{{ $loop->iteration }}. {{ $bukti->judul }} (versi {{ $bukti->berkas->versi }})</small>@endforeach
            @endif
        </td><td>{{ \App\Models\ButirAkreditasiHumas::STATUS[$b->status] }}@if($b->catatan_pemeriksaan)<br><small>{{ $b->catatan_pemeriksaan }}</small>@endif</td></tr>@empty<tr><td colspan="3">Belum ada butir.</td></tr>@endforelse
    </tbody></table>
    <p class="print-note">Dicetak {{ now()->format('d-m-Y H:i') }} WIB oleh {{ auth()->user()->nama }}. Ringkasan sesuai hak akses akun; bukan penetapan hasil akreditasi.</p>
</main></body></html>
