@extends('layouts.app')
@section('title', 'Evaluasi Orang Tua - NUSA')
@section('content')
<main class="uf-page uf-parent">
    @include('umpan-balik-humas._style')
    <header class="uf-head"><div><p class="eyebrow">Layanan Orang Tua / Umpan Balik</p><h1 class="page-title">{{ $formulir->judul }}</h1></div><a class="button button-muted" href="{{ route('umpan-balik-saya.index') }}">Daftar evaluasi</a></header>
    @include('agenda-humas._messages')
    <p class="agenda-text">{{ $formulir->pengantar }}</p><p class="agenda-muted">Batas pengisian {{ $formulir->selesai_pada->format('d-m-Y H:i') }} WIB</p>
    @if($sasaran->dikirim_pada)<p class="uf-note">Jawaban Anda telah terkirim pada {{ $sasaran->dikirim_pada->format('d-m-Y H:i') }} WIB dan tidak dapat diubah.</p>
    @elseif(!$formulir->menerimaJawaban())<p class="uf-note uf-note--warning">{{ $formulir->labelStatus() }}. Formulir tidak menerima jawaban saat ini.</p>
    @else<p class="uf-note">Satu respons per akun orang tua. Nama akun tidak ditampilkan dalam rekap Humas; isi jawaban tetap dapat dibaca petugas yang berwenang.</p>@endif
    @if(!$sasaran->dikirim_pada && $formulir->menerimaJawaban())
    <form method="POST" action="{{ route('umpan-balik-saya.store',$formulir) }}" data-save data-confirm="Kirim jawaban evaluasi? Jawaban yang terkirim tidak dapat diubah.">@csrf<input type="hidden" name="token_pengiriman" value="{{ old('token_pengiriman',$tokenPengiriman) }}">
        @foreach($formulir->pertanyaan as $p)<fieldset class="uf-question"><legend>{{ $p->urutan }}. {{ $p->teks }} @if($p->wajib)<small>(wajib)</small>@endif</legend>
            @if($p->jenis==='skala')<div class="uf-options">@foreach(\App\Models\PertanyaanUmpanBalikHumas::SKALA as $nilai=>$label)<label class="uf-option"><input type="radio" name="jawaban[{{ $p->id }}]" value="{{ $nilai }}" @checked((string)old('jawaban.'.$p->id,'')===(string)$nilai) @required($p->wajib)><span>{{ $nilai ? $nilai.'. ' : '' }}{{ $label }}</span></label>@endforeach</div>
            @else<label class="sr-only" for="jawaban-{{ $p->id }}">{{ $p->teks }}</label><textarea class="input" id="jawaban-{{ $p->id }}" name="jawaban[{{ $p->id }}]" maxlength="2000" @required($p->wajib)>{{ old('jawaban.'.$p->id) }}</textarea>@endif
        </fieldset>@endforeach
        <div class="actions"><button type="submit" class="button button-primary">Kirim jawaban</button></div>
    </form>
    @else<section class="uf-section"><h2>{{ $sasaran->dikirim_pada ? 'Jawaban Anda' : 'Pertanyaan evaluasi' }}</h2>@foreach($formulir->pertanyaan as $p)<article class="uf-results"><h3>{{ $p->urutan }}. {{ $p->teks }}</h3>@php($j=$jawaban->get($p->id))<p class="agenda-text">{{ $j ? ($p->jenis==='skala' ? \App\Models\PertanyaanUmpanBalikHumas::SKALA[$j->nilai] : $j->teks) : 'Tidak diisi' }}</p></article>@endforeach</section>@endif
    @if($ringkasanPublik->isNotEmpty())<section class="uf-section"><h2>Perbaikan dari sekolah</h2>@foreach($ringkasanPublik as $r)<p class="uf-note">{{ $r->ringkasan_publik }}</p>@endforeach</section>@endif
</main>
@endsection
