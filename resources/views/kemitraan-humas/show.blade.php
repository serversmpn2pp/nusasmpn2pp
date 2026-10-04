@extends('layouts.app')
@section('title', e($mitra->nama).' - Kemitraan')
@section('content')
@include('kemitraan-humas._style')
<div class="mitra-page"><div class="page-header"><div class="mitra-heading"><p class="eyebrow">Kemitraan & MoU</p><h1 class="page-title">{{ $mitra->nama }}</h1><p class="agenda-muted">{{ \App\Models\MitraHumas::JENIS[$mitra->jenis] }}</p></div><div class="actions"><a class="button button-muted" href="{{ route('kemitraan-humas.index') }}">Daftar mitra</a>@izin('kemitraan_humas.kelola')<a class="button button-muted" href="{{ route('kemitraan-humas.edit', $mitra) }}">Edit mitra</a>@endizin</div></div>
    @include('agenda-humas._messages')
    <section class="agenda-section"><div class="mitra-summary"><h2 style="margin:0">Informasi mitra</h2><span class="mitra-badge mitra-badge--{{ $mitra->status }}">{{ \App\Models\MitraHumas::STATUS[$mitra->status] }}</span></div><dl class="agenda-facts"><div><dt>Alamat</dt><dd>{{ $mitra->alamat ?: '-' }}</dd></div><div><dt>Kontak</dt><dd>{{ $mitra->nama_kontak ?: '-' }}{{ $mitra->jabatan_kontak ? ' - '.$mitra->jabatan_kontak : '' }}</dd></div><div><dt>Telepon / WhatsApp</dt><dd>{{ $mitra->nomor_kontak ?: '-' }}</dd></div><div><dt>Email</dt><dd>{{ $mitra->email ?: '-' }}</dd></div><div><dt>Website</dt><dd>@if ($mitra->website)<a href="{{ $mitra->website }}" target="_blank" rel="noopener noreferrer">{{ $mitra->website }}</a>@else - @endif</dd></div><div><dt>Catatan</dt><dd style="white-space:pre-line">{{ $mitra->catatan ?: '-' }}</dd></div></dl></section>
    <nav class="agenda-tabs" aria-label="Bagian mitra">@foreach (['mou' => 'Perjanjian & MoU', 'kegiatan' => 'Riwayat kegiatan', 'riwayat' => 'Jejak perubahan'] as $kode => $nama)<a href="{{ route('kemitraan-humas.show', [$mitra, 'tab' => $kode]) }}" @if ($tab === $kode) aria-current="page" @endif>{{ $nama }}</a>@endforeach</nav>
    @if ($tab === 'mou')
        @izin('kemitraan_humas.kelola')
            @if ($mitra->status === 'aktif')
                <div class="actions" style="margin-bottom:18px"><a class="button button-primary" href="{{ route('kemitraan-humas.mou.create', $mitra) }}">Tambah MoU</a></div>
            @endif
        @endizin
        @include('kemitraan-humas._mou-table', ['sertakanMitra' => false])<div style="margin-top:18px">{{ $mou->links() }}</div>
    @elseif ($tab === 'kegiatan')
        @if (!$bolehAgenda)<div class="alert">Akses agenda Humas belum diberikan.</div>@else
            @izin('kemitraan_humas.kelola')@if ($mitra->status === 'aktif')
                <section class="agenda-section"><h2>Kegiatan bersama mitra</h2>@izin('agenda_humas.kelola')<a class="button button-primary" href="{{ route('agenda-humas.create', ['mitra_humas_id' => $mitra->id]) }}">Buat agenda bersama</a>@endizin
                    <form method="GET" action="{{ route('kemitraan-humas.show', $mitra) }}" class="agenda-actions"><input type="hidden" name="tab" value="kegiatan"><div class="field" style="flex:1"><label for="cari_agenda">Cari agenda yang sudah ada</label><input id="cari_agenda" name="cari_agenda" class="input" value="{{ request('cari_agenda') }}" maxlength="120"></div><button type="submit" class="button button-muted">Cari agenda</button></form>
                    <form method="POST" action="{{ route('kemitraan-humas.agenda.store', $mitra) }}" class="agenda-actions" data-kemitraan-submit>@csrf<div class="field" style="flex:1"><label for="agenda_humas_id">Agenda terkait</label><select class="select" id="agenda_humas_id" name="agenda_humas_id" required><option value="">Pilih agenda</option>@foreach ($pilihanAgenda as $item)<option value="{{ $item->id }}">{{ $item->waktu_mulai->format('d-m-Y') }} - {{ $item->judul }}</option>@endforeach</select></div><button type="submit" class="button button-muted">Hubungkan agenda</button></form>
                </section>
            @endif
            @endizin
            @forelse ($agenda as $item)<article class="mitra-activity"><h3><a href="{{ route('agenda-humas.show', $item) }}">{{ $item->judul }}</a></h3><p class="agenda-muted">{{ $item->waktu_mulai->format('d-m-Y H:i') }} WIB &middot; {{ $item->tempat }}</p><span class="agenda-badge agenda-badge--{{ $item->status }}">{{ $item->labelWaktu() }}</span><div class="agenda-actions"><a class="button button-muted button-sm" href="{{ route('agenda-humas.show', $item) }}">Buka agenda</a>@izin('kemitraan_humas.kelola')<form method="POST" action="{{ route('kemitraan-humas.agenda.destroy', [$mitra, $item]) }}" onsubmit="return confirm('Lepas hubungan agenda dari mitra? Agenda tetap disimpan.')">@csrf @method('DELETE')<button type="submit" class="button button-muted button-sm">Lepas hubungan</button></form>@endizin</div></article>@empty<div class="agenda-empty">Belum ada agenda yang terhubung.</div>@endforelse
            <div style="margin-top:18px">{{ $agenda->links() }}</div>
        @endif
    @else @include('kemitraan-humas._riwayat') @endif
</div>@include('kemitraan-humas._scripts')
@endsection
