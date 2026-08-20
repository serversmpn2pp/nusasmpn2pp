@extends('layouts.app')

@section('title', 'Periksa Perangkat Ajar - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Kurikulum</p>
            <h1 class="page-title">Periksa perangkat ajar</h1>
        </div>

        <a href="{{ route('pemeriksaan-perangkat-ajar.show', ['pegawai' => $perangkatAjar->pegawai_id, 'tahun_pelajaran_id' => $perangkatAjar->tahun_pelajaran_id, 'semester' => $perangkatAjar->semester]) }}" class="button button-muted">Kembali</a>
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

    <form action="{{ route('pemeriksaan-perangkat-ajar.update', $perangkatAjar) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="form-shell">
            <aside class="panel panel-pad">
                <h2 class="panel-title">Dokumen guru</h2>
                <dl class="quick-facts" style="margin-top: 18px;">
                    <div>
                        <dt>Guru</dt>
                        <dd>{{ $perangkatAjar->pegawai?->nama_lengkap ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt>Mata pelajaran</dt>
                        <dd>{{ $perangkatAjar->mataPelajaran?->nama ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt>Jenis</dt>
                        <dd>{{ $perangkatAjar->jenisPerangkatAjar?->nama ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt>Tingkat</dt>
                        <dd>{{ $perangkatAjar->tingkatTampil() }}</dd>
                    </div>
                    <div>
                        <dt>Status saat ini</dt>
                        <dd>{{ $perangkatAjar->labelStatus() }}</dd>
                    </div>
                </dl>

                <a href="{{ route('perangkat-ajar-saya.download', $perangkatAjar) }}" class="button button-primary button-full" style="margin-top: 18px;">Unduh PDF</a>
            </aside>

            <div class="section-stack">
                <section class="panel panel-pad">
                    <h2 class="panel-title">Hasil Pemeriksaan</h2>
                    <div class="form-grid">
                        <div class="field">
                            <label for="status">Keputusan</label>
                            <select id="status" name="status" class="select @error('status') is-invalid @enderror" required autofocus>
                                <option value="">Pilih keputusan</option>
                                <option value="sudah_diperiksa" @selected(old('status', $perangkatAjar->status) === 'sudah_diperiksa')>Sudah diperiksa</option>
                                <option value="perlu_perbaikan" @selected(old('status', $perangkatAjar->status) === 'perlu_perbaikan')>Perlu perbaikan</option>
                            </select>
                            @error('status')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field span-2">
                            <label for="catatan_pemeriksa">Catatan pemeriksa</label>
                            <textarea id="catatan_pemeriksa" name="catatan_pemeriksa" class="textarea @error('catatan_pemeriksa') is-invalid @enderror" placeholder="Tuliskan catatan pemeriksaan. Wajib diisi jika dokumen perlu diperbaiki.">{{ old('catatan_pemeriksa', $perangkatAjar->catatan_pemeriksa) }}</textarea>
                            @error('catatan_pemeriksa')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                <div class="form-actions">
                    <a href="{{ route('pemeriksaan-perangkat-ajar.show', ['pegawai' => $perangkatAjar->pegawai_id, 'tahun_pelajaran_id' => $perangkatAjar->tahun_pelajaran_id, 'semester' => $perangkatAjar->semester]) }}" class="button button-muted">Batal</a>
                    <button type="submit" class="button button-primary">Simpan pemeriksaan</button>
                </div>
            </div>
        </div>
    </form>
@endsection
