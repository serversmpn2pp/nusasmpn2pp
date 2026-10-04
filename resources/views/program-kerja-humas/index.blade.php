@extends('layouts.app')
@section('title', 'Program Kerja Humas - NUSA')
@section('program-actions')
    @if(auth()->user()->memilikiIzin('program_kerja_humas.kelola'))<a class="button button-primary" href="{{ route('program-kerja-humas.create') }}">Tambah program</a>@endif
@endsection
@section('content')
<div class="publikasi-page humas-program-page">
    @include('program-kerja-humas._header', ['judulHalaman' => 'Program kerja Waka Humas'])
    <div class="agenda-metrics">@foreach($statistik as $kode => $jumlah)<div class="agenda-metric"><span>{{ \App\Models\ProgramKerjaHumas::STATUS[$kode] }}</span><strong>{{ $jumlah }}</strong></div>@endforeach</div>
    <section class="agenda-section"><form method="GET" class="humas-program-filter">
        <div class="field"><label for="kata_kunci">Cari program</label><input class="input" id="kata_kunci" name="kata_kunci" value="{{ $filter['kata_kunci'] ?? '' }}" maxlength="120"></div>
        <div class="field"><label for="tahun_pelajaran_id">Tahun pelajaran</label><select class="select" id="tahun_pelajaran_id" name="tahun_pelajaran_id"><option value="">Semua tahun</option>@foreach($tahun as $t)<option value="{{ $t->id }}" @selected((string)($filter['tahun_pelajaran_id'] ?? '') === (string)$t->id)>{{ $t->nama }}</option>@endforeach</select></div>
        @foreach(['semester' => ['Periode', \App\Models\ProgramKerjaHumas::SEMESTER], 'bidang' => ['Bidang', \App\Models\ProgramKerjaHumas::BIDANG], 'status' => ['Status', \App\Models\ProgramKerjaHumas::STATUS]] as $key => [$label, $pilihan])
        <div class="field"><label for="{{ $key }}">{{ $label }}</label><select class="select" id="{{ $key }}" name="{{ $key }}"><option value="">Semua</option>@foreach($pilihan as $kode => $nama)<option value="{{ $kode }}" @selected(($filter[$key] ?? '') === $kode)>{{ $nama }}</option>@endforeach</select></div>
        @endforeach
        <div class="field"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="terlambat" value="1" @checked($filter['terlambat'] ?? false)>Lewat target ({{ $terlambat }})</label><div class="actions"><button class="button button-primary" type="submit">Terapkan</button><a class="button button-muted" href="{{ route('program-kerja-humas.index') }}">Reset</a></div></div>
    </form></section>
    @forelse($daftar as $program)<article class="humas-program-row">
        <div><span class="program-badge program-badge--{{ $program->status }}">{{ \App\Models\ProgramKerjaHumas::STATUS[$program->status] }}</span>@if($program->terlambat()) <span class="program-badge program-badge--terlambat">Lewat target</span>@endif
            <h2><a href="{{ route('program-kerja-humas.show', $program) }}">{{ $program->nama }}</a></h2><p class="agenda-muted">{{ $program->tahunPelajaran->nama }} &middot; {{ \App\Models\ProgramKerjaHumas::SEMESTER[$program->semester] }} &middot; {{ \App\Models\ProgramKerjaHumas::BIDANG[$program->bidang] }}</p>
            <p>{{ $program->tanggal_mulai->format('d-m-Y') }} s.d. {{ $program->tanggal_selesai->format('d-m-Y') }}</p><p class="agenda-muted">Penanggung jawab: {{ $program->penanggung_jawab }}</p></div>
        <div><p>Realisasi kegiatan (laporan final)</p><strong>{{ $program->laporan_final_count }} / {{ $program->target_kegiatan }}</strong><progress class="humas-progress" value="{{ min($program->laporan_final_count, $program->target_kegiatan) }}" max="{{ $program->target_kegiatan }}" aria-label="Realisasi kegiatan"></progress></div>
        <a class="button button-muted" href="{{ route('program-kerja-humas.show', $program) }}">Buka program</a>
    </article>@empty<div class="agenda-empty"><strong>Belum ada program yang sesuai.</strong></div>@endforelse
    {{ $daftar->links() }}
</div>
@endsection
