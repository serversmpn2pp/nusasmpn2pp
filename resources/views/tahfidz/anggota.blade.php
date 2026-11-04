@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', 'Atur Anggota Tahfidz - NUSA')
@include('tahfidz._assets')
@push('scripts')
    <script src="{{ asset('js/tahfidz-anggota.js') }}?v={{ filemtime(public_path('js/tahfidz-anggota.js')) }}" defer></script>
@endpush

@section('content')
    @php
        $nilaiLama = old('daftar_lengkap') ? old('siswa_ids', []) : $terpilih->all();
        $dicentang = collect(is_array($nilaiLama) ? $nilaiLama : [])->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false)->map(fn ($id) => (int) $id)->flip();
        $kelas = $pilihan->map(fn ($siswa) => $siswa->anggotaKelas->first()?->kelas)->filter()->unique('id')->sortBy('nama');
        $tingkat = $kelas->pluck('tingkat')->filter()->unique()->sort();
    @endphp
    <div class="tf-page">
        <div class="page-header">
            <div><p class="eyebrow">Tahfidz / Kelompok</p><h1 class="page-title">Atur anggota</h1><p class="page-subtitle">{{ $kelompok->nama }} &middot; {{ $kelompok->tahunPelajaran->nama }} &middot; {{ $kelompok->pembimbing->nama_lengkap }}</p></div>
            <div class="actions"><a href="{{ route('tahfidz.show', $kelompok) }}" class="button button-muted"><i data-lucide="arrow-left" aria-hidden="true"></i> Kembali</a></div>
        </div>
        @include('tahfidz._pesan')

        <form method="POST" action="{{ route('tahfidz.anggota.update', $kelompok) }}" data-pemilih-anggota>
            @csrf
            @method('PUT')
            <input type="hidden" name="versi" value="{{ old('versi', $kelompok->versi) }}">

            <div class="feature-filter tf-filters tf-filters-roster" data-roster-filters hidden>
                <div class="field"><label for="tf-cari">Cari siswa</label><input id="tf-cari" type="search" class="input" placeholder="Nama, NIS, atau NISN" data-roster-search></div>
                <div class="field"><label for="tf-tingkat">Tingkat</label><select id="tf-tingkat" class="select" data-roster-grade>
                    <option value="">Semua tingkat</option>
                    @foreach ($tingkat as $item)<option value="{{ $item }}">Tingkat {{ $item }}</option>@endforeach
                </select></div>
                <div class="field"><label for="tf-kelas">Kelas</label><select id="tf-kelas" class="select" data-roster-class>
                    <option value="">Semua kelas</option>
                    @foreach ($kelas as $item)<option value="{{ $item->id }}" data-grade="{{ $item->tingkat }}">{{ $item->nama }}</option>@endforeach
                </select></div>
                <div class="field"><label for="tf-pilihan">Pilihan</label><select id="tf-pilihan" class="select" data-roster-status>
                    <option value="">Semua siswa</option><option value="selected">Siswa terpilih</option><option value="available">Belum dipilih</option>
                </select></div>
            </div>

            <section class="tf-surface" aria-labelledby="tf-daftar-siswa">
                <header class="tf-band"><h2 id="tf-daftar-siswa">Daftar siswa</h2><span data-roster-count role="status" aria-live="polite">{{ $dicentang->count() }} terpilih</span></header>
                <div class="tf-bulk" data-roster-bulk hidden>
                    <label><input type="checkbox" data-roster-all> Pilih semua hasil</label>
                    <span data-roster-visible class="person-meta"></span>
                </div>
                <div class="tf-roster-scroll" tabindex="0" role="region" aria-label="Pilihan anggota Tahfidz">
                    <ul class="tf-list">
                        @foreach ($pilihan as $siswa)
                            @php
                                $kelasSiswa = $siswa->anggotaKelas->first()?->kelas;
                                $lain = $kelompokLain->get($siswa->id);
                            @endphp
                            <li data-roster-row data-search="{{ $siswa->nama_lengkap }} {{ $siswa->nis }} {{ $siswa->nisn }}" data-grade="{{ $kelasSiswa?->tingkat }}" data-class="{{ $kelasSiswa?->id }}">
                                <label class="tf-choice">
                                    <input type="checkbox" name="siswa_ids[]" value="{{ $siswa->id }}" @checked($lain === null && $dicentang->has($siswa->id)) @disabled($lain !== null)>
                                    <span><span class="person-name">{{ $siswa->nama_lengkap }}</span>
                                        @if ($lain !== null)<small class="person-meta">Anggota {{ $lain }}</small>
                                        @elseif (! $calon->has($siswa->id))<small class="person-meta">Di luar daftar kelas aktif</small>
                                        @endif
                                    </span>
                                    <span class="badge badge-muted">{{ $kelasSiswa?->nama ?? 'Belum ada kelas' }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                    <p class="empty-state" data-roster-empty @if($pilihan->isNotEmpty()) hidden @endif>Belum ada siswa yang sesuai.</p>
                </div>
                <input type="hidden" name="daftar_lengkap" value="1">
                <div class="tf-save">
                    <a href="{{ route('tahfidz.show', $kelompok) }}" class="button button-muted">Batal</a>
                    <button type="submit" class="button button-primary"><i data-lucide="save" aria-hidden="true"></i> Simpan anggota</button>
                </div>
            </section>
        </form>
    </div>
@endsection
