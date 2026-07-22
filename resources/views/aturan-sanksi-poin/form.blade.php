@extends('layouts.app')
@section('title',$judul.' - NUSA')
@section('content')
    <div class="page-header"><div><p class="eyebrow">Pengaturan Pembinaan</p><h1 class="page-title">{{ $judul }}</h1></div></div>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ $aksi }}" class="panel panel-pad" style="max-width:820px">@csrf @if($metode==='PUT')@method('PUT')@endif
        <div class="form-grid"><div class="field"><label for="batas_poin">Batas poin</label><input id="batas_poin" name="batas_poin" type="number" min="1" value="{{ old('batas_poin',$aturanSanksiPoin->batas_poin) }}" class="input" required></div><div class="field"><label for="nama">Nama sanksi</label><input id="nama" name="nama" value="{{ old('nama',$aturanSanksiPoin->nama) }}" class="input" required></div><div class="field span-2"><label for="deskripsi">Deskripsi</label><textarea id="deskripsi" name="deskripsi" class="textarea" required>{{ old('deskripsi',$aturanSanksiPoin->deskripsi) }}</textarea></div><div class="field"><label for="urutan">Urutan</label><input id="urutan" name="urutan" type="number" min="0" value="{{ old('urutan',$aturanSanksiPoin->urutan??0) }}" class="input"></div><div class="field"><label for="aktif">Status</label><select id="aktif" name="aktif" class="select"><option value="1" @selected((string)old('aktif',(int)$aturanSanksiPoin->aktif)==='1')>Aktif</option><option value="0" @selected((string)old('aktif',(int)$aturanSanksiPoin->aktif)==='0')>Nonaktif</option></select></div></div>
        <div class="form-actions"><a href="{{ route('aturan-sanksi-poin.index') }}" class="button button-muted">Batal</a><button class="button button-primary">Simpan</button></div>
    </form>
@endsection
