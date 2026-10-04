@extends('layouts.app')
@section('title', 'Umpan Balik Orang Tua - NUSA')
@section('uf-actions')
@if(auth()->user()->memilikiIzin('umpan_balik_humas.kelola'))<a class="button button-primary" href="{{ route('umpan-balik-humas.create') }}">Tambah formulir</a>@endif
@endsection
@section('content')
<main class="uf-page">
    @include('umpan-balik-humas._header', ['judulHalaman' => 'Umpan Balik Orang Tua'])
    <div class="agenda-metrics">@foreach(\App\Models\UmpanBalikHumas::STATUS as $kode => $label)<div class="agenda-metric"><span>{{ $label }}</span><strong>{{ $jumlah[$kode] ?? 0 }}</strong></div>@endforeach</div>
    <form method="GET" class="uf-filter"><div class="field"><label for="kata_kunci">Cari formulir</label><input class="input" id="kata_kunci" name="kata_kunci" maxlength="120" value="{{ $filter['kata_kunci'] ?? '' }}"></div>
        <div class="field"><label for="tahun_pelajaran_id">Tahun pelajaran</label><select class="select" id="tahun_pelajaran_id" name="tahun_pelajaran_id"><option value="">Semua tahun</option>@foreach($tahun as $t)<option value="{{ $t->id }}" @selected((string)($filter['tahun_pelajaran_id'] ?? '') === (string)$t->id)>{{ $t->nama }}</option>@endforeach</select></div>
        <div class="field"><label for="status">Status</label><select class="select" id="status" name="status"><option value="">Semua status</option>@foreach(\App\Models\UmpanBalikHumas::STATUS as $kode => $label)<option value="{{ $kode }}" @selected(($filter['status'] ?? '') === $kode)>{{ $label }}</option>@endforeach</select></div><div class="actions"><button class="button button-primary" type="submit">Terapkan</button><a class="button button-muted" href="{{ route('umpan-balik-humas.index') }}">Reset</a></div>
    </form>
    <section class="uf-section" style="margin-top:24px">@forelse($daftar as $f)<article class="uf-row"><div><span class="uf-badge uf-badge--{{ $f->status }}">{{ $f->labelStatus() }}</span><h2>{{ $f->judul }}</h2><p>{{ $f->tahunPelajaran->nama }} &middot; {{ \App\Models\UmpanBalikHumas::CAKUPAN[$f->cakupan] }}</p><p class="agenda-muted">{{ $f->mulai_pada->format('d-m-Y H:i') }} s.d. {{ $f->selesai_pada->format('d-m-Y H:i') }} WIB</p><p>{{ $f->respons_count }} / {{ $f->sasaran_count }} akun merespons &middot; {{ $f->tertunda_count }} tindak lanjut belum selesai</p></div><a class="button button-muted" href="{{ route('umpan-balik-humas.show', $f) }}">Buka rekap</a></article>@empty<div class="agenda-empty"><strong>Belum ada formulir yang sesuai.</strong></div>@endforelse{{ $daftar->links() }}</section>
</main>
@endsection
