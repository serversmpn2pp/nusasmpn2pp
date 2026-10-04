<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>QR Presensi Pertemuan</title>
<style>
    @page { size:A4 portrait; margin:18mm; }
    * { box-sizing:border-box; } body { margin:0; font:12pt/1.5 Arial,sans-serif; color:#202d3a; background:#edf0f3; }
    .toolbar { display:flex; justify-content:center; gap:12px; padding:16px; } .toolbar button,.toolbar a { font:inherit; padding:8px 16px; background:#fff; border:1px solid #bcc5ce; color:#202d3a; border-radius:4px; text-decoration:none; cursor:pointer; }
    .sheet { background:#fff; width:174mm; min-height:245mm; margin:0 auto 20px; padding:12mm; text-align:center; }
    .header { display:flex; align-items:center; justify-content:space-between; gap:12px; border-bottom:1pt solid #243e59; padding-bottom:8mm; } .header img { width:16mm; height:20mm; object-fit:contain; } .header strong { font-size:13pt; }
    h1 { font-size:20pt; margin:12mm 0 5mm; } h2 { font-size:15pt; overflow-wrap:anywhere; margin:0 0 8mm; } .qr svg { display:block; width:75mm; height:75mm; margin:8mm auto; } .facts { overflow-wrap:anywhere; } .foot { border-top:1px solid #cad1d8; margin-top:12mm; padding-top:6mm; font-size:10pt; }
    @media print { body { background:#fff; } .toolbar { display:none; } .sheet { width:100%; margin:0; padding:0; min-height:0; } }
    @media screen and (max-width:680px) { .sheet { width:100%; min-height:0; padding:20px; } .header strong { font-size:11pt; } }
</style></head><body>
<div class="toolbar"><button type="button" onclick="window.print()">Cetak QR</button><a href="{{ route('agenda-humas.show', [$agendaHumas, 'tab' => 'qr']) }}">Kembali</a></div>
<article class="sheet"><header class="header"><img src="{{ asset('images/logo-padang-panjang.png') }}" alt="Logo Padang Panjang"><strong>SMP NEGERI 2 PADANG PANJANG<br>HUMAS</strong><img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo sekolah"></header>
<h1>Presensi Pertemuan</h1><h2>{{ $agendaHumas->judul }}</h2>
<div class="facts">{{ $agendaHumas->waktu_mulai->locale('id')->translatedFormat('l, d F Y') }}<br>{{ $agendaHumas->waktu_mulai->format('H:i') }} - {{ $agendaHumas->waktu_selesai->format('H:i') }} WIB<br>{{ $agendaHumas->tempat }}</div>
<div class="qr">{!! $qrSvg !!}</div>
<p>Pindai QR, masuk dengan akun orang tua/wali NUSA,<br>lalu konfirmasi kehadiran Anda.</p>
<footer class="foot">Presensi dibuka dan ditutup oleh petugas Humas.<br>Bila membutuhkan bantuan, hubungi petugas di tempat pertemuan.</footer>
</article></body></html>
