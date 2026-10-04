<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bundel pertemuan - {{ $agendaHumas->judul }}</title>
@include('agenda-humas._cetak-style')
<style>
    .bundle-subtitle { font-size:9pt; line-height:1.5; overflow-wrap:anywhere; }
    .bundle-summary { margin-top:4mm; font-size:9pt; }
    .bundle-gallery { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:5mm; }
    .bundle-gallery figure { margin:0; min-width:0; break-inside:avoid; }
    .bundle-gallery img { display:block; width:100%; height:73mm; object-fit:contain; border:1px solid #d2d9de; }
    .bundle-gallery figcaption { font-size:8pt; line-height:1.4; margin-top:2mm; overflow-wrap:anywhere; }
    .bundle-feedback th:first-child { width:44%; }
    .bundle-feedback td { font-size:8.5pt; }
    .bundle-attachment tr { break-inside:auto; }
    @media print { .sheet { break-after:auto; break-before:page; } .sheet:first-of-type { break-before:auto; } }
    @media screen and (max-width:600px) { .bundle-gallery { grid-template-columns:minmax(0,1fr); } }
</style></head><body>
<div class="toolbar">@if(!$offline)<a href="{{ route('agenda-humas.bundel',$agendaHumas) }}">Kembali ke bundel</a>@else<a href="index.html">Indeks paket</a>@endif <button type="button" onclick="window.print()">Cetak / Simpan PDF</button><span>A4 portrait</span></div>
@if($hambatan)<article class="sheet">@include('bundel-pertemuan-humas._cetak-header')<h2 class="heading">DRAF BUNDEL PERTEMUAN</h2><p class="text">{{ $agendaHumas->judul }}</p><ul>@foreach($hambatan as $alasan)<li>{{ $alasan }}</li>@endforeach</ul><p class="foot">Belum menjadi bundel arsip final.</p></article>@endif
@include('agenda-humas._cetak-lembar',['jenisCetak'=>'notulen'])
@include('agenda-humas._cetak-lembar',['jenisCetak'=>'daftar-hadir'])
@foreach($rekapUmpan as $item)@php($f=$item['formulir'])@php($r=$item['rekap'])
<article class="sheet">@include('bundel-pertemuan-humas._cetak-header')<h2 class="heading">REKAP UMPAN BALIK ORANG TUA</h2><p class="bundle-subtitle"><strong>{{ $f->judul }}</strong><br>{{ $f->mulai_pada->format('d-m-Y H:i') }} s.d. {{ $f->selesai_pada->format('d-m-Y H:i') }} WIB &middot; {{ $f->labelStatus() }}</p>
    @if($f->status==='aktif' && $f->selesai_pada->isFuture())<p class="draft">REKAP SEMENTARA: periode pengisian belum ditutup.</p>@endif
    <p class="bundle-subtitle">{{ $r['respons'] }} dari {{ $r['sasaran'] }} akun merespons ({{ number_format($r['persen'],1,',','.') }}%).</p>
    <table class="records bundle-feedback"><thead><tr><th>Pertanyaan</th><th>Menilai</th><th>Rata-rata 1-4</th><th>Puas</th><th>Tidak menilai</th></tr></thead><tbody>
    @foreach($f->pertanyaan as $p)@php($q=$r['pertanyaan'][$p->id])<tr><td>{{ $p->urutan }}. {{ $p->teks }}</td>@if($p->jenis==='skala')<td>{{ $q['menilai'] }}</td><td>{{ $q['rata']!==null ? number_format($q['rata'],2,',','.') : '-' }}</td><td>{{ $q['puas']!==null ? number_format($q['puas'],1,',','.').'%' : '-' }}</td><td>{{ $q['skala'][0] }}</td>@else<td colspan="4">{{ $q['teks'] }} jawaban tertulis; isi tidak disertakan.</td>@endif</tr>@endforeach
    </tbody></table><p class="foot">Puas = nilai 3 atau 4. Tidak menilai dan jawaban kosong dikecualikan dari rata-rata. Tidak memuat nama akun pengisi, isi jawaban tertulis, atau catatan internal tindak lanjut.</p>
</article>@endforeach
@if($daftar)<article class="sheet">@include('bundel-pertemuan-humas._cetak-header')<h2 class="heading">INDEKS LAMPIRAN PERTEMUAN</h2><p class="bundle-subtitle">{{ $agendaHumas->judul }}</p><table class="records bundle-attachment"><colgroup><col style="width:8%"><col style="width:62%"><col style="width:12%"><col style="width:18%"></colgroup><thead><tr><th>No</th><th>Dokumen</th><th>Versi</th><th>Ukuran</th></tr></thead><tbody>@foreach($daftar as $d)<tr><td>{{ $loop->iteration }}</td><td>{{ $d['judul'] }}</td><td>v{{ $d['versi'] }}</td><td>{{ number_format($d['ukuran']/1024/1024,2,',','.') }} MB</td></tr>@endforeach</tbody></table><p class="foot">Berkas lampiran asli tersedia terpisah di paket ZIP. Indeks ini bukan salinan seluruh isi dokumen.</p></article>@endif
@foreach($foto->chunk(4) as $lembarFoto)<article class="sheet">@include('bundel-pertemuan-humas._cetak-header')<h2 class="heading">DOKUMENTASI PERTEMUAN</h2><p class="bundle-subtitle">{{ $agendaHumas->judul }}</p><div class="bundle-gallery">@foreach($lembarFoto as $d)<figure><img src="{{ $d['url'] }}" alt="{{ $d['judul'] }}"><figcaption>{{ $d['judul'] }} &middot; v{{ $d['versi'] }}</figcaption></figure>@endforeach</div></article>@endforeach
<div class="bundle-summary" style="max-width:186mm;margin:0 auto 12px;padding:0 8px"><p class="foot">Disusun {{ $dibuatPada->format('d-m-Y H:i') }} WIB. Arsip internal sekolah; tanda tangan pada notulen dan daftar hadir belum merupakan tanda tangan elektronik tersertifikasi.</p></div>
@include('agenda-humas._cetak-presensi-script')
</body></html>
