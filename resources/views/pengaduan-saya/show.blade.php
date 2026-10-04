@extends('layouts.app')
@section('title', 'Laporan '.$tiket->nomor.' - NUSA')
@section('content')
@include('pengaduan-humas._style')
<div class="publikasi-page pengaduan-page">
    <div class="page-header"><div style="min-width:0"><p class="eyebrow">{{ $tiket->nomor }}</p><h1 class="page-title">{{ $tiket->judul }}</h1><span class="pengaduan-status pengaduan-status--{{ $tiket->status }}">{{ \App\Models\PengaduanHumas::STATUS_ORANG_TUA[$tiket->status] }}</span></div><a class="button button-muted" href="{{ route('pengaduan-saya.index') }}">Laporan saya</a></div>
    @include('agenda-humas._messages')
    <section class="agenda-section"><h2>Laporan Anda</h2><dl class="agenda-facts"><div><dt>Dikirim</dt><dd>{{ $tiket->created_at->format('d-m-Y H:i') }}</dd></div><div><dt>Kategori</dt><dd>{{ \App\Models\PengaduanHumas::KATEGORI[$tiket->kategori] }}</dd></div><div><dt>Jenis</dt><dd>{{ \App\Models\PengaduanHumas::JENIS[$tiket->jenis] }}</dd></div><div><dt>Identitas pelapor</dt><dd>Privat Admin / Humas{{ $tiket->rahasiakan_identitas ? ' (diminta dirahasiakan)' : '' }}</dd></div></dl><div class="agenda-text">{{ $tiket->isi }}</div></section>
    @if ($lampiran->isNotEmpty())<section class="agenda-section"><h2>Lampiran yang Anda kirim</h2>@foreach ($lampiran as $file)<div class="pengaduan-attachment"><strong>{{ $file->nama_file_asli }}</strong><a href="{{ route('pengaduan-saya.lampiran', [$tiket->id, $file->id]) }}" target="_blank" rel="noopener">Buka</a><a href="{{ route('pengaduan-saya.lampiran', [$tiket->id, $file->id, 'unduh' => 1]) }}">Unduh</a></div>@endforeach</section>@endif
    <section class="agenda-section"><h2>Balasan & informasi tambahan</h2>@include('pengaduan-humas._pesan', ['untukOrangTua' => true])</section>
    @if ($tiket->aktif())
        <section class="agenda-section"><h2>Kirim informasi tambahan</h2><form method="POST" action="{{ route('pengaduan-saya.informasi', $tiket->id) }}" data-publikasi-submit>@csrf<input type="hidden" name="versi" value="{{ $tiket->versi }}"><input type="hidden" name="token_pengiriman" value="{{ old('token_pengiriman', $tokenPengiriman) }}"><div class="field"><label for="isi_pesan">Informasi untuk Humas</label><textarea class="textarea" id="isi_pesan" name="isi_pesan" rows="4" required minlength="5" maxlength="3000">{{ old('isi_pesan') }}</textarea></div><div class="agenda-actions"><button class="button button-primary" type="submit">Kirim informasi</button></div></form></section>
    @else
        <p class="agenda-muted">Laporan sudah selesai atau ditutup. Hubungi Humas bila perlu dibuka kembali.</p>
    @endif
</div>
@include('publikasi-humas._scripts')
@endsection
