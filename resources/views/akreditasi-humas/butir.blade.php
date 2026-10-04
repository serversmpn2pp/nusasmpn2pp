@extends('layouts.app')
@section('title', 'Butir Akreditasi Humas - NUSA')
@section('ak-actions')<a class="button button-muted" href="{{ route('akreditasi-humas.show', $portofolio) }}">Kembali ke portofolio</a>@endsection
@section('content')
<main class="ak-page">
    @include('akreditasi-humas._header', ['judulHalaman' => $butir->exists ? $butir->kode.' - '.$butir->judul : 'Tambah butir'])
    @php($dapatEdit = $bolehKelola && $portofolio->status === 'draf')
    @if($butir->exists)
        <span class="ak-badge ak-badge--{{ $butir->status }}">{{ \App\Models\ButirAkreditasiHumas::STATUS[$butir->status] }}</span>
        <dl class="ak-facts"><div><dt>Bukti / target</dt><dd>{{ $butir->bukti->count() }} / {{ $butir->target_bukti }}</dd></div><div><dt>Pemeriksa</dt><dd>{{ $butir->pemeriksa?->nama ?? '-' }}</dd></div><div><dt>Diperiksa pada</dt><dd>{{ $butir->diperiksa_pada?->format('d-m-Y H:i') ?? '-' }}</dd></div></dl>
        @if($butir->deskripsi)<p class="agenda-text">{{ $butir->deskripsi }}</p>@endif
        @if($butir->catatan_pemeriksaan)<p class="ak-note {{ $butir->status === 'perlu_perbaikan' ? 'ak-note--warning' : '' }}">{{ $butir->catatan_pemeriksaan }}</p>@endif
    @endif
    @if($dapatEdit)
        @if($butir->exists)<details class="ak-disclosure"><summary>Edit identitas butir</summary>@else<section class="ak-section">@endif
        <form method="POST" action="{{ $butir->exists ? route('akreditasi-humas.butir.update', [$portofolio, $butir]) : route('akreditasi-humas.butir.store', $portofolio) }}" data-save>
            @csrf @if($butir->exists)@method('PUT')@endif<input type="hidden" name="versi" value="{{ $portofolio->versi }}">
            <div class="ak-grid">
                <div class="field"><label for="kode">Kode butir</label><input class="input" id="kode" name="kode" maxlength="40" value="{{ old('kode', $butir->kode) }}" required></div>
                <div class="field"><label for="judul">Nama butir</label><input class="input" id="judul" name="judul" maxlength="180" value="{{ old('judul', $butir->judul) }}" required></div>
                <div class="field"><label for="urutan">Urutan</label><input class="input" type="number" id="urutan" name="urutan" min="1" max="999" value="{{ old('urutan', $butir->urutan) }}" required></div>
                <div class="field"><label for="target_bukti">Target bukti</label><input class="input" type="number" id="target_bukti" name="target_bukti" min="1" max="200" value="{{ old('target_bukti', $butir->target_bukti) }}" required></div>
                <div class="field ak-wide"><label for="deskripsi">Kebutuhan bukti / ruang lingkup</label><textarea class="input" id="deskripsi" name="deskripsi" maxlength="5000">{{ old('deskripsi', $butir->deskripsi) }}</textarea></div>
            </div><div class="actions" style="margin-top:18px"><button type="submit" class="button button-primary">Simpan butir</button></div>
        </form>
        @if($butir->exists)</details>@else</section>@endif
    @endif
    @if($butir->exists)
    <section class="ak-section"><h2>Bukti tertaut</h2>
        @if(!$bolehDokumen)<p class="ak-note ak-note--warning">Rincian dan unduhan bukti memerlukan akses Pusat Dokumen Humas.</p>
        @else
            @forelse($butir->bukti as $bukti)<article class="ak-row"><div><h3>{{ $bukti->judul }}</h3><p class="agenda-muted">Versi dokumen {{ $bukti->berkas->versi }} &middot; Ditautkan {{ $bukti->created_at->format('d-m-Y') }}</p>@if($bukti->catatan)<p>{{ $bukti->catatan }}</p>@endif</div><a class="button button-muted" href="{{ route('akreditasi-humas.bukti.unduh', [$portofolio, $butir, $bukti]) }}">Unduh bukti</a>
                @if($dapatEdit)<details class="ak-wide"><summary class="ak-danger" style="cursor:pointer">Lepas tautan bukti</summary><form method="POST" action="{{ route('akreditasi-humas.bukti.destroy', [$portofolio, $butir, $bukti]) }}" data-save data-confirm="Lepas tautan bukti ini? Dokumen asli tidak dihapus." style="margin-top:12px">@csrf @method('DELETE')<input type="hidden" name="versi" value="{{ $portofolio->versi }}"><div class="field"><label for="alasan_{{ $bukti->id }}">Alasan pelepasan</label><input class="input" id="alasan_{{ $bukti->id }}" name="alasan" minlength="5" maxlength="2000" required></div><div class="actions" style="margin-top:12px"><button type="submit" class="button button-muted ak-danger">Lepas bukti</button></div></form></details>@endif
            </article>@empty<p class="agenda-muted">Belum ada bukti yang ditautkan.</p>@endforelse
        @endif
    </section>
    @if($dokumen !== null)
    <details class="ak-disclosure" @if($butir->bukti->isEmpty() || request()->has('kata_kunci') || request()->has('page')) open @endif><summary>Tambah bukti dari Pusat Dokumen Humas</summary>
        <form method="GET" class="ak-filter" action="{{ route('akreditasi-humas.butir', [$portofolio, $butir]) }}">
            <div class="field"><label for="kata_kunci">Cari dokumen</label><input class="input" id="kata_kunci" name="kata_kunci" maxlength="120" value="{{ $filter['kata_kunci'] ?? '' }}"></div>
            <div class="field"><label for="kategori">Kategori</label><select class="select" id="kategori" name="kategori"><option value="">Semua kategori</option>@foreach(\App\Models\DokumenHumas::KATEGORI as $k => $label)<option value="{{ $k }}" @selected(($filter['kategori'] ?? '') === $k)>{{ $label }}</option>@endforeach</select></div>
            <div class="actions"><button type="submit" class="button button-muted">Cari</button><a class="button button-muted" href="{{ route('akreditasi-humas.butir', [$portofolio, $butir]) }}">Reset</a></div>
        </form>
        <form method="POST" action="{{ route('akreditasi-humas.bukti.store', [$portofolio, $butir]) }}" data-save data-evidence style="margin-top:18px">@csrf<input type="hidden" name="versi" value="{{ $portofolio->versi }}"><input type="hidden" name="token_pembuatan" value="{{ $tokenBukti }}">
            <fieldset style="border:0;margin:0;padding:0"><legend style="font-weight:700;margin-bottom:12px">Pilih satu dokumen</legend>
                @forelse($dokumen as $d)@php($r = $d->riwayat->first())
                    <label class="ak-doc"><input type="radio" name="riwayat_dokumen_humas_id" value="{{ $r?->id }}" @disabled(!$d->berkas_tersedia || $butir->bukti->contains('riwayat_dokumen_humas_id', $r?->id)) required><span><strong>{{ $d->judul }}</strong><small>{{ \App\Models\DokumenHumas::KATEGORI[$d->kategori] }} &middot; Versi {{ $r?->versi ?? '-' }}@if(!$d->berkas_tersedia) &middot; Berkas tidak tersedia @elseif($butir->bukti->contains('riwayat_dokumen_humas_id', $r?->id)) &middot; Sudah ditautkan @endif</small></span></label>
                @empty<p class="agenda-muted">Tidak ada dokumen aktif yang sesuai.</p>@endforelse
            </fieldset>
            <div class="field" style="margin-top:18px"><label for="catatan_bukti">Catatan keterkaitan bukti</label><textarea class="input" id="catatan_bukti" name="catatan" maxlength="2000"></textarea></div>
            <div class="actions" style="margin-top:12px"><button type="submit" class="button button-primary" data-evidence-submit disabled>Tautkan bukti</button>@if(auth()->user()->memilikiIzin('dokumen_humas.kelola'))<a class="button button-muted" href="{{ route('dokumen-humas.create') }}" target="_blank" rel="noopener">Tambah dokumen baru</a>@endif</div>
        </form>
        <div style="margin-top:18px">{{ $dokumen->links() }}</div>
    </details>
    @endif
    @if($dapatEdit && $bolehDokumen)
    <section class="ak-section"><h2>Pemeriksaan butir</h2><form method="POST" action="{{ route('akreditasi-humas.butir.periksa', [$portofolio, $butir]) }}" class="ak-review-form" data-save>@csrf<input type="hidden" name="versi" value="{{ $portofolio->versi }}">
        <div class="field"><label for="hasil_periksa">Hasil pemeriksaan</label><select class="select" id="hasil_periksa" name="status" required>@foreach(\App\Models\ButirAkreditasiHumas::STATUS as $k => $label)<option value="{{ $k }}" @selected($butir->status === $k)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="catatan_pemeriksaan">Catatan pemeriksaan / alasan tidak berlaku</label><textarea class="input" id="catatan_pemeriksaan" name="catatan_pemeriksaan" minlength="5" maxlength="5000" required>{{ $butir->catatan_pemeriksaan }}</textarea></div>
        <div class="actions"><button type="submit" class="button button-primary">Simpan pemeriksaan</button></div>
    </form></section>
    @endif
    @if($dapatEdit)<details class="ak-disclosure"><summary class="ak-danger">Keluarkan butir dari portofolio</summary><form method="POST" action="{{ route('akreditasi-humas.butir.destroy', [$portofolio, $butir]) }}" data-save data-confirm="Keluarkan butir beserta tautan buktinya dari portofolio? Dokumen asli tidak dihapus.">@csrf @method('DELETE')<input type="hidden" name="versi" value="{{ $portofolio->versi }}"><div class="field"><label for="alasan_butir">Alasan pengeluaran</label><textarea class="input" id="alasan_butir" name="alasan" minlength="5" maxlength="2000" required></textarea></div><div class="actions" style="margin-top:12px"><button type="submit" class="button button-muted ak-danger">Keluarkan butir</button></div></form></details>@endif
    @endif
</main>
@endsection
