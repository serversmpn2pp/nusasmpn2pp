@extends('layouts.app')

@php($sedangEdit = $dokumenHumas->exists)

@section('title', ($sedangEdit ? 'Perbarui' : 'Tambah').' Dokumen Humas - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Humas / Pusat Dokumen</p>
            <h1 class="page-title">{{ $sedangEdit ? 'Perbarui dokumen' : 'Tambah dokumen' }}</h1>
        </div>
        <a href="{{ $sedangEdit ? route('dokumen-humas.show', $dokumenHumas) : route('dokumen-humas.index') }}" class="button button-muted">Kembali</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Ada data yang perlu diperbaiki.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form action="{{ $sedangEdit ? route('dokumen-humas.update', $dokumenHumas) : route('dokumen-humas.store') }}" method="POST" enctype="multipart/form-data" @if (isset($mouTerkait)) data-mou-upload @endif>
        @csrf
        @if (isset($mouTerkait))
            <input type="hidden" name="kerja_sama_humas_id" value="{{ $mouTerkait->id }}">
            <input type="hidden" name="token_unggahan_mou" value="{{ old('token_unggahan_mou', $tokenUnggahanMou) }}">
            <p class="help-text">MoU: {{ $mouTerkait->judul }} &middot; {{ $mouTerkait->mitra->nama }}</p>
        @endif
        @if (isset($agendaTerkait))
            <input type="hidden" name="agenda_humas_id" value="{{ $agendaTerkait->id }}">
            <p class="help-text">Agenda: {{ $agendaTerkait->judul }}</p>
        @endif
        @if ($sedangEdit) @method('PUT') @endif
        <div class="form-shell">
            <aside class="panel panel-pad">
                <h2 class="panel-title">Berkas privat</h2>
                <p class="help-text">Berkas hanya dapat dibuka oleh pengguna yang memiliki izin dokumen Humas. Format PDF, Office, atau gambar; maksimal {{ $batasBerkasMb }} MB.</p>
                @if ($sedangEdit)
                    <dl class="quick-facts" style="margin-top:18px;">
                        <div><dt>Berkas saat ini</dt><dd>{{ $dokumenHumas->nama_file_asli }}</dd></div>
                        <div><dt>Ukuran</dt><dd>{{ $dokumenHumas->ukuranFileTampil() }}</dd></div>
                    </dl>
                    <p class="help-text">Berkas lama tetap tersimpan sebagai riwayat saat Anda mengunggah revisi.</p>
                @endif
            </aside>

            <div class="section-stack">
                <section class="panel panel-pad">
                    <h2 class="panel-title">Informasi dokumen</h2>
                    <div class="form-grid">
                        <div class="field">
                            <label for="kategori">Kategori</label>
                            <select id="kategori" name="kategori" class="select @error('kategori') is-invalid @enderror" required>
                                @foreach ($kategori as $kode => $label)
                                    <option value="{{ $kode }}" @selected(old('kategori', $dokumenHumas->kategori ?: 'lainnya') === $kode)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('kategori')<p class="error-text">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="nomor_dokumen">Nomor dokumen</label>
                            <input id="nomor_dokumen" name="nomor_dokumen" class="input @error('nomor_dokumen') is-invalid @enderror" value="{{ old('nomor_dokumen', $dokumenHumas->nomor_dokumen) }}" maxlength="120" placeholder="Opsional">
                            @error('nomor_dokumen')<p class="error-text">{{ $message }}</p>@enderror
                        </div>
                        <div class="field span-2">
                            <label for="judul">Judul</label>
                            <input id="judul" name="judul" class="input @error('judul') is-invalid @enderror" value="{{ old('judul', $dokumenHumas->judul) }}" maxlength="180" required autofocus>
                            @error('judul')<p class="error-text">{{ $message }}</p>@enderror
                        </div>
                        <div class="field span-2">
                            <label for="deskripsi">Keterangan</label>
                            <textarea id="deskripsi" name="deskripsi" class="textarea @error('deskripsi') is-invalid @enderror" maxlength="5000" placeholder="Ringkasan isi, pihak terkait, atau lokasi kegiatan">{{ old('deskripsi', $dokumenHumas->deskripsi) }}</textarea>
                            @error('deskripsi')<p class="error-text">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="berlaku_mulai">Berlaku mulai</label>
                            <input id="berlaku_mulai" name="berlaku_mulai" type="date" class="input @error('berlaku_mulai') is-invalid @enderror" value="{{ old('berlaku_mulai', $dokumenHumas->berlaku_mulai?->format('Y-m-d')) }}">
                            @error('berlaku_mulai')<p class="error-text">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="berlaku_sampai">Berlaku sampai</label>
                            <input id="berlaku_sampai" name="berlaku_sampai" type="date" class="input @error('berlaku_sampai') is-invalid @enderror" value="{{ old('berlaku_sampai', $dokumenHumas->berlaku_sampai?->format('Y-m-d')) }}">
                            @error('berlaku_sampai')<p class="error-text">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="ingatkan_hari_sebelum">Pengingat sebelum berakhir</label>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <input id="ingatkan_hari_sebelum" name="ingatkan_hari_sebelum" type="number" min="0" max="365" class="input @error('ingatkan_hari_sebelum') is-invalid @enderror" value="{{ old('ingatkan_hari_sebelum', $dokumenHumas->ingatkan_hari_sebelum ?? 30) }}">
                                <span>hari</span>
                            </div>
                            @error('ingatkan_hari_sebelum')<p class="error-text">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="berkas">{{ $sedangEdit ? 'Unggah revisi baru (opsional)' : 'Berkas dokumen' }}</label>
                            <input id="berkas" name="berkas" type="file" class="file-input @error('berkas') is-invalid @enderror" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp" @required(! $sedangEdit)>
                            @error('berkas')<p class="error-text">{{ $message }}</p>@enderror
                        </div>
                        @if ($sedangEdit)
                            <div class="field span-2">
                                <label for="catatan_revisi">Catatan revisi</label>
                                <textarea id="catatan_revisi" name="catatan_revisi" class="textarea @error('catatan_revisi') is-invalid @enderror" maxlength="1000" placeholder="Wajib diisi bila mengunggah berkas revisi">{{ old('catatan_revisi') }}</textarea>
                                <p class="help-text">Catatan ini membantu petugas memahami perubahan pada berkas terbaru.</p>
                                @error('catatan_revisi')<p class="error-text">{{ $message }}</p>@enderror
                            </div>
                        @endif
                    </div>
                </section>
                <div class="form-actions">
                    @if (isset($mouTerkait))<div data-mou-upload-state hidden role="status" aria-live="polite" style="flex-basis:100%;padding:16px;border:1px solid #a1bfd0;background:#eef6fa;border-radius:6px"><strong data-mou-upload-label></strong><progress max="100" value="0" style="width:100%;display:block;margin-top:10px" aria-label="Kemajuan unggahan MoU"></progress></div>@endif
                    <a href="{{ $sedangEdit ? route('dokumen-humas.show', $dokumenHumas) : route('dokumen-humas.index') }}" class="button button-muted">Batal</a>
                    <button type="submit" class="button button-primary">{{ $sedangEdit ? 'Simpan perubahan' : 'Simpan dokumen' }}</button>
                </div>
            </div>
        </div>
    </form>
    @if (isset($mouTerkait))@include('kemitraan-humas._upload-script')@endif
@endsection
