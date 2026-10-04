@extends('layouts.app')
@section('title', 'Laporan Pelaksanaan Humas - NUSA')
@section('program-actions')
    <a class="button button-muted" href="{{ route('program-kerja-humas.show', $program) }}">Kembali ke program</a>
    <a class="button button-muted" href="{{ route('program-kerja-humas.laporan.cetak', [$program, $laporan]) }}" target="_blank" rel="noopener">Cetak laporan</a>
    @if($program->terbuka() && $laporan->status === 'draf' && auth()->user()->memilikiIzin('program_kerja_humas.kelola'))<a class="button button-primary" href="{{ route('program-kerja-humas.laporan.edit', [$program, $laporan]) }}">Edit laporan</a>@endif
@endsection
@section('content')
@php($kelola = $program->terbuka() && auth()->user()->memilikiIzin('program_kerja_humas.kelola'))
<div class="publikasi-page humas-program-page">
    @include('program-kerja-humas._header', ['judulHalaman' => $isi['judul']])
    <p class="agenda-muted">{{ $isi['program']['nama'] ?? $program->nama }}</p>
    <div class="humas-program-toolbar"><span class="program-badge program-badge--{{ $laporan->status === 'final' ? 'selesai' : $laporan->status }}">{{ \App\Models\LaporanPelaksanaanHumas::STATUS[$laporan->status] }}</span>@if($laporan->difinalisasi_pada)<span class="agenda-muted">Difinalisasi {{ $laporan->difinalisasi_pada->format('d-m-Y H:i') }}</span>@endif</div>
    @if(!$program->terbuka())<div class="alert alert-info" style="margin-top:16px">Program {{ strtolower(\App\Models\ProgramKerjaHumas::STATUS[$program->status]) }}. Laporan hanya dapat dilihat dan dicetak.</div>@endif
    <section class="agenda-section"><h2>Pelaksanaan kegiatan</h2><dl class="agenda-facts">
        <div><dt>Tanggal pelaksanaan</dt><dd>{{ \Carbon\Carbon::parse($isi['tanggal_mulai'])->format('d-m-Y') }} s.d. {{ \Carbon\Carbon::parse($isi['tanggal_selesai'])->format('d-m-Y') }}</dd></div>
        <div><dt>Tempat</dt><dd>{{ $isi['tempat'] }}</dd></div><div><dt>Pelaksana</dt><dd>{{ $isi['pelaksana'] }}</dd></div><div><dt>Jumlah peserta</dt><dd>{{ $isi['jumlah_peserta'] }}</dd></div>
        @if($bolehAgenda && ($isi['agenda'] ?? null))<div class="span-2"><dt>Agenda terkait</dt><dd><a href="{{ route('agenda-humas.show', $isi['agenda_humas_id']) }}">{{ $isi['agenda'] }}</a></dd></div>@endif
    </dl>@foreach(['uraian' => 'Uraian pelaksanaan', 'hasil' => 'Hasil / capaian', 'kendala' => 'Kendala', 'tindak_lanjut' => 'Tindak lanjut'] as $key => $label)<h3>{{ $label }}</h3><div class="agenda-text">{{ $isi[$key] ?: '-' }}</div>@endforeach</section>
    <section class="agenda-section"><h2>Bukti kegiatan</h2>
        @if($bolehDokumen)
            @forelse($laporan->bukti as $b)<div class="humas-evidence"><div><strong>{{ $b->judul }}</strong><p class="agenda-muted">{{ $b->berkas->nama_file_asli }} &middot; Versi dokumen {{ $b->berkas->versi }}</p></div><a class="button button-muted" href="{{ route('program-kerja-humas.laporan.berkas', [$program, $laporan, $b]) }}">Unduh bukti</a>
                @if($kelola && $laporan->status === 'draf')<details><summary>Lepas bukti</summary><form method="POST" action="{{ route('program-kerja-humas.laporan.bukti.destroy', [$program, $laporan, $b]) }}" data-agenda-submit>@csrf @method('DELETE')<input type="hidden" name="versi" value="{{ $laporan->versi }}"><div class="field"><label for="lepas-{{ $b->id }}">Alasan pelepasan</label><textarea class="textarea" id="lepas-{{ $b->id }}" name="catatan_perubahan" minlength="5" maxlength="2000" required></textarea></div><button type="submit" class="button button-muted">Lepas dari laporan</button></form></details>@endif
            </div>@empty<p class="agenda-muted">Belum ada bukti terlampir.</p>@endforelse
            @if($kelola && $laporan->status === 'draf')
                @if(auth()->user()->memilikiIzin('dokumen_humas.kelola'))<h3>Unggah bukti baru</h3><form method="POST" enctype="multipart/form-data" action="{{ route('program-kerja-humas.laporan.bukti.store', [$program, $laporan]) }}" data-publikasi-upload data-upload-noun="bukti kegiatan">@csrf<input type="hidden" name="versi" value="{{ $laporan->versi }}"><input type="hidden" name="token_pembuatan" value="{{ old('token_pembuatan', $tokenBukti) }}"><div class="agenda-field-grid"><div class="field"><label for="judul_bukti">Nama bukti</label><input class="input" id="judul_bukti" name="judul" maxlength="180" required></div><div class="field"><label for="berkas">Berkas PDF / foto (maks. 10 MB)</label><input type="file" class="input" id="berkas" name="berkas" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div></div><div class="publikasi-upload" data-upload-state role="status" aria-live="polite" hidden><strong data-upload-label></strong><progress value="0" max="100"></progress></div><div class="agenda-actions"><button type="submit" class="button button-primary">Unggah bukti</button></div></form>@endif
                <h3>Ambil dari Pusat Dokumen</h3><form method="GET" class="agenda-filter"><div class="field"><label for="cari_dokumen">Cari dokumen</label><input class="input" id="cari_dokumen" name="cari_dokumen" maxlength="120" value="{{ request('cari_dokumen') }}"></div><button type="submit" class="button button-muted">Cari dokumen</button></form>
                @if($pilihanDokumen->isNotEmpty())<form method="POST" action="{{ route('program-kerja-humas.laporan.bukti.store', [$program, $laporan]) }}" data-agenda-submit>@csrf<input type="hidden" name="versi" value="{{ $laporan->versi }}"><input type="hidden" name="token_pembuatan" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><div class="agenda-field-grid"><div class="field"><label for="dokumen_humas_id">Dokumen PDF / foto</label><select class="select" id="dokumen_humas_id" name="dokumen_humas_id" required><option value="">Pilih dokumen</option>@foreach($pilihanDokumen as $d)<option value="{{ $d->id }}">{{ $d->judul }}</option>@endforeach</select></div><div class="field"><label for="judul_dokumen">Nama bukti di laporan</label><input class="input" id="judul_dokumen" name="judul" maxlength="180" required></div></div><div class="agenda-actions"><button type="submit" class="button button-muted">Hubungkan dokumen</button></div></form>@else<p class="agenda-muted">Tidak ada dokumen yang sesuai.</p>@endif
                {{ $pilihanDokumen->links() }}
            @endif
        @else<p class="agenda-muted">Bukti dibatasi sesuai izin Pusat Dokumen Humas.</p>@endif
    </section>
    @if($kelola)<section class="agenda-section"><h2>Status laporan</h2>
        @if($laporan->status === 'draf')<form method="POST" action="{{ route('program-kerja-humas.laporan.tindakan', [$program, $laporan, 'finalisasi']) }}" data-agenda-submit>@csrf<input type="hidden" name="versi" value="{{ $laporan->versi }}"><button type="submit" class="button button-primary">Finalisasi laporan</button></form><details style="margin-top:18px"><summary>Batalkan laporan</summary><form method="POST" action="{{ route('program-kerja-humas.laporan.tindakan', [$program, $laporan, 'batalkan']) }}" data-agenda-submit>@csrf<input type="hidden" name="versi" value="{{ $laporan->versi }}"><div class="field"><label for="alasan_batal">Alasan pembatalan</label><textarea class="textarea" id="alasan_batal" name="catatan_perubahan" minlength="5" maxlength="2000" required></textarea></div><button type="submit" class="button button-muted">Batalkan laporan</button></form></details>
        @else<form method="POST" action="{{ route('program-kerja-humas.laporan.tindakan', [$program, $laporan, 'revisi']) }}" data-agenda-submit>@csrf<input type="hidden" name="versi" value="{{ $laporan->versi }}"><div class="field"><label for="alasan_revisi">Alasan membuka revisi</label><textarea class="textarea" id="alasan_revisi" name="catatan_perubahan" minlength="5" maxlength="2000" required></textarea></div><div class="agenda-actions"><button type="submit" class="button button-muted">Buka revisi</button></div></form>@endif
    </section>@endif
    @include('program-kerja-humas._riwayat', ['jenisRiwayat' => 'laporan'])
</div>
@if($kelola)
    @include('publikasi-humas._scripts')
    @include('agenda-humas._scripts')
    @include('program-kerja-humas._scripts')
@endif
@endsection
