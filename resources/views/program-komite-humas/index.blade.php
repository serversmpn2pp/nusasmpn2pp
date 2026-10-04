@extends('layouts.app')
@section('title', 'Program Kerja Komite - NUSA')
@section('program-actions')
    @if ($periode->status !== 'arsip' && auth()->user()->memilikiIzin('komite_humas.kelola'))<a class="button button-primary" href="{{ route('komite-humas.program.create', $periode) }}">Tambah program</a>@endif
@endsection
@section('content')
<div class="publikasi-page komite-page">
    @include('program-komite-humas._header', ['judulHalaman' => 'Program kerja komite'])
    <div class="agenda-metrics">@foreach ($statistik as $kode => $jumlah)<div class="agenda-metric"><span>{{ \App\Models\ProgramKomiteHumas::STATUS[$kode] }}</span><strong>{{ $jumlah }}</strong></div>@endforeach</div>
    <section class="agenda-section">
        <form method="GET" class="program-filter">
            <div class="field"><label for="kata_kunci">Cari program</label><input id="kata_kunci" name="kata_kunci" class="input" value="{{ $filter['kata_kunci'] ?? '' }}" maxlength="120"></div>
            <div class="field"><label for="status">Status</label><select id="status" name="status" class="select"><option value="">Semua status</option>@foreach (\App\Models\ProgramKomiteHumas::STATUS as $kode => $nama)<option value="{{ $kode }}" @selected(($filter['status'] ?? '') === $kode)>{{ $nama }}</option>@endforeach</select></div>
            <label class="check-label"><input type="checkbox" name="terlambat" value="1" @checked($filter['terlambat'] ?? false)>Lewat target ({{ $terlambat }})</label>
            <div class="actions"><button class="button button-primary" type="submit">Terapkan</button><a class="button button-muted" href="{{ route('komite-humas.program.index', $periode) }}">Reset</a></div>
        </form>
    </section>
    <ul class="program-list">@forelse ($daftar as $program)<li class="program-row">
        <div><div class="komite-tags"><span class="program-badge program-badge--{{ $program->status }}">{{ \App\Models\ProgramKomiteHumas::STATUS[$program->status] }}</span>@if ($program->terlambat())<span class="program-badge program-badge--terlambat">Lewat target</span>@endif</div><h2><a href="{{ route('komite-humas.program.show', [$periode, $program]) }}">{{ $program->nama }}</a></h2><p class="agenda-muted">{{ $program->tanggal_mulai->format('d-m-Y') }} s.d. {{ $program->tanggal_selesai->format('d-m-Y') }}</p></div>
        <div><p>Penanggung jawab</p><strong>{{ $program->penanggungJawab?->nama ?: 'Belum ditentukan' }}</strong>@if ($program->penanggungJawab && ! $program->penanggungJawab->aktif)<p class="agenda-muted">Pengurus tidak aktif</p>@endif<p class="agenda-muted">{{ $program->agenda_count }} rapat terkait</p></div>
        <a class="button button-muted" href="{{ route('komite-humas.program.show', [$periode, $program]) }}">Buka program</a>
    </li>@empty<li class="agenda-empty">Belum ada program yang sesuai.</li>@endforelse</ul>
    {{ $daftar->links() }}
</div>
@endsection
