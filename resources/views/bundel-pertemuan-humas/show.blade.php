@extends('layouts.app')
@section('title', 'Bundel Pertemuan Humas - NUSA')
@section('content')
<main class="bp-page">
    @include('bundel-pertemuan-humas._style')
    <header class="bp-head"><div><p class="eyebrow">Humas / Agenda & Pertemuan</p><h1>Bundel pertemuan</h1><p>{{ $agendaHumas->judul }}</p><p class="agenda-muted">{{ $agendaHumas->waktu_mulai->format('d-m-Y H:i') }} WIB &middot; {{ $agendaHumas->tempat }}</p></div><a class="button button-muted" href="{{ route('agenda-humas.show', $agendaHumas) }}">Kembali ke agenda</a></header>
    @include('agenda-humas._messages')
    @include('agenda-humas._tabs')
    <div class="agenda-metrics"><div class="agenda-metric"><span>Peserta terdaftar</span><strong>{{ $agendaHumas->peserta->count() }}</strong></div><div class="agenda-metric"><span>Hadir</span><strong>{{ $rekapPresensi['hadir'] }}</strong></div><div class="agenda-metric"><span>Belum dicatat</span><strong>{{ $rekapPresensi['belum_dicatat'] }}</strong></div><div class="agenda-metric"><span>Tindak lanjut belum selesai</span><strong>{{ $agendaHumas->tindakLanjut->where('status', '!=', 'selesai')->count() }}</strong></div></div>
    <section class="bp-section"><h2>Kesiapan bundel</h2><ul class="bp-ready">
        @foreach([
            [$agendaHumas->status === 'selesai', 'Status pertemuan', \App\Models\AgendaHumas::STATUS[$agendaHumas->status], 'ringkasan'],
            [filled($agendaHumas->pembahasan) && filled($agendaHumas->keputusan), 'Notulen', filled($agendaHumas->pembahasan) && filled($agendaHumas->keputusan) ? 'Pembahasan dan keputusan lengkap' : 'Pembahasan atau keputusan belum lengkap', 'notulen'],
            [$agendaHumas->peserta->isNotEmpty() && !$rekapPresensi['belum_dicatat'], 'Presensi peserta', $agendaHumas->peserta->isEmpty() ? 'Belum ada peserta' : ($rekapPresensi['belum_dicatat'] ? $rekapPresensi['belum_dicatat'].' peserta belum dicatat' : 'Seluruh peserta sudah dicatat'), 'peserta'],
        ] as [$siap,$label,$status,$bagian])<li><span class="bp-mark {{ !$siap ? 'bp-mark--wait' : '' }}" aria-hidden="true">@if($siap)&#10003;@else!@endif</span><div><strong><a href="{{ route('agenda-humas.show', [$agendaHumas, 'tab'=>$bagian]) }}">{{ $label }}</a></strong><small>{{ $status }}</small></div></li>@endforeach
    </ul>
    @if($hambatan)<p class="bp-note">Bundel ZIP belum siap. Cetak gabungan tetap tersedia dengan penanda draf.</p>@else<p class="bp-note bp-note--ok">Notulen dan presensi siap dibundel. Tindak lanjut yang masih berjalan tetap dicantumkan sesuai statusnya.</p>@endif
    </section>
    <form method="POST" action="{{ route('agenda-humas.bundel.export', $agendaHumas) }}" id="bp-form">@csrf<input type="hidden" name="sidik" value="{{ $sidik }}">
        <div class="bp-grid"><section class="bp-section"><h2>Lampiran dokumen & foto</h2>
            @if(!$bolehDokumen)<p class="agenda-muted">Akun ini tidak memiliki akses dokumen Humas.</p>
            @elseif($dokumen->isEmpty())<p class="agenda-muted">Belum ada dokumen yang dihubungkan ke pertemuan ini.</p>
            @else<label class="bp-check bp-check--all"><input type="checkbox" data-all="dokumen_ids[]"><span>Pilih semua lampiran aktif</span></label><div class="bp-document-list">
            @foreach($dokumen as $d)@php($v=$d->riwayat->first())<label class="bp-check"><input type="checkbox" name="dokumen_ids[]" value="{{ $v?->id }}" @disabled($d->status !== 'aktif' || !$v) @checked(in_array($v?->id,(array)old('dokumen_ids',[])))><span><strong>{{ $d->judul }}</strong><small>{{ \App\Models\DokumenHumas::KATEGORI[$d->kategori] }} &middot; {{ $d->status === 'aktif' ? 'Aktif' : 'Arsip' }} &middot; {{ $v ? 'v'.$v->versi.' / '.$v->ukuranFileTampil() : 'Belum ada versi berkas' }}</small></span></label>@endforeach
            </div>@endif
            @if($bolehDokumen && auth()->user()->memilikiIzin('agenda_humas.kelola'))<p><a href="{{ route('agenda-humas.show', [$agendaHumas,'tab'=>'dokumen']) }}">Kelola dokumen pertemuan</a></p>@endif
        </section><section class="bp-section"><h2>Rekap umpan balik terkait</h2>
            @if(!$bolehUmpan)<p class="agenda-muted">Akun ini tidak memiliki akses rekap umpan balik.</p>
            @elseif($formulir->isEmpty())<p class="agenda-muted">Belum ada formulir yang dibuka untuk pertemuan ini.</p>
            @else<label class="bp-check bp-check--all"><input type="checkbox" data-all="formulir_ids[]"><span>Pilih semua rekap</span></label>
            @foreach($formulir as $f)<label class="bp-check"><input type="checkbox" name="formulir_ids[]" value="{{ $f->id }}" @checked(in_array($f->id,(array)old('formulir_ids',[])))><span><strong>{{ $f->judul }}</strong><small>{{ $f->labelStatus() }} &middot; {{ $f->respons_count }} / {{ $f->sasaran_count }} akun merespons</small></span></label>@endforeach
            <p class="agenda-muted">Hanya statistik agregat, tanpa nama akun, isi jawaban tertulis, atau catatan internal tindak lanjut.</p>@endif
        </section></div>
        <p class="agenda-muted"><span id="bp-selection">0 lampiran &middot; 0 rekap dipilih</span> &middot; Batas 200 lampiran / 200 MB</p>
        <p class="bp-note">Arsip internal sekolah. Daftar hadir memuat nama peserta; periksa isi lampiran sebelum membagikan paket kepada pihak luar.</p>
        <div class="bp-submit"><button class="button button-muted" type="submit" formaction="{{ route('agenda-humas.bundel.cetak', $agendaHumas) }}" formtarget="_blank" data-print>Cetak gabungan</button>
            @if($bolehUnduh)<button class="button button-primary" type="submit" data-download @disabled(count($hambatan)>0)>Unduh bundel ZIP</button>@endif
            <span class="bp-processing" id="bp-processing" role="status" hidden><span class="bp-spinner" aria-hidden="true"></span>Menyiapkan bundel...</span>
        </div><p class="bp-status" id="bp-status" role="status" hidden></p>
    </form>
    <section class="bp-section"><h2>Riwayat pembuatan bundel</h2><ul class="bp-history">@forelse($riwayat as $r)<li><strong>{{ $r->created_at->format('d-m-Y H:i') }} WIB</strong><p class="agenda-muted">{{ $r->pengguna?->nama ?? 'Akun tidak tersedia' }} &middot; {{ $r->ringkasan['peserta'] }} peserta &middot; {{ count($r->ringkasan['lampiran']) }} lampiran &middot; {{ count($r->ringkasan['formulir']) }} rekap</p></li>@empty<li class="agenda-muted">Belum ada bundel yang dibuat.</li>@endforelse</ul>{{ $riwayat->links() }}</section>
</main>
@include('bundel-pertemuan-humas._scripts')
@endsection
