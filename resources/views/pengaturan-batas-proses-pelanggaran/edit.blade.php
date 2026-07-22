@extends('layouts.app')

@section('title', 'Atur Batas Proses Pelanggaran - NUSA')

@section('content')
    <style>
        .deadline-form-grid { display:grid; gap:16px; grid-template-columns:repeat(3,minmax(0,1fr)); }
        .deadline-toggle-list { display:grid; gap:10px; margin-top:16px; }
        .deadline-toggle { align-items:start; border:1px solid var(--line); border-radius:8px; display:grid; gap:10px; grid-template-columns:20px minmax(0,1fr); padding:13px; }
        .deadline-toggle input { margin-top:3px; }
        @media(max-width:760px){.deadline-form-grid{grid-template-columns:1fr}}
    </style>

    <div class="page-header"><div><p class="eyebrow">Kesiswaan & BK</p><h1 class="page-title">Atur batas proses</h1><p class="page-subtitle">Tahun pelajaran {{ $tahunPelajaran->nama }}</p></div><a href="{{ route('pengaturan-batas-proses-pelanggaran.index') }}" class="button button-muted">Kembali</a></div>

    @if($errors->any())<div class="alert alert-danger"><strong>Ada data yang perlu diperbaiki.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ route('pengaturan-batas-proses-pelanggaran.update',$tahunPelajaran) }}">@csrf @method('PUT')
        <section class="panel panel-pad">
            <h2 class="panel-title">Tenggat tiap tahap</h2><p class="help-text">Gunakan hari kalender. Nilai yang diperbolehkan 1 sampai 30 hari.</p>
            <div class="deadline-form-grid" style="margin-top:16px">
                <div class="field"><label for="batas_hari_pemeriksaan_bk">Pemeriksaan BK</label><input id="batas_hari_pemeriksaan_bk" name="batas_hari_pemeriksaan_bk" type="number" min="1" max="30" class="input" value="{{ old('batas_hari_pemeriksaan_bk',$pengaturan->batas_hari_pemeriksaan_bk) }}" required><p class="help-text">Sejak laporan dibuat atau diklarifikasi.</p></div>
                <div class="field"><label for="batas_hari_persetujuan">Persetujuan guru</label><input id="batas_hari_persetujuan" name="batas_hari_persetujuan" type="number" min="1" max="30" class="input" value="{{ old('batas_hari_persetujuan',$pengaturan->batas_hari_persetujuan) }}" required><p class="help-text">Sejak BK menyatakan terbukti.</p></div>
                <div class="field"><label for="batas_hari_musyawarah">Musyawarah/pengganti</label><input id="batas_hari_musyawarah" name="batas_hari_musyawarah" type="number" min="1" max="30" class="input" value="{{ old('batas_hari_musyawarah',$pengaturan->batas_hari_musyawarah) }}" required><p class="help-text">Sejak konflik atau kebutuhan pengganti muncul.</p></div>
            </div>
        </section>

        <section class="panel panel-pad" style="margin-top:18px">
            <h2 class="panel-title">Pengingat otomatis</h2>
            <div class="field" style="margin-top:16px;max-width:360px"><label for="pengingat_hari_sebelum_batas">Kirim pengingat sebelum batas</label><input id="pengingat_hari_sebelum_batas" name="pengingat_hari_sebelum_batas" type="number" min="0" max="29" class="input" value="{{ old('pengingat_hari_sebelum_batas',$pengaturan->pengingat_hari_sebelum_batas) }}" required><p class="help-text">Isi 0 untuk mengingatkan pada hari jatuh tempo.</p></div>
            <div class="deadline-toggle-list">
                <label class="deadline-toggle"><input type="checkbox" name="notifikasi_pengingat_aktif" value="1" @checked(old('notifikasi_pengingat_aktif',$pengaturan->notifikasi_pengingat_aktif))><span><strong>Aktifkan pengingat sebelum jatuh tempo</strong><span class="help-text">Notifikasi dikirim satu kali kepada petugas pada tahap tersebut.</span></span></label>
                <label class="deadline-toggle"><input type="checkbox" name="notifikasi_terlambat_aktif" value="1" @checked(old('notifikasi_terlambat_aktif',$pengaturan->notifikasi_terlambat_aktif))><span><strong>Aktifkan pemberitahuan keterlambatan</strong><span class="help-text">Notifikasi dikirim satu kali ketika tenggat terlewati.</span></span></label>
            </div>
        </section>

        <div class="form-actions"><a href="{{ route('pengaturan-batas-proses-pelanggaran.index') }}" class="button button-muted">Batal</a><button class="button button-primary">Simpan pengaturan</button></div>
    </form>
@endsection
