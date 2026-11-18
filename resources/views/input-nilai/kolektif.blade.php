@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', 'Input Nilai Beberapa Komponen - NUSA')

@section('content')
    @php
        $cakupan = array_filter([
            'tahun_pelajaran_id' => $tahunId,
            'semester' => $semester,
            'kelas_id' => $kelasId,
        ]);
        $perKomponen = route('input-nilai.index', [
            ...$cakupan,
            ...($komponenDipilih->isNotEmpty() ? ['komponen_nilai_id' => $komponenDipilih->first()->id] : []),
        ]);
        $jumlahPerMapel = $daftarPenugasan->countBy('mata_pelajaran_id');
    @endphp
    @include('input-nilai._kolektif-styles')

    <div class="page-header">
        <div><p class="eyebrow">Penilaian</p><h1 class="page-title">Input nilai</h1></div>
        <div class="actions">
            @if ($daftarKomponen->isNotEmpty())
                <a href="{{ route('input-nilai.kolektif.export', ['guru_mata_pelajaran_id' => $penugasan->id, 'semester' => $semester]) }}"
                    class="button button-muted" data-bulk-export title="Unduh semua nilai yang sudah tersimpan">
                    <i data-lucide="download" aria-hidden="true"></i> Ekspor Excel
                </a>
            @endif
            @izin('nilai.komponen_kelola')
                <a href="{{ route('komponen-nilai.index') }}" class="button button-muted">Komponen nilai</a>
            @endizin
        </div>
    </div>

    @include('input-nilai._mode', ['modeKolektif' => true, 'tautanPerKomponen' => $perKomponen, 'tautanKolektif' => request()->fullUrl()])

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($daftarTahun->isEmpty())
        <div class="panel panel-pad empty-state">Belum ada penugasan mengajar aktif yang tersedia.</div>
    @else
        <form action="{{ route('input-nilai.kolektif') }}" method="GET" class="feature-filter bulk-grade-context" data-bulk-context>
            <div class="field">
                <label for="bulk-tahun">Tahun pelajaran</label>
                <select id="bulk-tahun" name="tahun_pelajaran_id" class="select">
                    @foreach ($daftarTahun as $tahun)
                        <option value="{{ $tahun->id }}" @selected($tahunId === $tahun->id)>{{ $tahun->nama }}{{ $tahun->aktif ? ' (aktif)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="bulk-semester">Semester</label>
                <select id="bulk-semester" name="semester" class="select">
                    <option value="ganjil" @selected($semester === 'ganjil')>Ganjil</option>
                    <option value="genap" @selected($semester === 'genap')>Genap</option>
                </select>
            </div>
            <div class="field">
                <label for="bulk-kelas">Kelas</label>
                <select id="bulk-kelas" name="kelas_id" class="select">
                    <option value="">Pilih kelas</option>
                    @foreach ($daftarKelas as $kelas)
                        <option value="{{ $kelas->id }}" @selected($kelasId === $kelas->id)>{{ $kelas->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="bulk-mapel">Mata pelajaran</label>
                <select id="bulk-mapel" name="guru_mata_pelajaran_id" class="select" @disabled(! $kelasId)>
                    <option value="">Pilih mata pelajaran</option>
                    @foreach ($daftarPenugasan as $item)
                        <option value="{{ $item->id }}" @selected($penugasan?->id === $item->id)>
                            {{ $item->mataPelajaran?->nama }}{{ $jumlahPerMapel->get($item->mata_pelajaran_id) > 1 && $item->pegawai?->nama_lengkap ? ' - '.$item->pegawai->nama_lengkap : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <noscript><button type="submit" class="button button-muted">Muat pilihan</button></noscript>
        </form>

        @if (! $penugasan)
            <div class="panel panel-pad empty-state">Pilih kelas dan mata pelajaran.</div>
        @elseif ($daftarKomponen->isEmpty())
            <div class="panel panel-pad empty-state">Belum ada komponen aktif untuk penugasan dan semester ini.</div>
        @else
            <details class="panel bulk-grade-pick" @if ($komponenDipilih->isEmpty()) open @endif>
                <summary>Komponen nilai <span class="badge badge-muted">{{ $komponenDipilih->count() }} ditampilkan</span></summary>
                <form method="GET" action="{{ route('input-nilai.kolektif') }}" class="bulk-grade-picker">
                    @foreach ([...$cakupan, 'guru_mata_pelajaran_id' => $penugasan->id] as $nama => $nilai)
                        <input type="hidden" name="{{ $nama }}" value="{{ $nilai }}">
                    @endforeach
                    <fieldset class="bulk-grade-options">
                        <legend class="sr-only">Komponen untuk tabel nilai</legend>
                        @foreach ($daftarKomponen as $komponen)
                            @php
                                $terkunci = $alasanTerkunci->get($komponen->id);
                            @endphp
                            <label class="bulk-grade-option">
                                <input type="checkbox" name="komponen[]" value="{{ $komponen->id }}"
                                    @checked($komponenDipilih->contains('id', $komponen->id)) @disabled($terkunci)>
                                <span><strong>{{ $komponen->nama }}</strong><small>{{ $komponen->labelJenis() }}</small>
                                    @if ($terkunci)<small class="bulk-grade-locked"><i data-lucide="lock" aria-hidden="true"></i> {{ $terkunci }}</small>@endif
                                </span>
                            </label>
                        @endforeach
                    </fieldset>
                    <div class="bulk-grade-picker-end">
                        <button type="submit" class="button button-primary" @disabled($alasanTerkunci->every(fn ($alasan) => $alasan !== null))>
                            <i data-lucide="table-2" aria-hidden="true"></i> Buka tabel nilai
                        </button>
                    </div>
                </form>
            </details>
        @endif
    @endif

    @if ($komponenDipilih->isNotEmpty())
        <section class="panel bulk-grade-workspace" aria-labelledby="judul-tabel-kolektif">
            <div class="bulk-grade-heading">
                <div><h2 id="judul-tabel-kolektif">Daftar nilai siswa</h2><p>{{ $anggota->count() }} siswa &middot; {{ $komponenDipilih->count() }} komponen</p></div>
                <span class="badge {{ $dipublikasikan ? 'badge-active' : 'badge-warning' }}" data-bulk-publication>{{ $dipublikasikan ? 'Dipublikasikan' : 'Draf' }}</span>
            </div>
            @if ($anggota->isEmpty())
                <div class="empty-state">Belum ada siswa aktif di kelas ini.</div>
            @else
                <form action="{{ route('input-nilai.kolektif.store') }}" method="POST" data-bulk-form
                    data-penugasan="{{ $penugasan->id }}" data-semester="{{ $semester }}" data-skala="{{ $predikat ? 'predikat' : 'angka' }}">
                    @csrf
                    <div class="bulk-grade-scroll" tabindex="0" role="region" aria-labelledby="judul-tabel-kolektif">
                        <table class="bulk-grade-table">
                            <thead><tr>
                                <th scope="col">Siswa</th>
                                @foreach ($komponenDipilih as $komponen)
                                    <th scope="col">{{ $komponen->nama }}<small>{{ $komponen->labelJenis() }}</small></th>
                                @endforeach
                            </tr></thead>
                            <tbody>
                                @foreach ($anggota as $anggotaKelas)
                                    @php
                                        $siswaId = $anggotaKelas->siswa_id;
                                    @endphp
                                    <tr>
                                        <th scope="row">{{ $anggotaKelas->siswa?->nama_lengkap ?? '-' }}<small>No. absen {{ $anggotaKelas->nomor_absen ?? '-' }}</small></th>
                                        @foreach ($komponenDipilih as $komponen)
                                            @php
                                                $nilaiTersimpan = $nilaiSiswa->get($siswaId)?->get($komponen->id);
                                                $nilaiAwal = $predikat ? $nilaiTersimpan?->predikat : $nilaiTersimpan?->nilai;
                                                $label = ($predikat ? 'Predikat ' : 'Nilai ').($anggotaKelas->siswa?->nama_lengkap ?? '-').', '.$komponen->nama;
                                                $idError = 'bulk-error-'.$komponen->id.'-'.$siswaId;
                                            @endphp
                                            <td>
                                                @if ($predikat)
                                                    <select class="select" aria-label="{{ $label }}" aria-describedby="{{ $idError }}"
                                                        data-bulk-cell data-komponen="{{ $komponen->id }}" data-siswa="{{ $siswaId }}" data-nilai-awal="{{ $nilaiAwal }}">
                                                        <option value="">-</option>
                                                        @foreach (\App\Models\MataPelajaran::PREDIKAT_NILAI as $opsi)
                                                            <option value="{{ $opsi }}" @selected($nilaiAwal === $opsi)>{{ $opsi }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input class="input" type="text" inputmode="decimal" maxlength="16" autocomplete="off"
                                                        aria-label="{{ $label }}" aria-describedby="{{ $idError }}"
                                                        value="{{ $nilaiAwal === null ? '' : str_replace('.', ',', $nilaiAwal) }}"
                                                        data-bulk-cell data-komponen="{{ $komponen->id }}" data-siswa="{{ $siswaId }}" data-nilai-awal="{{ $nilaiAwal }}">
                                                @endif
                                                <p id="{{ $idError }}" class="error-text bulk-grade-cell-error" hidden></p>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="bulk-grade-footer">
                        <div>
                            <span role="status" data-bulk-status></span>
                            <p role="alert" class="error-text" data-bulk-error hidden></p>
                        </div>
                        <button type="submit" class="button button-primary" data-bulk-save disabled>
                            <i data-lucide="save" aria-hidden="true"></i> Simpan semua sebagai draf
                        </button>
                    </div>
                </form>
            @endif
        </section>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/input-nilai-kolektif.js') }}?v={{ filemtime(public_path('js/input-nilai-kolektif.js')) }}" defer></script>
@endpush
