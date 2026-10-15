<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Rekap Supervisi Akademik</title>
@include('supervisi._cetak-style')
<style>
    @page { size:A4 landscape; margin:12mm; }
    .paper { max-width:297mm; padding:12mm; }
    .recap-table { font-size:8pt; }
    .recap-table th,.recap-table td { padding:1.8mm; }
    .print-table-wrap { max-width:100%; }
    @media screen and (max-width:1000px) { .print-table-wrap { overflow:auto; } .recap-table { min-width:1000px; } }
    @media print { .paper { padding:0; max-width:none; } .print-table-wrap { overflow:visible; } }
</style></head><body>
<div class="print-toolbar"><span>Rekap supervisi akademik</span><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
<main class="paper">@include('supervisi._kop')
@php($coverage=$filter['tab']==='belum_terjadwal')
<div class="doc-title"><h1>{{ $coverage ? 'GURU BELUM MEMILIKI JADWAL TERBIT' : 'REKAP SUPERVISI AKADEMIK' }}</h1><p>{{ implode(' | ',$label_filter) }}</p></div>
@if($coverage)
<p class="muted">{{ $statistik['guru_aktif'] }} guru aktif · {{ $statistik['sudah_terjadwal'] }} sudah terjadwal · {{ $statistik['belum_terjadwal'] }} belum terjadwal. Acuan: penugasan mengajar aktif pada tahun terpilih; jadwal draf dan dibatalkan tidak dihitung.</p>
@else
<p class="muted">{{ $statistik['sesi'] }} sesi · {{ $statistik['hasil_final'] }} hasil final · {{ $statistik['selesai'] }} pascaobservasi selesai · Rata-rata per sesi final: {{ $statistik['rata_rata']===null?'-':number_format($statistik['rata_rata'],2,',','.') }}. Nilai draf tidak dihitung; nilai kosong bukan nol.</p>
@endif
<div class="print-table-wrap"><table class="recap-table"><thead>
@if($coverage)<tr><th style="width:5%">No.</th><th style="width:25%">Guru</th><th style="width:30%">Mata pelajaran</th><th style="width:25%">Kelas</th><th style="width:15%">Status</th></tr>
@else<tr><th style="width:4%">No.</th><th style="width:21%">Guru / jadwal / mapel</th><th style="width:15%">Supervisor</th><th style="width:9%">Perencanaan</th><th style="width:9%">Pelaksanaan</th><th style="width:12%">Hasil final</th><th style="width:20%">Progres</th><th style="width:10%">Tindak lanjut</th></tr>@endif
</thead><tbody>
@forelse($semua as $r)<tr><td>{{ $loop->iteration }}</td><td><strong>{{ $r['guru'] }}</strong>@unless($coverage)<br>{{ $r['tanggal'] }}<br>{{ $r['mapel'] }} · {{ $r['kelas'] }}<br>{{ $r['program'] }}@endunless</td>
@if($coverage)<td>{{ $r['mapel'] }}</td><td>{{ $r['kelas'] }}</td><td>{{ $r['status'] }}</td>
@else
<td>{{ $r['supervisor'] }}</td>
@foreach(['nilai_perencanaan','nilai_pelaksanaan','nilai_akhir'] as $key)
<td class="numeric">{{ $r[$key]===null?'-':number_format($r[$key],2,',','.') }}
@if($key==='nilai_akhir')<br>{{ $r['kategori']??'Belum final' }}@endif
</td>
@endforeach
<td>{{ $r['label_tahap'] }}</td><td>{{ $r['tindak_lanjut_selesai'] }}/{{ $r['tindak_lanjut'] }} selesai
@if($r['tindak_lanjut_terlambat'])<br>{{ $r['tindak_lanjut_terlambat'] }} lewat tenggat @endif
</td>
@endif
</tr>
@empty<tr><td colspan="{{ $coverage?5:8 }}" class="center">Tidak ada data pada filter ini.</td></tr>@endforelse
</tbody></table></div>
<footer class="doc-meta">Dicetak {{ now()->format('d-m-Y H:i') }} WIB oleh {{ auth()->user()->nama }} · NUSA</footer>
</main></body></html>
