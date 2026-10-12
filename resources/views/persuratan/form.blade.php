@extends('layouts.app')
@section('title', ($surat->exists ? 'Perbarui Surat' : 'Tambah Surat').' - NUSA')
@section('content')
    @include('persuratan._style')
    <div class="page-header"><div><p class="eyebrow">Tata Usaha / Persuratan & Arsip</p><h1 class="page-title">{{ $surat->exists ? 'Perbarui surat' : ($asal ? 'Revisi surat' : 'Tambah surat') }}</h1></div><a class="button button-muted" href="{{ $surat->exists ? route('persuratan.show', $surat) : route('persuratan.index') }}">Kembali</a></div>
    @include('persuratan._messages')
    @if($asal)<div class="surat-notice">Revisi dari surat <strong>{{ $asal->nomor_surat }}</strong>. Surat asal tetap berlaku sampai revisi ini disahkan.</div>@endif
    <form method="POST" enctype="multipart/form-data" action="{{ $surat->exists ? route('persuratan.update', $surat) : route('persuratan.store') }}" class="panel surat-document surat-form" data-surat-form>
        @csrf
        @if($surat->exists) @method('PUT')<input type="hidden" name="versi" value="{{ $surat->versi }}">@endif
        @if($asal)<input type="hidden" name="revisi_dari_id" value="{{ $asal->id }}"><input type="hidden" name="versi_asal" value="{{ $asal->versi }}">@endif
        <section class="surat-section"><h2>Identitas surat</h2><div class="surat-grid">
            <div><label for="jenis">Jenis surat</label><select class="select" id="jenis" name="jenis" @disabled($surat->exists || $asal) required>@foreach(\App\Models\SuratTu::JENIS as $kode => $label)<option value="{{ $kode }}" @selected(old('jenis', $surat->jenis) === $kode)>{{ $label }}</option>@endforeach</select>@if($surat->exists || $asal)<input type="hidden" name="jenis" value="{{ $surat->jenis }}">@endif</div>
            <div><label for="sifat">Sifat surat</label><select class="select" id="sifat" name="sifat" required>@foreach(\App\Models\SuratTu::SIFAT as $kode => $label) @if($kode !== 'rahasia' || auth()->user()->memilikiIzin('surat.rahasia'))<option value="{{ $kode }}" @selected(old('sifat', $surat->sifat) === $kode)>{{ $label }}</option>@endif @endforeach</select></div>
            <div><label for="nomor_surat">Nomor surat{{ $asal ? ' revisi (nomor baru)' : '' }}</label><input class="input" id="nomor_surat" name="nomor_surat" value="{{ old('nomor_surat', $surat->nomor_surat) }}" maxlength="120" required autofocus></div>
            <div><label for="tanggal_surat">Tanggal surat</label><input class="input" type="date" id="tanggal_surat" name="tanggal_surat" value="{{ old('tanggal_surat', $surat->tanggal_surat?->format('Y-m-d')) }}" required></div>
            <div data-surat-jenis="masuk"><label for="nomor_agenda">Nomor agenda sekolah</label><input class="input" id="nomor_agenda" name="nomor_agenda" value="{{ old('nomor_agenda', $surat->nomor_agenda) }}" maxlength="80" data-wajib></div>
            <div data-surat-jenis="masuk"><label for="tanggal_diterima">Tanggal diterima</label><input class="input" type="date" id="tanggal_diterima" name="tanggal_diterima" value="{{ old('tanggal_diterima', $surat->tanggal_diterima?->format('Y-m-d')) }}" data-wajib></div>
            <div class="surat-full"><label for="perihal">Perihal</label><input class="input" id="perihal" name="perihal" value="{{ old('perihal', $surat->perihal) }}" maxlength="200" required></div>
            <div><label for="pihak" data-label-pihak>Pengirim / penerima</label><input class="input" id="pihak" name="pihak" value="{{ old('pihak', $surat->pihak) }}" maxlength="200" required></div>
            <div data-surat-jenis="keluar,tugas"><label for="pengesah_pengguna_id">Pejabat pengesah</label><select class="select" id="pengesah_pengguna_id" name="pengesah_pengguna_id" data-wajib><option value="">Pilih pengesah</option>@foreach($pengesah as $akun)<option value="{{ $akun->id }}" @selected((int) old('pengesah_pengguna_id', $surat->pengesah_pengguna_id) === $akun->id)>{{ $akun->nama }}</option>@endforeach</select></div>
            <div class="surat-full"><label for="keterangan">Keterangan</label><textarea class="textarea" id="keterangan" name="keterangan" rows="3" maxlength="5000">{{ old('keterangan', $surat->keterangan) }}</textarea></div>
        </div></section>
        <section class="surat-section" data-surat-jenis="tugas"><h2>Pelaksanaan & penerima tugas</h2><div class="surat-grid">
            <div><label for="mulai_tugas">Mulai tugas</label><input class="input" type="date" id="mulai_tugas" name="mulai_tugas" value="{{ old('mulai_tugas', $surat->mulai_tugas?->format('Y-m-d')) }}" data-wajib></div>
            <div><label for="selesai_tugas">Selesai tugas</label><input class="input" type="date" id="selesai_tugas" name="selesai_tugas" value="{{ old('selesai_tugas', $surat->selesai_tugas?->format('Y-m-d')) }}" data-wajib></div>
            <div class="surat-full"><label for="tempat_tugas">Tempat kegiatan</label><input class="input" id="tempat_tugas" name="tempat_tugas" value="{{ old('tempat_tugas', $surat->tempat_tugas) }}" maxlength="200" data-wajib></div>
            <div class="surat-full"><label for="uraian_tugas">Uraian tugas</label><textarea class="textarea" id="uraian_tugas" name="uraian_tugas" rows="4" maxlength="5000" data-wajib>{{ old('uraian_tugas', $surat->uraian_tugas) }}</textarea></div>
            <div class="surat-full"><label for="cari_pegawai">Guru / pegawai yang ditugaskan <span class="surat-muted" data-jumlah-pilihan></span></label><input class="input" id="cari_pegawai" type="search" placeholder="Cari nama guru atau pegawai" autocomplete="off">
                @php($dipilih = array_map('intval', old('pegawai_ids', $surat->penugasan->pluck('pegawai_id')->all())))
                <div class="surat-choice-list">@forelse($pegawai as $orang)<label class="surat-choice" data-pilihan-pegawai><input type="checkbox" name="pegawai_ids[]" value="{{ $orang->id }}" @checked(in_array($orang->id, $dipilih))><span><strong>{{ $orang->nama_lengkap }}</strong><small>{{ $orang->jabatan_utama ?: 'Pegawai' }}</small></span></label>@empty<div class="surat-empty">Belum ada pegawai dengan akun NUSA aktif.</div>@endforelse</div>
                <p class="surat-muted" data-pencarian-kosong hidden>Tidak ada nama yang sesuai.</p>
            </div>
        </div></section>
        <section class="surat-section"><h2>Berkas surat</h2><div class="surat-grid">
            <div><label for="berkas_utama">Berkas utama (PDF / JPG / PNG, maksimal 10 MB)</label><input class="input" type="file" id="berkas_utama" name="berkas_utama" accept=".pdf,.jpg,.jpeg,.png" @required(!$surat->exists)>@if($surat->exists)@foreach($surat->berkas->where('aktif', true)->where('jenis', 'utama') as $file)<p class="surat-muted">Tersimpan: {{ $file->nama_file_asli }}</p>@endforeach @endif</div>
            <div><label for="lampiran">Lampiran (maksimal 5 berkas, 10 MB per berkas)</label><input class="input" type="file" id="lampiran" name="lampiran[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.ppt,.pptx" multiple></div>
            @if($surat->exists && $surat->berkas->where('aktif',true)->where('jenis','lampiran')->isNotEmpty())<div class="surat-full"><label>Lepas lampiran</label>@foreach($surat->berkas->where('aktif',true)->where('jenis','lampiran') as $file)<label class="surat-choice"><input type="checkbox" name="hapus_lampiran[]" value="{{ $file->id }}">{{ $file->nama_file_asli }}</label>@endforeach</div>@endif
            @if($surat->exists)<div class="surat-full"><label for="catatan_revisi">Alasan perubahan</label><textarea class="textarea" id="catatan_revisi" name="catatan_revisi" rows="2" maxlength="2000" required>{{ old('catatan_revisi') }}</textarea></div>@endif
        </div></section>
        <div class="surat-actions surat-form-footer"><button type="submit" class="button button-primary">{{ $surat->exists ? 'Simpan perubahan' : 'Simpan surat' }}</button><a href="{{ route('persuratan.index') }}" class="button button-muted">Batal</a><span role="status" data-surat-progress hidden>Sedang mengunggah dan menyimpan surat...</span></div>
    </form>
    @include('persuratan._scripts')
@endsection
