<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Indeks bundel pertemuan</title>@include('program-kerja-humas._cetak-style')</head><body>
<main class="print-page"><header class="print-header"><img src="{{ $logoKota }}" alt="Logo Padang Panjang"><div><strong>SMP NEGERI 2 PADANG PANJANG</strong><h1>BUNDEL DOKUMEN PERTEMUAN</h1></div><img src="{{ $logoSekolah }}" alt="Logo sekolah"></header>
<h2>{{ $agendaHumas->judul }}</h2><p>{{ $agendaHumas->waktu_mulai->format('d-m-Y H:i') }} WIB &middot; {{ $agendaHumas->tempat }}<br>Disusun {{ $dibuatPada->format('d-m-Y H:i') }} WIB.</p>
<p><a href="01-bundel-pertemuan.html">Notulen, daftar hadir, tindak lanjut, rekap terpilih, dan dokumentasi</a></p>
<p>{{ $agendaHumas->peserta->count() }} peserta &middot; {{ count($daftar) }} lampiran &middot; {{ count($rekapUmpan) }} rekap umpan balik.</p>
<h2>Lampiran asli</h2><table><thead><tr><th>Dokumen</th><th>Versi</th><th>Berkas</th></tr></thead><tbody>@forelse($daftar as $d)<tr><td>{{ $d['judul'] }}</td><td>v{{ $d['versi'] }}</td><td><a href="{{ $d['nama'] }}">Buka lampiran</a></td></tr>@empty<tr><td colspan="3">Tidak ada lampiran tambahan dipilih.</td></tr>@endforelse</tbody></table>
<h2>Rekap umpan balik</h2>@forelse($rekapUmpan as $item)<p>{{ $item['formulir']->judul }} &middot; {{ $item['rekap']['respons'] }} / {{ $item['rekap']['sasaran'] }} akun merespons.</p>@empty<p>Tidak ada rekap tambahan dipilih.</p>@endforelse
<p class="print-note">Arsip internal sekolah. Daftar hadir memuat nama peserta. Rekap umpan balik hanya statistik agregat; tidak memuat identitas pengisi, isi jawaban tertulis, atau catatan internal tindak lanjut. Berkas lampiran disertakan dalam versi saat paket dibuat. Paket ini tidak menerbitkan tanda tangan elektronik tersertifikasi.</p>
</main></body></html>
