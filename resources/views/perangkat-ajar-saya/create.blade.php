@extends('layouts.app')

@section('title', 'Unggah Perangkat Ajar - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Kurikulum</p>
            <h1 class="page-title">Unggah perangkat ajar</h1>
        </div>

        <a href="{{ route('perangkat-ajar-saya.index', ['tahun_pelajaran_id' => $tahunPelajaranId, 'semester' => $semester]) }}" class="button button-muted">Kembali</a>
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

    <form action="{{ route('perangkat-ajar-saya.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="form-shell">
            <aside class="panel panel-pad">
                <h2 class="panel-title">Ketentuan file</h2>
                <p class="help-text">Unggah dokumen dalam format PDF dengan ukuran maksimal 10 MB. File disimpan privat dan hanya dapat dibuka melalui akun NUSA yang berwenang.</p>

                <dl class="quick-facts" style="margin-top: 18px;">
                    <div>
                        <dt>Tahun pelajaran</dt>
                        <dd>{{ $tahunPelajaran->firstWhere('id', $tahunPelajaranId)?->nama ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt>Semester</dt>
                        <dd>{{ $semester }}</dd>
                    </div>
                </dl>
            </aside>

            <div class="section-stack">
                <section class="panel panel-pad">
                    <h2 class="panel-title">Informasi Dokumen</h2>
                    <div class="form-grid">
                        <input type="hidden" name="tahun_pelajaran_id" value="{{ $tahunPelajaranId }}">
                        <input type="hidden" name="semester" value="{{ $semester }}">

                        <div class="field">
                            <label for="mata_pelajaran_id">Mata pelajaran</label>
                            <select id="mata_pelajaran_id" name="mata_pelajaran_id" class="select @error('mata_pelajaran_id') is-invalid @enderror" required>
                                <option value="">Pilih mata pelajaran</option>
                                @foreach ($mataPelajaran as $item)
                                    <option value="{{ $item->id }}" @selected((string) old('mata_pelajaran_id', $mataPelajaranId) === (string) $item->id)>
                                        {{ $item->nama }}
                                    </option>
                                @endforeach
                            </select>
                            @error('mata_pelajaran_id')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <label for="jenis_perangkat_ajar_id">Jenis perangkat</label>
                            <select id="jenis_perangkat_ajar_id" name="jenis_perangkat_ajar_id" class="select @error('jenis_perangkat_ajar_id') is-invalid @enderror" required>
                                <option value="">Pilih jenis perangkat</option>
                                @foreach ($jenisPerangkatAjar as $item)
                                    <option value="{{ $item->id }}" @selected((string) old('jenis_perangkat_ajar_id', $jenisPerangkatAjarId) === (string) $item->id)>
                                        {{ $item->nama }}{{ $item->wajib ? ' - wajib' : ' - opsional' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('jenis_perangkat_ajar_id')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field span-2">
                            <label for="judul">Judul dokumen</label>
                            <input id="judul" name="judul" type="text" value="{{ old('judul') }}" placeholder="Contoh: Modul Ajar Informatika Semester 1" class="input @error('judul') is-invalid @enderror" required autofocus>
                            @error('judul')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field span-2">
                            <label for="file_pdf">File PDF</label>
                            <input id="file_pdf" name="file_pdf" type="file" accept="application/pdf" class="file-input @error('file_pdf') is-invalid @enderror" required>
                            <p class="help-text">PDF maksimal 10 MB.</p>
                            @error('file_pdf')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field span-2">
                            <label for="catatan_guru">Catatan guru</label>
                            <textarea id="catatan_guru" name="catatan_guru" class="textarea @error('catatan_guru') is-invalid @enderror" placeholder="Opsional">{{ old('catatan_guru') }}</textarea>
                            @error('catatan_guru')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                <div class="form-actions">
                    <a href="{{ route('perangkat-ajar-saya.index', ['tahun_pelajaran_id' => $tahunPelajaranId, 'semester' => $semester]) }}" class="button button-muted">Batal</a>
                    <button type="submit" class="button button-primary">Unggah PDF</button>
                </div>
            </div>
        </div>
    </form>
@endsection
