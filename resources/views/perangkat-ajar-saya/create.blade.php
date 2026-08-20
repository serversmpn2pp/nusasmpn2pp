@extends('layouts.app')

@section('title', 'Unggah Perangkat Ajar - NUSA')

@section('content')
    @php
        $mataPelajaranTerpilih = (int) old('mata_pelajaran_id', $mataPelajaranId);
        $tingkatTerpilih = (int) old('tingkat', $tingkat);
        $daftarTingkatAwal = $tingkatPerMataPelajaran->get($mataPelajaranTerpilih, collect());
        $labelTingkat = fn (int $nilai) => match ($nilai) {
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            default => (string) $nilai,
        };
    @endphp

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

    <form action="{{ route('perangkat-ajar-saya.store') }}" method="POST" enctype="multipart/form-data" data-perangkat-ajar-form>
        @csrf

        <div class="form-shell">
            <aside class="panel panel-pad">
                <h2 class="panel-title">Ketentuan file</h2>
                <p class="help-text">Unggah dokumen dalam format PDF dengan ukuran maksimal 10 MB. File disimpan privat dan hanya dapat dibuka melalui akun NUSA yang berwenang.</p>
                @if ($batasUnggahPdf['dibatasi_server'])
                    <div class="alert alert-danger" style="margin-top: 14px; margin-bottom: 0;">
                        Konfigurasi PHP server saat ini hanya dapat menerima PDF sampai {{ $batasUnggahPdf['label'] }}. Hubungi administrator untuk menaikkan batas unggah server.
                    </div>
                @endif

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
                            <label for="tingkat">Tingkat</label>
                            <select id="tingkat" name="tingkat" class="select @error('tingkat') is-invalid @enderror" required>
                                <option value="">Pilih tingkat</option>
                                @foreach ($daftarTingkatAwal as $nilaiTingkat)
                                    <option value="{{ $nilaiTingkat }}" @selected($tingkatTerpilih === (int) $nilaiTingkat)>
                                        Tingkat {{ $labelTingkat((int) $nilaiTingkat) }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="help-text">Hanya tingkat dari kelas yang Anda ajar.</p>
                            @error('tingkat')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field span-2">
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
                            <input id="file_pdf" name="file_pdf" type="file" accept="application/pdf,.pdf" class="file-input @error('file_pdf') is-invalid @enderror" required data-pdf-input data-max-bytes="{{ $batasUnggahPdf['byte'] }}" data-max-label="{{ $batasUnggahPdf['label'] }}" aria-describedby="file_pdf_help file_pdf_client_error">
                            <p id="file_pdf_help" class="help-text">PDF maksimal {{ $batasUnggahPdf['label'] }}.</p>
                            <p id="file_pdf_client_error" class="error-text" data-pdf-client-error role="alert" hidden></p>
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
                    <button type="submit" class="button button-primary" data-pdf-submit>Unggah PDF</button>
                </div>
                <p class="help-text" data-pdf-upload-status role="status" hidden></p>
            </div>
        </div>
    </form>

    @include('perangkat-ajar-saya.partials.validasi-file-pdf')

    <script>
        (() => {
            const mataPelajaran = document.getElementById('mata_pelajaran_id');
            const tingkat = document.getElementById('tingkat');
            const tingkatPerMataPelajaran = @json($tingkatPerMataPelajaran);
            const tingkatAwal = @json((string) $tingkatTerpilih);
            const labelTingkat = { 7: 'VII', 8: 'VIII', 9: 'IX' };

            if (!mataPelajaran || !tingkat) return;

            const perbaruiTingkat = (nilaiTerpilih = '') => {
                const daftarTingkat = tingkatPerMataPelajaran[mataPelajaran.value] || [];
                tingkat.innerHTML = '<option value="">Pilih tingkat</option>';

                daftarTingkat.forEach((nilai) => {
                    const option = document.createElement('option');
                    option.value = String(nilai);
                    option.textContent = `Tingkat ${labelTingkat[nilai] || nilai}`;
                    option.selected = String(nilai) === String(nilaiTerpilih);
                    tingkat.appendChild(option);
                });

                if (daftarTingkat.length === 1 && !nilaiTerpilih) {
                    tingkat.value = String(daftarTingkat[0]);
                }
            };

            mataPelajaran.addEventListener('change', () => perbaruiTingkat());
            perbaruiTingkat(tingkatAwal === '0' ? '' : tingkatAwal);
        })();
    </script>
@endsection
