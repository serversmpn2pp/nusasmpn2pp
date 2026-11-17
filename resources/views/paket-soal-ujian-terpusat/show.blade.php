@extends('layouts.app')
@section('admin-module', 'ujian-asesmen')
@section('title', 'Paket Soal Terpusat - NUSA')

@section('content')
    @php
        $paketSiap = $paket && in_array($paket->status, ['terjadwal', 'berlangsung', 'selesai'], true);
        $skorUntuk = fn ($item) => $sedangMenyusun
            ? $item->skor_maksimal
            : ($soalDipilih->get($item->id)?->bobot ?? $item->skor_maksimal);
        $totalBobot = $soal->sum(fn ($item) => (float) $skorUntuk($item));
        $daftarPaket = route('paket-soal-terpusat.index', ['kegiatan' => $jadwal->kegiatan_ujian_cbt_id]);
        $parameterSusun = ['jadwalUjianCbt' => $jadwal, 'mode' => 'susun'];
    @endphp

    @include('paket-soal-ujian-terpusat.partials.styles')
    <div class="page-header">
        <div>
            <p class="eyebrow">Ujian Terpusat &middot; Tahap 8</p>
            <h1 class="page-title">{{ $sedangMenyusun ? 'Susun paket soal' : 'Periksa paket soal' }}</h1>
        </div>
        <div class="actions">
            @if ($bolehKelola)
                <a href="{{ route('paket-soal-terpusat.show', ['jadwalUjianCbt' => $jadwal, 'mode' => $sedangMenyusun ? 'periksa' : 'susun']) }}" class="button button-muted">
                    <i data-lucide="{{ $sedangMenyusun ? 'eye' : 'pencil' }}" aria-hidden="true"></i>
                    {{ $sedangMenyusun ? 'Periksa paket' : 'Susun paket' }}
                </a>
            @endif
            @if ($paket?->dapatDiaksesOperasionalOleh(auth()->user()))
                <a href="{{ route('ujian-cbt.hasil.index', $paket) }}" class="button button-muted">Lihat nilai</a>
                <a href="{{ route('ujian-cbt.hasil.analisis-soal', $paket) }}" class="button button-muted">Analisis soal</a>
            @endif
            @if ($paketSiap)
                <a href="{{ route('ujian-terpusat.pelaksanaan-nilai.index', $jadwal->kegiatan_ujian_cbt_id) }}" class="button button-muted">Pelaksanaan ujian</a>
            @endif
            <a href="{{ $daftarPaket }}" class="button button-muted"><i data-lucide="list" aria-hidden="true"></i> Daftar paket</a>
        </div>
    </div>

    @if (session('berhasil')) <div class="alert">{{ session('berhasil') }}</div> @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Ada bagian yang perlu diperbaiki.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($jadwal->kegiatanUjianCbt?->dapatDiaksesOleh(auth()->user()))
        @include('ujian-terpusat.partials.alur', ['kegiatan' => $jadwal->kegiatanUjianCbt, 'tahapAktif' => 8])
    @endif

    <section class="panel panel-pad package-context" aria-labelledby="package-context-title">
        <div class="package-heading">
            <div>
                <p class="eyebrow">{{ $jadwal->kegiatanUjianCbt?->nama }}</p>
                <h2 id="package-context-title">{{ $jadwal->mataPelajaran?->nama }} &middot; Tingkat {{ $jadwal->tingkat }}</h2>
                <p>{{ $jadwal->tanggal?->locale('id')->translatedFormat('l, d F Y') }} &middot; {{ $jadwal->sesiKegiatanUjianCbt?->nama }} &middot; {{ $jadwal->labelWaktu() }}</p>
            </div>
            <span class="badge {{ $paketSiap ? 'badge-active' : ($paket ? 'badge-warning' : 'badge-muted') }}">{{ $paketSiap ? 'Paket siap' : ($paket ? 'Masih draf' : 'Belum disusun') }}</span>
        </div>
        <dl class="package-facts">
            <div><dt>Kelas</dt><dd>{{ $jadwal->kelas->pluck('nama')->join(', ') }}</dd></div>
            <div><dt>Durasi ujian</dt><dd>{{ $jadwal->durasiMenit() }} menit</dd></div>
            <div><dt>Tahun pelajaran</dt><dd>{{ $jadwal->kegiatanUjianCbt?->tahunPelajaran?->nama }}</dd></div>
            <div><dt>Penilaian PGK</dt><dd>{{ $jadwal->kegiatanUjianCbt?->labelPenilaianPgk() }}</dd></div>
            @if ($paket?->token)<div><dt>Token otomatis</dt><dd>{{ $paket->token }}</dd></div>@endif
        </dl>
    </section>

    @if ($sedangMenyusun && $jadwal->kegiatanUjianCbt?->jenisUjianCbt?->kode === 'SIMULASI_CBT')
        <form method="POST" action="{{ route('paket-soal-terpusat.update', $parameterSusun) }}" class="panel panel-pad package-context">
            @csrf @method('PUT')
            <input type="hidden" name="gunakan_paket_simulasi" value="1">
            <input type="hidden" name="acak_soal" value="0">
            <input type="hidden" name="acak_jawaban" value="0">
            <div class="package-heading">
                <div><h2>Simulasi CBT</h2><p>12 soal umum &middot; 20 menit &middot; tanpa nilai akademik</p></div>
                <button class="button button-muted" name="aksi" value="terbitkan">Gunakan paket Simulasi CBT</button>
            </div>
        </form>
    @endif

    @if ($sedangMenyusun)
        <form action="{{ route('paket-soal-terpusat.update', $parameterSusun) }}" method="POST" data-package-form>
            @csrf @method('PUT')
            <input type="hidden" name="daftar_soal_dikirim" value="1">
    @endif

    <section class="panel" aria-labelledby="package-questions-title">
        <div class="package-heading package-section-head">
            <div>
                <h2 id="package-questions-title">Soal dalam paket</h2>
                <p role="status"><b data-package-count>{{ $soal->count() }}</b> soal &middot; Total skor <b data-package-score>{{ number_format($totalBobot, 2, ',', '.') }}</b></p>
            </div>
            @if ($sedangMenyusun)
                <button type="button" class="button button-primary" data-open-bank aria-haspopup="dialog"><i data-lucide="folder-plus" aria-hidden="true"></i> Tambah dari Bank Soal</button>
            @endif
        </div>
        <div class="package-empty" data-package-empty @if ($soal->isNotEmpty()) hidden @endif>Belum ada soal dalam paket ini.</div>
        <ol class="package-question-list" data-package-list>
            @foreach ($soal as $item)
                @include('paket-soal-ujian-terpusat.partials.baris-soal', ['nomor' => $loop->iteration, 'skor' => $skorUntuk($item)])
            @endforeach
        </ol>
    </section>

    @if ($sedangMenyusun)
            <section class="panel panel-pad package-shuffle" aria-labelledby="package-shuffle-title">
                <h2 id="package-shuffle-title" class="panel-title">Pengacakan untuk siswa</h2>
                <div class="package-shuffle-options">
                    <label>
                        <input type="hidden" name="acak_soal" value="0">
                        <input type="checkbox" name="acak_soal" value="1" @checked(old('acak_soal', $paket?->acak_soal ?? true))>
                        Acak urutan soal
                    </label>
                    <label>
                        <input type="hidden" name="acak_jawaban" value="0">
                        <input type="checkbox" name="acak_jawaban" value="1" @checked(old('acak_jawaban', $paket?->acak_jawaban ?? true))>
                        Acak pilihan jawaban
                    </label>
                </div>
            </section>

            <div class="package-form-actions">
                <a href="{{ $daftarPaket }}" class="button button-muted">Batal</a>
                <div class="actions">
                    @if ($paketSiap)
                        <button type="submit" name="aksi" value="draf" class="button button-muted">Kembalikan ke draf</button>
                        <button type="submit" name="aksi" value="simpan" class="button button-primary">Simpan perubahan</button>
                    @else
                        <button type="submit" name="aksi" value="draf" class="button button-muted">Simpan draf</button>
                        <button type="submit" name="aksi" value="terbitkan" class="button button-primary">Terbitkan paket</button>
                    @endif
                </div>
            </div>
        </form>
        @include('paket-soal-ujian-terpusat.partials.dialog-bank')
    @elseif ($sudahDikerjakan)
        <p class="help-text package-lock-note"><i data-lucide="lock" aria-hidden="true"></i> Paket tidak dapat diubah karena sudah dikerjakan peserta.</p>
    @endif

    @include('soal-cbt.partials.preview-styles')
    <dialog class="question-preview-dialog" data-question-preview-dialog aria-labelledby="judul-pratinjau-paket">
        <div class="question-preview-head">
            <h2 id="judul-pratinjau-paket">Pratinjau soal</h2>
            <button type="button" class="button button-muted" data-close-question-preview>Tutup</button>
        </div>
        <div class="question-preview-body" data-question-preview-body></div>
    </dialog>
    @push('scripts')
        @vite('resources/js/soal-editor.js')
        @if ($sedangMenyusun)
            <script src="{{ asset('js/paket-soal-terpusat.js') }}?v={{ filemtime(public_path('js/paket-soal-terpusat.js')) }}" defer></script>
        @endif
    @endpush
@endsection
