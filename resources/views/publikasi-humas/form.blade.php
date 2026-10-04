@extends('layouts.app')
@section('title', ($publikasi->exists ? 'Edit draf' : 'Buat draf').' - Publikasi Humas')
@section('content')
@include('aset-promosi-humas._style')
<div class="publikasi-page" style="max-width:1000px">
    <div class="page-header"><div><p class="eyebrow">Humas / Publikasi</p><h1 class="page-title">{{ $publikasi->exists ? 'Edit draf publikasi' : 'Buat draf publikasi' }}</h1></div><a class="button button-muted" href="{{ $publikasi->exists ? route('publikasi-humas.show', $publikasi) : route('publikasi-humas.index') }}">Kembali</a></div>
    @include('agenda-humas._messages')
    @if ($publikasi->status === 'revisi' && $publikasi->catatan_pemeriksaan)<div class="publikasi-review"><strong>Catatan pimpinan</strong><div class="agenda-text">{{ $publikasi->catatan_pemeriksaan }}</div></div>@endif
    <form method="POST" enctype="multipart/form-data" action="{{ $publikasi->exists ? route('publikasi-humas.update', $publikasi) : route('publikasi-humas.store') }}" data-publikasi-upload>
        @csrf
        @if ($publikasi->exists)
            @method('PUT')<input type="hidden" name="versi" value="{{ old('versi', $publikasi->versi) }}">
        @else
            <input type="hidden" name="token_pembuatan" value="{{ old('token_pembuatan', $tokenPembuatan) }}">
        @endif
        <section class="agenda-section"><h2>Naskah konten</h2><div class="agenda-field-grid">
            <div class="field span-2"><label for="judul">Judul</label><input class="input" id="judul" name="judul" value="{{ old('judul', $publikasi->judul) }}" maxlength="180" required></div>
            <div class="field"><label for="jenis">Jenis konten</label><select class="select" id="jenis" name="jenis" required>@foreach (\App\Models\PublikasiHumas::JENIS as $kode => $label)<option value="{{ $kode }}" @selected(old('jenis', $publikasi->jenis) === $kode)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="kanal">Media tujuan</label><select class="select" id="kanal" name="kanal" required>@foreach (\App\Models\PublikasiHumas::KANAL as $kode => $label)<option value="{{ $kode }}" @selected(old('kanal', $publikasi->kanal) === $kode)>{{ $label }}</option>@endforeach</select></div>
            <div class="field span-2"><label for="ringkasan">Ringkasan (opsional)</label><textarea class="textarea" id="ringkasan" name="ringkasan" rows="2" maxlength="500">{{ old('ringkasan', $publikasi->ringkasan) }}</textarea></div>
            <div class="field span-2"><label for="isi">Isi berita / pengumuman</label><textarea class="textarea" id="isi" name="isi" rows="12" minlength="10" maxlength="20000" required>{{ old('isi', $publikasi->isi) }}</textarea></div>
            <div class="field"><label for="rencana_tayang">Rencana tanggal tayang (opsional)</label><input class="input" id="rencana_tayang" name="rencana_tayang" type="date" value="{{ old('rencana_tayang', $publikasi->rencana_tayang?->format('Y-m-d')) }}"></div>
            @if ($bolehAgenda)<div class="field"><label for="agenda_humas_id">Agenda terkait (opsional)</label><select class="select" id="agenda_humas_id" name="agenda_humas_id"><option value="">Tidak terkait agenda</option>@foreach ($agenda as $item)<option value="{{ $item->id }}" @selected(old('agenda_humas_id', $publikasi->agenda_humas_id) == $item->id)>{{ $item->waktu_mulai->format('d-m-Y') }} - {{ $item->judul }}</option>@endforeach</select></div>@endif
        </div></section>
        <section class="agenda-section"><h2>Foto konten</h2>
            @if ($foto->isNotEmpty())<div class="publikasi-photos">@foreach ($foto as $item)<figure class="publikasi-photo"><img src="{{ route('publikasi-humas.berkas', [$publikasi, $item]) }}" alt="Foto konten {{ $loop->iteration }}"><figcaption>{{ $item->nama_file_asli }}</figcaption><label><input type="checkbox" name="hapus_foto[]" value="{{ $item->id }}" @checked(in_array($item->id, old('hapus_foto', [])))>Hapus dari draf</label></figure>@endforeach</div>@endif
            <div class="field" style="margin-top:18px"><label for="foto">Tambah foto (maksimal 5 foto, masing-masing 2 MB)</label><input class="file-input" id="foto" name="foto[]" type="file" accept=".jpg,.jpeg,.png,.webp" multiple><div class="publikasi-photos" data-foto-preview style="margin-top:16px"></div></div>
        </section>
        @include('publikasi-humas._aset-form')
        @include('publikasi-humas._upload-state')
        <div class="agenda-actions"><button class="button button-primary" type="submit">Simpan draf</button><a class="button button-muted" href="{{ $publikasi->exists ? route('publikasi-humas.show', $publikasi) : route('publikasi-humas.index') }}">Batal</a></div>
    </form>
    @if ($bolehAgenda)<form method="GET" action="{{ url()->current() }}" style="margin-top:28px"><div class="agenda-actions"><div class="field" style="flex:1"><label for="cari_agenda">Cari agenda lain</label><input class="input" id="cari_agenda" name="cari_agenda" maxlength="120" value="{{ request('cari_agenda') }}"></div><button type="submit" class="button button-muted">Cari agenda</button></div></form>@endif
</div>
@include('publikasi-humas._scripts')
@include('publikasi-humas._aset-scripts')
@endsection
