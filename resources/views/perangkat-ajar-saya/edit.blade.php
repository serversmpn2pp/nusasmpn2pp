@extends('layouts.app')

@section('title', 'Revisi Perangkat Ajar - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Kurikulum</p>
            <h1 class="page-title">Revisi perangkat ajar</h1>
        </div>

        <a href="{{ route('perangkat-ajar-saya.show', $perangkatAjar) }}" class="button button-muted">Kembali</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Ada data yang perlu diperbaiki.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('perangkat-ajar-saya.update', $perangkatAjar) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="form-shell">
            <aside class="panel panel-pad">
                <h2 class="panel-title">Dokumen saat ini</h2>
                <dl class="quick-facts" style="margin-top: 18px;">
                    <div>
                        <dt>Mata pelajaran</dt>
                        <dd>{{ $perangkatAjar->mataPelajaran?->nama ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt>Jenis</dt>
                        <dd>{{ $perangkatAjar->jenisPerangkatAjar?->nama ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt>Semester</dt>
                        <dd>{{ $perangkatAjar->semester }}</dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd>{{ $perangkatAjar->labelStatus() }}</dd>
                    </div>
                </dl>

                @if ($perangkatAjar->catatan_pemeriksa)
                    <div class="alert alert-danger" style="margin-top: 18px; margin-bottom: 0;">
                        {{ $perangkatAjar->catatan_pemeriksa }}
                    </div>
                @endif
            </aside>

            <div class="section-stack">
                <section class="panel panel-pad">
                    <h2 class="panel-title">Perbarui Dokumen</h2>
                    <div class="form-grid">
                        <div class="field span-2">
                            <label for="judul">Judul dokumen</label>
                            <input id="judul" name="judul" type="text" value="{{ old('judul', $perangkatAjar->judul) }}" class="input @error('judul') is-invalid @enderror" required autofocus>
                            @error('judul')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field span-2">
                            <label for="file_pdf">Unggah revisi PDF</label>
                            <input id="file_pdf" name="file_pdf" type="file" accept="application/pdf" class="file-input @error('file_pdf') is-invalid @enderror">
                            <p class="help-text">Kosongkan jika hanya memperbarui judul atau catatan. Jika PDF baru diunggah, status kembali menjadi Menunggu pemeriksaan.</p>
                            @error('file_pdf')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field span-2">
                            <label for="catatan_guru">Catatan guru</label>
                            <textarea id="catatan_guru" name="catatan_guru" class="textarea @error('catatan_guru') is-invalid @enderror">{{ old('catatan_guru', $perangkatAjar->catatan_guru) }}</textarea>
                            @error('catatan_guru')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                <div class="form-actions">
                    <a href="{{ route('perangkat-ajar-saya.show', $perangkatAjar) }}" class="button button-muted">Batal</a>
                    <button type="submit" class="button button-primary">Simpan perubahan</button>
                </div>
            </div>
        </div>
    </form>
@endsection
