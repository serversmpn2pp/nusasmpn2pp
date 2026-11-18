@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', 'Input Kolektif Tahfidz - NUSA')
@include('tahfidz._assets')
@push('scripts')
    <script src="{{ asset('js/tahfidz-kolektif.js') }}?v={{ filemtime(public_path('js/tahfidz-kolektif.js')) }}" defer></script>
@endpush

@section('content')
    <div class="tf-page">
        <div class="page-header">
            <div>
                <p class="eyebrow">Tahfidz / Pertemuan</p>
                <h1 class="page-title">Input Kolektif Tahfidz</h1>
                <p class="page-subtitle">{{ $kelompok->nama }} &middot; {{ $pertemuan->tanggal->locale('id')->translatedFormat('l, j F Y') }} &middot; {{ $kelompok->tahunPelajaran->nama }}</p>
            </div>
            <div class="actions">
                <a href="{{ route('tahfidz.show', $kelompok) }}" class="button button-muted"><i data-lucide="arrow-left" aria-hidden="true"></i> Kelompok</a>
            </div>
        </div>
        @include('tahfidz._pesan')
        @include('tahfidz._status-pertemuan', ['bolehCatat' => true])
        @include('tahfidz._mode-setoran', ['modeSetoran' => 'kolektif'])

        <form method="GET" action="{{ route('tahfidz.kolektif.index', [$kelompok, $pertemuan]) }}" class="tf-surface tf-section" data-tf-batch-filter>
            <div class="tf-form-body tf-kolektif-filter">
                <div class="field">
                    <label for="tf-filter-siswa">Siswa</label>
                    <select class="select" name="siswa" id="tf-filter-siswa">
                        <option value="">Semua siswa</option>
                        @foreach ($peserta as $item)
                            <option value="{{ $item->id }}" @selected($siswaDipilih === $item->id)>{{ $item->siswa->nama_lengkap }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="tf-kolektif-summary">
                    <span>{{ $peserta->count() }} siswa aktif</span>
                    <span>{{ $surahDipilih->count() }} surah dipilih</span>
                </div>
            </div>
            <details class="tf-kolektif-surah" @if ($surahDipilih->isEmpty()) open @endif>
                <summary>Surah Juz 30 <span data-tf-surah-count>{{ $surahDipilih->count() }}/37</span></summary>
                <div class="tf-target-options">
                    @foreach ($surah as $item)
                        <label>
                            <input type="checkbox" name="surah[]" value="{{ $item->nomor }}" @checked($surahDipilih->contains('nomor', $item->nomor))>
                            <span>{{ $item->nomor }}. {{ $item->nama }}</span>
                        </label>
                    @endforeach
                </div>
            </details>
            <div class="tf-save">
                <button type="submit" class="button button-primary"><i data-lucide="table-2" aria-hidden="true"></i> Buka tabel</button>
            </div>
        </form>

        @if ($surahDipilih->isEmpty())
            <p class="empty-state">Belum ada surah yang dipilih.</p>
        @elseif ($anggota->isEmpty())
            <p class="empty-state">Tidak ada siswa aktif pada halaman ini.</p>
            <div class="tf-pagination">{{ $anggota->links() }}</div>
        @else
            @php
                $tokenBatch = old('token_batch');
                $tokenBatch = is_string($tokenBatch) && \Illuminate\Support\Str::isUuid($tokenBatch)
                    ? $tokenBatch : (string) \Illuminate\Support\Str::uuid();
            @endphp
            <form method="POST" action="{{ route('tahfidz.kolektif.store', [$kelompok, $pertemuan]) }}" class="tf-surface tf-section" data-tf-batch-form>
                @csrf
                <input type="hidden" name="token_batch" value="{{ $tokenBatch }}">
                <input type="hidden" name="siswa" value="{{ $siswaDipilih }}">
                <input type="hidden" name="page" value="{{ $anggota->currentPage() }}">
                @foreach ($surahDipilih as $item)
                    <input type="hidden" name="surah[]" value="{{ $item->nomor }}">
                @endforeach
                <header class="tf-band">
                    <h2 id="tf-tabel-kolektif">Nilai setoran baru</h2>
                    <span>{{ $anggota->firstItem() }}-{{ $anggota->lastItem() }} dari {{ $anggota->total() }} siswa</span>
                </header>
                <div class="tf-kolektif-scroll" tabindex="0" role="region" aria-labelledby="tf-tabel-kolektif">
                    <table class="tf-kolektif-table">
                        <thead>
                            <tr>
                                <th scope="col" class="tf-kolektif-student">Siswa</th>
                                @foreach ($surahDipilih as $item)
                                    <th scope="col" id="tf-kolom-{{ $item->nomor }}">{{ $item->nama }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($anggota as $pesertaBaris)
                                <tr>
                                    <th scope="row" class="tf-kolektif-student">
                                        <span>{{ $pesertaBaris->siswa->nama_lengkap }}</span>
                                        <a class="tf-kolektif-history" href="{{ route('tahfidz.anggota.riwayat', [$kelompok, $pesertaBaris]) }}">Riwayat <i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
                                    </th>
                                    @foreach ($surahDipilih as $item)
                                        @php
                                            $key = $pesertaBaris->id.'.'.$item->nomor;
                                            $nilaiBaru = old('nilai.'.$key);
                                            $acuan = $terakhir->get($pesertaBaris->id)?->get($item->nomor);
                                            $inputId = 'tf-nilai-'.$pesertaBaris->id.'-'.$item->nomor;
                                        @endphp
                                        <td>
                                            <label class="tf-kolektif-label" for="{{ $inputId }}">Nilai {{ $item->nama }} - {{ $pesertaBaris->siswa->nama_lengkap }}</label>
                                            <input id="{{ $inputId }}" class="input @error('nilai.'.$key) is-invalid @enderror"
                                                type="text" inputmode="decimal" maxlength="6" pattern="[0-9]{1,3}([,.][0-9]{1,2})?"
                                                name="nilai[{{ $pesertaBaris->id }}][{{ $item->nomor }}]"
                                                value="{{ is_scalar($nilaiBaru) ? $nilaiBaru : '' }}" autocomplete="off" placeholder="-"
                                                aria-describedby="{{ $inputId }}-acuan{{ $errors->has('nilai.'.$key) ? ' '.$inputId.'-error' : '' }}"
                                                @error('nilai.'.$key) aria-invalid="true" @enderror data-tf-batch-cell>
                                            <span id="{{ $inputId }}-acuan" class="tf-kolektif-reference">
                                                @if ($acuan)
                                                    Terakhir: <strong>{{ $acuan->labelNilai() }}</strong>
                                                    <span>{{ \Illuminate\Support\Carbon::parse($acuan->tanggal_setoran)->format('d/m/Y') }}</span>
                                                @else
                                                    Belum ada setoran
                                                @endif
                                            </span>
                                            @error('nilai.'.$key)
                                                <span id="{{ $inputId }}-error" class="tf-kolektif-error">{{ $message }}</span>
                                            @enderror
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="tf-save tf-kolektif-save">
                    <span role="status" aria-live="polite" data-tf-batch-count>0 setoran baru</span>
                    <button type="submit" class="button button-primary" data-tf-batch-save><i data-lucide="save" aria-hidden="true"></i> Simpan kolektif</button>
                </div>
            </form>
            <div class="tf-pagination">{{ $anggota->links() }}</div>
        @endif
    </div>
@endsection
