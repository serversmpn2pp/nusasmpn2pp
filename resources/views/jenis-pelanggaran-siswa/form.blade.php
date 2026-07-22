@extends('layouts.app')
@section('title', $judul . ' - NUSA')
@section('content')
    <div class="page-header"><div><p class="eyebrow">Pengaturan Pembinaan</p><h1 class="page-title">{{ $judul }}</h1></div></div>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ $aksi }}" class="panel panel-pad" style="max-width:900px">@csrf @if($metode==='PUT')@method('PUT')@endif
        <div class="form-grid">
            <div class="field"><label for="kode">Kode</label><input id="kode" name="kode" value="{{ old('kode',$jenisPelanggaranSiswa->kode) }}" class="input" required></div>
            <div class="field"><label for="kategori">Kategori pembinaan</label><select id="kategori" name="kategori_pembinaan_siswa_id" class="select"><option value="">Tanpa kategori</option>@foreach($daftarKategori as $kategori)<option value="{{ $kategori->id }}" @selected((string)old('kategori_pembinaan_siswa_id',$jenisPelanggaranSiswa->kategori_pembinaan_siswa_id)===(string)$kategori->id)>{{ $kategori->nama }}</option>@endforeach</select></div>
            <div class="field span-2"><label for="nama">Jenis pelanggaran</label><textarea id="nama" name="nama" class="textarea" required>{{ old('nama',$jenisPelanggaranSiswa->nama) }}</textarea></div>
            <div class="field"><label for="tingkat">Tingkat</label><select id="tingkat" name="tingkat" class="select" required>@foreach($daftarTingkat as $kode=>$label)<option value="{{ $kode }}" @selected(old('tingkat',$jenisPelanggaranSiswa->tingkat)===$kode)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="poin">Poin</label><input id="poin" name="poin" type="number" min="1" value="{{ old('poin',$jenisPelanggaranSiswa->poin) }}" class="input" required></div>
            <div class="field"><label for="urutan">Urutan</label><input id="urutan" name="urutan" type="number" min="0" value="{{ old('urutan',$jenisPelanggaranSiswa->urutan ?? 0) }}" class="input"></div>
            <div class="field"><label for="aktif">Status</label><select id="aktif" name="aktif" class="select"><option value="1" @selected((string)old('aktif',(int)$jenisPelanggaranSiswa->aktif)==='1')>Aktif</option><option value="0" @selected((string)old('aktif',(int)$jenisPelanggaranSiswa->aktif)==='0')>Nonaktif</option></select></div>
        </div>
        <div class="form-actions"><a href="{{ route('jenis-pelanggaran-siswa.index') }}" class="button button-muted">Batal</a><button class="button button-primary">Simpan</button></div>
    </form>
@endsection
