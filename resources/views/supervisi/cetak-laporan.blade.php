<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Laporan Supervisi Akademik</title>@include('supervisi._cetak-style')</head><body>
<div class="print-toolbar"><span>Laporan supervisi akademik · {{ count($laporan) }} sesi</span><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
<main class="paper">@foreach($laporan as $d)
@php
    $r=$d['sesi']; $p=$d['penilaian']; $ref=$d['refleksi'];
    $scores=collect($p['nilai'])->keyBy(fn($n)=>$n['kelompok'].':'.$n['indikator']);
    $angka=fn($v)=>$v===null?'-':number_format($v,2,',','.');
@endphp
<article class="report" data-report="{{ $r['id'] }}">
    @include('supervisi._kop')
    <div class="doc-title"><h1>LAPORAN SUPERVISI AKADEMIK</h1><p>{{ $d['tahun_pelajaran'] }} · Semester {{ ucfirst($d['semester']) }} · Sesi #{{ $r['id'] }}</p></div>
    <dl class="facts">
        <div><dt>Guru yang disupervisi</dt><dd>{{ $r['guru'] }}</dd></div><div><dt>NIP / NUPTK guru</dt><dd>{{ $d['guru_nip']?:'-' }} / {{ $d['guru_nuptk']?:'-' }}</dd></div>
        <div><dt>Mata pelajaran / kelas</dt><dd>{{ $r['mapel'] }} / {{ $r['kelas'] }}</dd></div><div><dt>Program</dt><dd>{{ $r['program'] }}</dd></div>
        <div><dt>Supervisor</dt><dd>{{ $r['supervisor'] }}</dd></div><div><dt>NIP supervisor</dt><dd>{{ $d['supervisor_nip']?:'-' }}</dd></div>
        <div><dt>Jadwal supervisi</dt><dd>{{ $r['tanggal'] }} · {{ \Carbon\Carbon::parse($r['waktu_mulai'])->format('H:i') }} - {{ \Carbon\Carbon::parse($d['waktu_selesai'])->format('H:i') }} WIB</dd></div><div><dt>Tanggal observasi / tempat</dt><dd>{{ \Carbon\Carbon::parse($d['tanggal_observasi'])->format('d-m-Y') }} / {{ $d['tempat']?:'-' }}</dd></div>
        <div style="grid-column:1/-1"><dt>Instrumen</dt><dd>{{ $d['instrumen']['nama'] }} · Versi {{ $d['instrumen']['nomor'] }}</dd></div>
    </dl>
    <h2>1. Ringkasan Hasil Final</h2>
    <table><thead><tr><th style="width:37%">Tahap</th><th>Skor</th><th>Nilai (0-100)</th><th>Bobot</th></tr></thead><tbody>
        @foreach($p['ringkasan']['tahap'] as $key=>$v)<tr><td>{{ ucfirst($key) }}</td><td class="numeric">{{ $v['skor_perolehan'] }} / {{ $v['skor_maksimal'] }}</td><td class="numeric">{{ $angka($v['nilai']) }}</td><td class="numeric">{{ $v['bobot'] }}%</td></tr>@endforeach
        <tr class="result"><td colspan="2"><strong>Nilai akhir · {{ $r['kategori'] }}</strong></td><td colspan="2" class="numeric"><strong>{{ $angka($r['nilai_akhir']) }}</strong></td></tr>
    </tbody></table>
    <p class="muted">Nilai akhir = (nilai perencanaan + nilai pelaksanaan) / 2. Setiap tahap dinormalisasi terhadap skor maksimal instrumen.</p>
    @foreach(['kekuatan'=>'Kekuatan pembelajaran','perbaikan'=>'Area perbaikan','catatan'=>'Catatan observasi'] as $key=>$label)<div class="field-block"><strong>{{ $label }}</strong><div class="prose">{{ $p[$key]?:'-' }}</div></div>@endforeach
    <div class="status"><strong>{{ $r['label_tahap'] }}</strong>Hasil penilaian final versi {{ $p['versi'] }} · Difinalisasi {{ \Carbon\Carbon::parse($p['difinalisasi_pada'])->format('d-m-Y H:i') }} WIB.@if($r['tindak_lanjut']) Tindak lanjut: {{ $r['tindak_lanjut_selesai'] }} dari {{ $r['tindak_lanjut'] }} selesai.@endif</div>
    <div class="signatures"><div>Guru yang disupervisi<div class="sign-name">{{ $r['guru'] }}</div><div class="muted">NIP {{ $d['guru_nip']?:'-' }}</div></div><div>Supervisor<div class="sign-name">{{ $r['supervisor'] }}</div><div class="muted">NIP {{ $d['supervisor_nip']?:'-' }}</div></div></div>
    <p class="doc-meta">Sesi #{{ $r['id'] }} · Dicetak {{ \Carbon\Carbon::parse($d['dicetak_pada'])->format('d-m-Y H:i') }} WIB oleh {{ $d['dicetak_oleh'] }} · Ruang tanda tangan diisi setelah pemeriksaan.</p>

    <section class="section-start"><h2>2. Rincian Penilaian Indikator</h2>
        <p class="muted">{{ $r['guru'] }} · Sesi #{{ $r['id'] }} · {{ $d['instrumen']['nama'] }} versi {{ $d['instrumen']['nomor'] }}</p>
        <p class="muted">Skala: @foreach($d['instrumen']['skala'] as $skala){{ $skala['skor'] }} = {{ $skala['label'] }}{{ $loop->last?'':'; ' }}@endforeach</p>
        @foreach($d['instrumen']['kelompok'] as $g=>$group)
        <h3>{{ ucfirst($group['tahap']) }} · {{ $group['nama'] }}</h3>
        <table><thead><tr><th style="width:7%">No.</th><th style="width:49%">Indikator</th><th style="width:9%">Skor</th><th style="width:35%">Catatan / bukti</th></tr></thead><tbody>
        @foreach($group['indikator'] as $i=>$label)@php($n=$scores->get($g.':'.$i))<tr><td class="center">{{ $loop->iteration }}</td><td>{{ $label }}</td><td class="center">{{ $n['skor'] }}</td><td class="prose">{{ $n['catatan']??'-' }}</td></tr>@endforeach
        </tbody></table>@endforeach
    </section>
    <section class="section-start"><h2>3. Praobservasi Guru</h2>
        <p class="muted">{{ $r['guru'] }} · Praobservasi terkirim versi {{ $d['praobservasi']['versi'] }}</p>
        @foreach(\App\Services\Supervisi\PengisianSupervisiService::ISIAN as $key=>$label)<div class="field-block"><strong>{{ $label }}</strong><div class="prose">{{ $d['praobservasi']['isian'][$key]??'-' }}</div></div>@endforeach
        @if($d['praobservasi']['catatan_supervisor'])<div class="field-block"><strong>Catatan supervisor</strong><div class="prose">{{ $d['praobservasi']['catatan_supervisor'] }}</div></div>@endif
        @if($d['praobservasi']['lampiran'])<h3>Dokumen pendukung praobservasi</h3><ul class="attachment-list">@foreach($d['praobservasi']['lampiran'] as $f)<li>{{ $f['nama_file_asli'] }}</li>@endforeach</ul>@endif
        <h2>4. Refleksi dan Evaluasi Pascaobservasi</h2>
        @if($ref['terlihat'])
            @foreach($ref['pertanyaan'] as $i=>$label)<div class="field-block"><strong>{{ $i+1 }}. {{ $label }}</strong><div class="prose">{{ $ref['jawaban'][$i]??'-' }}</div></div>@endforeach
            @if($ref['catatan_supervisor'])<div class="field-block"><strong>Catatan supervisor</strong><div class="prose">{{ $ref['catatan_supervisor'] }}</div></div>@endif
            @if($ref['catatan_penutup'])<div class="field-block"><strong>Catatan penutup</strong><div class="prose">{{ $ref['catatan_penutup'] }}</div></div>@endif
        @else<p>{{ $ref['status']==='penyesuaian_hasil'?'Refleksi perlu disesuaikan dengan hasil observasi terbaru.':'Refleksi belum dikirim atau masih dalam perbaikan.' }}</p>@endif
    </section>
    <section><h2>5. Rencana dan Pelaksanaan Tindak Lanjut</h2>
        @forelse($d['tindak_lanjut'] as $t)
            <h3>Tindak lanjut {{ $loop->iteration }} · {{ \App\Models\TindakLanjutSupervisi::STATUS[$t['status']] }}</h3>
            <p class="muted">Tenggat {{ $t['tenggat']?\Carbon\Carbon::parse($t['tenggat'])->format('d-m-Y'):'-' }}@if($t['melewati_tenggat']) · Melewati tenggat @endif</p>
            @foreach(\App\Services\Supervisi\PascaobservasiSupervisiService::RENCANA as $key=>$label)<div class="field-block"><strong>{{ $label }}</strong><div class="prose">{{ $t[$key] }}</div></div>@endforeach
            @if($t['laporan_terlihat'])<p class="muted">Pelaksanaan {{ $t['tanggal_pelaksanaan']?\Carbon\Carbon::parse($t['tanggal_pelaksanaan'])->format('d-m-Y'):'-' }}</p><div class="field-block"><strong>Laporan pelaksanaan</strong><div class="prose">{{ $t['laporan_pelaksanaan']?:'-' }}</div></div><div class="field-block"><strong>Hasil pelaksanaan</strong><div class="prose">{{ $t['hasil_pelaksanaan']?:'-' }}</div></div>
            @if($t['lampiran'])<h3>Bukti pelaksanaan</h3><ul class="attachment-list">@foreach($t['lampiran'] as $f)<li>{{ $f['nama_file_asli'] }}</li>@endforeach</ul>@endif
            @else<p class="muted">Laporan pelaksanaan belum diajukan.</p>@endif
            @if($t['catatan_supervisor'])<div class="field-block"><strong>Catatan supervisor</strong><div class="prose">{{ $t['catatan_supervisor'] }}</div></div>@endif
        @empty<p>Belum ada rencana tindak lanjut yang diterbitkan untuk hasil terbaru.</p>@endforelse
    </section>
    <footer class="doc-meta">Akhir laporan sesi #{{ $r['id'] }} · {{ $r['guru'] }} · NUSA</footer>
</article>@endforeach</main></body></html>
