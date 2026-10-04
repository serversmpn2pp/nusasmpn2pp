@extends('layouts.app')
@section('title', 'Portofolio Akreditasi Humas - NUSA')
@section('ak-actions')
    @if(auth()->user()->memilikiIzin('akreditasi_humas.kelola'))<a class="button button-primary" href="{{ route('akreditasi-humas.create') }}">Tambah portofolio</a>@endif
@endsection
@section('content')
<main class="ak-page">
    @include('akreditasi-humas._header', ['judulHalaman' => 'Portofolio Akreditasi'])
    <div class="agenda-metrics agenda-metrics--three">@foreach(\App\Models\PortofolioAkreditasiHumas::STATUS as $kode => $label)<div class="agenda-metric"><span>{{ $label }}</span><strong>{{ $jumlah[$kode] ?? 0 }}</strong></div>@endforeach</div>
    <form method="GET" class="ak-filter">
        <div class="field"><label for="kata_kunci">Nama / instrumen</label><input class="input" id="kata_kunci" name="kata_kunci" maxlength="120" value="{{ $filter['kata_kunci'] ?? '' }}"></div>
        <div class="field"><label for="tahun_pelajaran_id">Tahun pelajaran</label><select class="select" id="tahun_pelajaran_id" name="tahun_pelajaran_id"><option value="">Semua tahun</option>@foreach($tahun as $t)<option value="{{ $t->id }}" @selected((string)($filter['tahun_pelajaran_id'] ?? '') === (string)$t->id)>{{ $t->nama }}</option>@endforeach</select></div>
        <div class="field"><label for="status">Status</label><select class="select" id="status" name="status"><option value="">Semua status</option>@foreach(\App\Models\PortofolioAkreditasiHumas::STATUS as $kode => $label)<option value="{{ $kode }}" @selected(($filter['status'] ?? '') === $kode)>{{ $label }}</option>@endforeach</select></div>
        <div class="actions"><button type="submit" class="button button-primary">Terapkan</button><a class="button button-muted" href="{{ route('akreditasi-humas.index') }}">Reset</a></div>
    </form>
    <section class="ak-section" style="margin-top:24px">
    @forelse($daftar as $p)
        <article class="ak-row"><div><span class="ak-badge ak-badge--{{ $p->status }}">{{ \App\Models\PortofolioAkreditasiHumas::STATUS[$p->status] }}</span>
            <h2><a href="{{ route('akreditasi-humas.show', $p) }}">{{ $p->nama }}</a></h2><p>{{ $p->instrumen }} &middot; {{ $p->tahunPelajaran->nama }}</p><p class="agenda-muted">{{ $p->terpenuhi_count }} butir terpenuhi dari {{ $p->butir_count }} butir &middot; {{ $p->penanggung_jawab }}</p></div>
            <a class="button button-muted" href="{{ route('akreditasi-humas.show', $p) }}">Buka portofolio</a></article>
    @empty<div class="agenda-empty"><strong>Belum ada portofolio yang sesuai.</strong></div>@endforelse
    {{ $daftar->links() }}
    </section>
</main>
@endsection
