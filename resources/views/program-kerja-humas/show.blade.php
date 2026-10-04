@extends('layouts.app')
@section('title', 'Rincian Program Kerja Humas - NUSA')
@section('program-actions')
    <a class="button button-muted" href="{{ route('program-kerja-humas.index') }}">Daftar program</a>
    <a class="button button-muted" href="{{ route('program-kerja-humas.cetak', $program) }}" target="_blank" rel="noopener">Cetak rekap</a>
    @if(auth()->user()->memilikiIzin('program_kerja_humas.kelola'))<a class="button button-primary" href="{{ route('program-kerja-humas.edit', $program) }}">Edit program</a>@endif
@endsection
@section('content')
<div class="publikasi-page humas-program-page">
    @include('program-kerja-humas._header', ['judulHalaman' => $program->nama])
    <div class="agenda-metrics"><div class="agenda-metric"><span>Status program</span><strong style="font-size:17px">{{ \App\Models\ProgramKerjaHumas::STATUS[$program->status] }}</strong></div><div class="agenda-metric"><span>Target kegiatan</span><strong>{{ $program->target_kegiatan }}</strong></div><div class="agenda-metric"><span>Realisasi (laporan final)</span><strong>{{ $program->laporan_final_count }}</strong></div><div class="agenda-metric"><span>Draf laporan</span><strong>{{ $program->draf_count }}</strong></div></div>
    @if($program->terlambat())<div class="alert alert-warning">Program melewati target selesai {{ $program->tanggal_selesai->format('d-m-Y') }}.</div>@endif
    <section class="agenda-section"><h2>Rencana program</h2><dl class="agenda-facts"><div><dt>Tahun pelajaran / periode</dt><dd>{{ $program->tahunPelajaran->nama }} / {{ \App\Models\ProgramKerjaHumas::SEMESTER[$program->semester] }}</dd></div><div><dt>Bidang</dt><dd>{{ \App\Models\ProgramKerjaHumas::BIDANG[$program->bidang] }}</dd></div><div><dt>Penanggung jawab</dt><dd>{{ $program->penanggung_jawab }}</dd></div><div><dt>Rencana pelaksanaan</dt><dd>{{ $program->tanggal_mulai->format('d-m-Y') }} s.d. {{ $program->tanggal_selesai->format('d-m-Y') }}</dd></div></dl>
        @foreach(['tujuan' => 'Tujuan', 'sasaran' => 'Sasaran', 'target_hasil' => 'Target hasil / indikator keberhasilan'] as $key => $label)<h3>{{ $label }}</h3><div class="agenda-text">{{ $program->$key }}</div>@endforeach
        @if($program->evaluasi)<h3>Evaluasi program</h3><div class="agenda-text">{{ $program->evaluasi }}</div>@endif
    </section>
    <section class="agenda-section"><div class="program-section-head"><h2>Laporan pelaksanaan</h2>@if($program->terbuka() && auth()->user()->memilikiIzin('program_kerja_humas.kelola'))<a class="button button-primary" href="{{ route('program-kerja-humas.laporan.create', $program) }}">Tambah laporan</a>@endif</div>
        @forelse($laporan as $l)<div class="humas-program-row"><div><span class="program-badge program-badge--{{ $l->status === 'final' ? 'selesai' : $l->status }}">{{ \App\Models\LaporanPelaksanaanHumas::STATUS[$l->status] }}</span><h2>{{ $l->judul }}</h2><p class="agenda-muted">{{ $l->tanggal_mulai->format('d-m-Y') }} s.d. {{ $l->tanggal_selesai->format('d-m-Y') }} &middot; {{ $l->tempat }}</p></div><div><p>{{ $l->jumlah_peserta }} peserta</p><p class="agenda-muted">{{ $l->bukti_count }} bukti</p></div><a class="button button-muted" href="{{ route('program-kerja-humas.laporan.show', [$program, $l]) }}">Buka laporan</a></div>@empty<div class="agenda-empty">Belum ada laporan pelaksanaan.</div>@endforelse
        {{ $laporan->links() }}
    </section>
    @include('program-kerja-humas._riwayat', ['jenisRiwayat' => 'program', 'bolehDokumen' => false, 'bolehAgenda' => false])
</div>
@endsection
