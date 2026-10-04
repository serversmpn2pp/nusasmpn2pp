<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Rekap Alumni - NUSA</title>
@include('program-kerja-humas._cetak-style')
<style>
    @page { size:A4 landscape; margin:12mm; }
    body { font-size:10pt; }
    .print-page { max-width:273mm; }
    .print-summary { display:flex; flex-wrap:wrap; gap:5mm; margin:4mm 0; }
    th,td { font-size:9pt; padding:2mm; }
    .alumni-print-wrap { overflow:auto; }
    table { min-width:245mm; }
    @media print { table { min-width:0; } .alumni-print-wrap { overflow:visible; } }
</style></head><body>
<div class="print-toolbar"><button type="button" onclick="window.print()">Cetak rekap</button><a href="{{ route('alumni-humas.index', $filter) }}">Kembali</a></div>
<main class="print-page">
    <header class="print-header"><img src="{{ asset('images/logo-padang-panjang.png') }}" alt="Logo Padang Panjang"><div><strong>SMP NEGERI 2 PADANG PANJANG</strong><h1>REKAP ALUMNI & SEKOLAH LANJUTAN</h1><p>Dicetak {{ now()->translatedFormat('d F Y H:i') }}</p></div><img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo sekolah"></header>
    <p>Angkatan: {{ $filter['tahun_lulus'] ?? 'Semua' }} &middot; Status data: {{ ['semua' => 'Semua status', ...\App\Models\AlumniHumas::STATUS][$filter['status'] ?? 'aktif'] }}</p>
    <p>Penelusuran: {{ \App\Models\AlumniHumas::PENELUSURAN[$filter['status_penelusuran'] ?? ''] ?? 'Semua' }} &middot; Jenis sekolah: {{ \App\Models\AlumniHumas::SEKOLAH[$filter['jenis_sekolah'] ?? ''] ?? 'Semua' }} &middot; Jenis kelamin: {{ ['L' => 'Laki-laki', 'P' => 'Perempuan'][$filter['jenis_kelamin'] ?? ''] ?? 'Semua' }} @if(filled($filter['kata_kunci'] ?? null))&middot; Pencarian: {{ $filter['kata_kunci'] }}@endif</p>
    <div class="print-summary">@foreach(['total' => 'Total alumni', 'melanjutkan' => 'Melanjutkan', 'tidak_melanjutkan' => 'Tidak melanjutkan', 'belum_terdata' => 'Belum terdata'] as $key => $label)<span>{{ $label }}: <strong>{{ $statistik[$key] }}</strong></span>@endforeach</div>
    <div class="alumni-print-wrap"><table><colgroup><col style="width:4%"><col style="width:23%"><col style="width:9%"><col style="width:10%"><col style="width:17%"><col style="width:26%"><col style="width:11%"></colgroup><thead><tr><th>No.</th><th>Nama / NISN</th><th>Angkatan</th><th>Kelas terakhir</th><th>Penelusuran</th><th>Sekolah lanjutan / kota</th><th>Tanggal penelusuran</th></tr></thead><tbody>
        @forelse($daftar as $a)<tr><td>{{ $loop->iteration }}</td><td><strong>{{ $a->nama_lengkap }}</strong><br>NISN: {{ $a->nisn ?: '-' }}@if($a->status === 'arsip')<br>Diarsipkan @endif</td><td>{{ $a->tahun_lulus }}</td><td>{{ $a->kelas_terakhir ?: '-' }}</td><td>{{ \App\Models\AlumniHumas::PENELUSURAN[$a->status_penelusuran] }}</td><td>@if($a->status_penelusuran === 'melanjutkan'){{ $a->nama_sekolah }}<br>{{ \App\Models\AlumniHumas::SEKOLAH[$a->jenis_sekolah] }}{{ $a->kota_sekolah ? ' - '.$a->kota_sekolah : '' }}@else - @endif</td><td>{{ $a->tanggal_penelusuran?->format('d-m-Y') ?: '-' }}</td></tr>@empty<tr><td colspan="7">Belum ada alumni yang sesuai filter.</td></tr>@endforelse
    </tbody></table></div>
    <p class="print-note">Penelusuran terdata: {{ $statistik['persen_terdata'] }}%. Rekap ini tidak memuat kontak atau catatan privat alumni.</p>
</main></body></html>
