@extends('layouts.app')

@section('title', 'Input Nilai - NUSA')

@section('content')
    <style>
        .grade-filter { margin-bottom:24px; padding:20px 0; border-top:1px solid var(--line); border-bottom:1px solid var(--line); }
        .input-nilai-filter { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; }
        .input-nilai-filter .field, .grade-component .field { min-width:0; }
        .grade-component { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:end; gap:16px; padding-top:18px; margin-top:18px; border-top:1px solid var(--line); }
        .grade-component .actions { display:flex; flex-wrap:wrap; gap:8px; }
        .grade-component-label { display:flex; align-items:baseline; justify-content:space-between; gap:8px; margin-bottom:6px; }
        .grade-component-label label { margin-bottom:0; }
        .grade-component-count { color:#52525b; font-size:.8rem; white-space:nowrap; }
        .grade-filter .select { min-width:0; text-overflow:ellipsis; }
        .grade-filter[aria-busy="true"] { pointer-events:none; opacity:.7; }
        .grade-filter-state { margin:10px 0 0; color:var(--primary); font-size:.85rem; }
        .grade-unsaved { color:#854d0e; font-size:.85rem; font-weight:700; }
        .grade-filter [hidden], .grade-unsaved[hidden] { display:none !important; }
        @media(max-width:1000px) { .input-nilai-filter { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media(max-width:600px) {
            .input-nilai-filter, .grade-component { grid-template-columns:minmax(0,1fr); gap:12px; }
            .grade-component .actions { display:grid; grid-template-columns:minmax(0,1fr) auto; }
            .grade-component .button { justify-content:center; }
            .grade-component-label { flex-wrap:wrap; }
        }

        .grade-workspace { display:grid; grid-template-columns:minmax(0,1fr); gap:20px; }
        .grade-overview { padding:0 0 20px; border-bottom:1px solid var(--line); }
        .grade-overview-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; }
        .grade-overview-head > div { min-width:0; }
        .grade-overview-head h2 { margin:0; font-size:1.15rem; line-height:1.4; overflow-wrap:anywhere; }
        .grade-overview-head p { margin:4px 0 0; color:var(--muted); font-size:.9rem; }
        .grade-overview-head .badge { flex-shrink:0; }
        .grade-facts { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)) minmax(0,1.4fr) minmax(0,.8fr) minmax(0,1fr); gap:16px; margin:18px 0 0; font-size:.88rem; }
        .grade-facts dt { color:var(--muted); margin-bottom:4px; }
        .grade-facts dd { margin:0; font-weight:700; overflow-wrap:anywhere; }
        .grade-entry { min-width:0; }
        .grade-entry-head { display:flex; justify-content:space-between; align-items:center; gap:16px; padding:16px; border-bottom:1px solid var(--line); }
        .grade-entry-head h2 { margin:0; font-size:1rem; }
        .grade-metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:24px; margin:0; }
        .grade-metrics dt { color:var(--muted); font-size:.8rem; }
        .grade-metrics dd { margin:3px 0 0; font-size:1.1rem; font-weight:800; font-variant-numeric:tabular-nums; }
        .grade-table.placement-table { min-width:0; table-layout:fixed; }
        .grade-col-absence { width:80px; }
        .grade-col-student { width:30%; }
        .grade-col-identifier { width:16%; }
        .grade-col-score { width:140px; }
        .grade-table--predikat .grade-col-score { width:180px; }
        .grade-table th, .grade-table td { padding:12px; overflow-wrap:anywhere; }
        .grade-table .input, .grade-table .select { min-width:0; max-width:100%; }
        .grade-table th .help-text { display:block; font-size:.72rem; line-height:1.4; font-weight:400; text-transform:none; margin-top:4px; }
        .grade-table .person-name, .grade-table .person-meta { overflow-wrap:anywhere; }
        .grade-save-actions { border-top:1px solid var(--line); padding:16px; align-items:center; }
        .grade-save-actions .grade-unsaved { margin-right:auto; }
        .publication-box { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:20px; margin-top:18px; padding:14px; border-left:3px solid #f1c40f; background:#fafafa; }
        .publication-copy { min-width:0; }

        .publication-box.is-published {
            border-left-color: #16a34a;
        }

        .publication-head {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
        }

        .publication-head strong {
            color: #17324c;
        }

        .publication-box p {
            margin: 8px 0 0;
            color: #64748b;
            font-size: .78rem;
            line-height: 1.5;
        }

        .publication-box form {
            margin:0;
        }

        @media(max-width:1100px) {
            .grade-facts { grid-template-columns:repeat(3,minmax(0,1fr)); }
            .grade-col-student { width:28%; }
            .grade-col-identifier { width:18%; }
            .grade-col-score { width:130px; }
            .grade-table--predikat .grade-col-score { width:160px; }
        }
        @media(max-width:900px) {
            .grade-table.placement-table { table-layout:auto; }
            .grade-table colgroup { display:none; }
            .grade-table td { padding:8px 0; }
        }
        @media(max-width:600px) {
            .grade-facts { grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
            .grade-overview-head { flex-wrap:wrap; gap:10px; }
            .publication-box { grid-template-columns:minmax(0,1fr); gap:14px; }
            .publication-head { flex-wrap:wrap; }
            .grade-entry-head { align-items:stretch; flex-direction:column; }
            .grade-metrics { gap:12px; }
            .grade-save-actions { align-items:stretch; }
        }

    </style>

    @php
        $labelKomponen = function ($item) use ($filter) {
            $guruMapel = $item->guruMataPelajaran;

            return collect([
                $filter['tahun_pelajaran_id'] ? null : $guruMapel?->tahunPelajaran?->nama,
                $filter['kelas_id'] ? null : $guruMapel?->kelas?->nama,
                $guruMapel?->mataPelajaran?->nama,
                $filter['jenis_komponen'] === 'semua' ? $item->labelJenis() : null,
                $filter['semester'] === 'semua' ? ucfirst($item->semester) : null,
                $item->nama,
            ])->filter()->join(' - ');
        };

        $nilaiLama = is_array(old('nilai')) ? old('nilai') : [];
        $predikatLama = is_array(old('predikat')) ? old('predikat') : [];
        $catatanLama = is_array(old('catatan')) ? old('catatan') : [];
        $ambilNilai = function ($siswaId) use ($nilaiLama, $nilaiTersimpan) {
            if (array_key_exists($siswaId, $nilaiLama)) {
                return $nilaiLama[$siswaId];
            }

            $nilai = $nilaiTersimpan->get($siswaId)?->nilai;

            if ($nilai === null) {
                return '';
            }

            return number_format((float) $nilai, 2, ',', '');
        };
        $ambilPredikat = function ($siswaId) use ($predikatLama, $nilaiTersimpan) {
            if (array_key_exists($siswaId, $predikatLama)) {
                return $predikatLama[$siswaId];
            }

            return $nilaiTersimpan->get($siswaId)?->predikat;
        };
        $ambilCatatan = function ($siswaId) use ($catatanLama, $nilaiTersimpan) {
            if (array_key_exists($siswaId, $catatanLama)) {
                return $catatanLama[$siswaId];
            }

            return $nilaiTersimpan->get($siswaId)?->catatan;
        };
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian</p>
            <h1 class="page-title">Input nilai</h1>
        </div>

        @izin('nilai.komponen_kelola')
            <a href="{{ route('komponen-nilai.index') }}" class="button button-muted">Komponen nilai</a>
        @endizin
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Ada isian yang perlu diperbaiki.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="filter-input-nilai" action="{{ route('input-nilai.index') }}" method="GET" class="grade-filter">
        <div class="input-nilai-filter">
            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select" data-grade-filter>
                    <option value="">Semua tahun pelajaran</option>
                    @foreach ($tahunPelajaran as $tahun)
                        <option value="{{ $tahun->id }}" @selected((string) $filter['tahun_pelajaran_id'] === (string) $tahun->id)>{{ $tahun->nama }}{{ $tahun->aktif ? ' (aktif)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="semester">Semester</label>
                <select id="semester" name="semester" class="select" data-grade-filter>
                    @foreach (['semua' => 'Semua semester', 'ganjil' => 'Ganjil', 'genap' => 'Genap'] as $key => $label)
                        <option value="{{ $key }}" @selected($filter['semester'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select" data-grade-filter>
                    <option value="">Semua kelas</option>
                    @foreach ($kelas as $item)
                        <option value="{{ $item->id }}" @selected((string) $filter['kelas_id'] === (string) $item->id)>{{ $item->nama }}{{ ! $filter['tahun_pelajaran_id'] ? ' - '.$tahunPelajaran->firstWhere('id', $item->tahun_pelajaran_id)?->nama : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="jenis_komponen">Jenis penilaian</label>
                <select id="jenis_komponen" name="jenis_komponen" class="select" data-grade-filter>
                    <option value="semua" @selected($filter['jenis_komponen'] === 'semua')>Semua jenis penilaian</option>
                    @foreach (\App\Support\FilterInputNilai::JENIS as $key => $label)
                        <option value="{{ $key }}" @selected($filter['jenis_komponen'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="grade-component">
            <div class="field">
                <div class="grade-component-label">
                    <label for="komponen_nilai_id">Komponen nilai</label>
                    <span class="grade-component-count">{{ $daftarKomponenNilai->count() }} komponen tersedia</span>
                </div>
                <select id="komponen_nilai_id" name="komponen_nilai_id" class="select">
                    <option value="">Pilih komponen nilai</option>
                    @foreach ($daftarKomponenNilai as $item)
                        <option value="{{ $item->id }}" data-tahun="{{ $item->guruMataPelajaran?->tahun_pelajaran_id }}" data-kelas="{{ $item->guruMataPelajaran?->kelas_id }}" data-semester="{{ $item->semester }}" data-jenis="{{ $item->jenis_komponen }}" title="{{ $labelKomponen($item) }}" @selected((string) $komponenNilaiId === (string) $item->id)>
                            {{ $labelKomponen($item) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-primary">Buka nilai</button>
                <a href="{{ route('input-nilai.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
        <p class="grade-filter-state" data-filter-state role="status" hidden>Memuat komponen nilai...</p>
    </form>

    @if ($daftarKomponenNilai->isEmpty())
        <section class="panel panel-pad">
            <h2 class="panel-title">Tidak ada komponen nilai yang sesuai</h2>
            <p class="help-text" style="margin-top: 8px;">Belum ada komponen nilai aktif untuk pilihan ini.</p>
        </section>
    @elseif (! $komponenDipilih)
        <section class="panel panel-pad">
            <h2 class="panel-title">Pilih komponen nilai</h2>
            <p class="help-text" style="margin-top: 8px;">{{ $daftarKomponenNilai->count() }} komponen nilai tersedia sesuai pilihan Anda.</p>
        </section>
    @else
        <div class="grade-workspace">
            <section class="grade-overview" aria-labelledby="grade-summary-title">
                <div class="grade-overview-head">
                    <div>
                        <h2 id="grade-summary-title">{{ $komponenDipilih->nama }}</h2>
                        <p>{{ $komponenDipilih->labelJenis() }} - {{ ucfirst($komponenDipilih->semester) }}</p>
                    </div>
                    <span class="badge badge-active">{{ $komponenDipilih->guruMataPelajaran?->kelas?->nama ?: '-' }}</span>
                </div>

                <dl class="grade-facts">
                    <div>
                        <dt>Tahun</dt>
                        <dd>{{ $komponenDipilih->guruMataPelajaran?->tahunPelajaran?->nama ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt>Mapel</dt>
                        <dd>{{ $komponenDipilih->guruMataPelajaran?->mataPelajaran?->nama ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt>Guru</dt>
                        <dd>{{ $komponenDipilih->guruMataPelajaran?->pegawai?->nama_lengkap ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt>Jenis</dt>
                        <dd>{{ $komponenDipilih->labelJenis() }}</dd>
                    </div>
                    <div>
                        <dt>Penilaian</dt>
                        <dd>{{ $komponenDipilih->guruMataPelajaran?->mataPelajaran?->labelJenisPenilaian() }}</dd>
                    </div>
                </dl>

                @php
                    $sudahDipublikasikan = $publikasiNilai?->dipublikasikan === true;
                @endphp
                <div class="publication-box {{ $sudahDipublikasikan ? 'is-published' : '' }}">
                    <div class="publication-copy">
                        <div class="publication-head">
                            <strong>Publikasi nilai</strong>
                            <span class="badge {{ $sudahDipublikasikan ? 'badge-active' : 'badge-warning' }}">
                                {{ $sudahDipublikasikan ? 'Dipublikasikan' : 'Draf' }}
                            </span>
                        </div>
                        <p>
                            {{ $jumlahNilaiPublikasi }} dari {{ $targetNilaiPublikasi }} entri terisi
                            pada {{ $jumlahKomponenPublikasi }} komponen semester ini.
                        </p>
                        @if ($sudahDipublikasikan)
                            <p>Dirilis {{ $publikasiNilai->dipublikasikan_pada?->locale('id')->translatedFormat('d F Y, H:i') }}.</p>
                        @else
                            <p>Nilai belum dapat dilihat siswa. Simpan perubahan terlebih dahulu sebelum mempublikasikan.</p>
                        @endif
                    </div>
                    @if ($sudahDipublikasikan)
                        <form
                            method="POST"
                            action="{{ route('publikasi-nilai.jadikan-draf', [$komponenDipilih->guruMataPelajaran, $komponenDipilih->semester]) }}"
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="komponen_nilai_id" value="{{ $komponenDipilih->id }}">
                            @include('input-nilai._filter-fields')
                            <button type="submit" class="button button-muted">Jadikan draf</button>
                        </form>
                    @else
                        <form
                            method="POST"
                            action="{{ route('publikasi-nilai.publikasikan', [$komponenDipilih->guruMataPelajaran, $komponenDipilih->semester]) }}"
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="komponen_nilai_id" value="{{ $komponenDipilih->id }}">
                            @include('input-nilai._filter-fields')
                            <button type="submit" class="button button-primary" @disabled($jumlahNilaiPublikasi === 0)>
                                Publikasikan nilai
                            </button>
                        </form>
                    @endif
                </div>
            </section>

            <section class="panel grade-entry" aria-labelledby="grade-entry-title">
                <div class="grade-entry-head">
                    <h2 id="grade-entry-title">Daftar nilai siswa</h2>
                    <dl class="grade-metrics">
                        <div><dt>Siswa</dt><dd>{{ $jumlahSiswa }}</dd></div>
                        <div><dt>Sudah terisi</dt><dd>{{ $jumlahTerisi }}</dd></div>
                        <div><dt>{{ $penilaianPredikat ? 'Skala nilai' : 'Rata-rata' }}</dt><dd>{{ $penilaianPredikat ? 'SB-K' : ($rataRata === null ? '-' : number_format($rataRata, 2, ',', '.')) }}</dd></div>
                    </dl>
                </div>
                @if ($anggotaKelas->isEmpty())
                    <div class="empty-state">Belum ada siswa aktif di kelas ini.</div>
                @else
                    <form action="{{ route('input-nilai.store') }}" method="POST" data-grade-form>
                        @csrf
                        <input type="hidden" name="komponen_nilai_id" value="{{ $komponenDipilih->id }}">
                        @include('input-nilai._filter-fields')

                        <div class="table-wrap">
                            <table class="employee-table placement-table grade-table {{ $penilaianPredikat ? 'grade-table--predikat' : '' }}">
                                <colgroup>
                                    <col class="grade-col-absence">
                                    <col class="grade-col-student">
                                    <col class="grade-col-identifier">
                                    <col class="grade-col-score">
                                    <col>
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>No. absen</th>
                                        <th>Siswa</th>
                                        <th>NIS/NISN</th>
                                        <th>
                                            {{ $penilaianPredikat ? 'Predikat' : 'Nilai' }}
                                            @unless($penilaianPredikat)
                                                <span class="help-text">0-100; koma atau titik, maksimal 2 desimal</span>
                                            @endunless
                                        </th>
                                        <th>Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($anggotaKelas as $anggota)
                                        @php
                                            $siswaId = $anggota->siswa_id;
                                        @endphp
                                        <tr>
                                            <td data-label="No. absen">
                                                <span class="badge badge-active">No. {{ $anggota->nomor_absen ?: '-' }}</span>
                                            </td>
                                            <td data-label="Siswa">
                                                <p class="person-name">{{ $anggota->siswa?->nama_lengkap ?: '-' }}</p>
                                                <p class="person-meta">{{ $anggota->siswa?->jenis_kelamin === 'P' ? 'Perempuan' : 'Laki-laki' }}</p>
                                            </td>
                                            <td data-label="NIS/NISN">
                                                <p class="person-name">{{ $anggota->siswa?->nis ?: '-' }}</p>
                                                <p class="person-meta">NISN: {{ $anggota->siswa?->nisn ?: '-' }}</p>
                                            </td>
                                            <td data-label="{{ $penilaianPredikat ? 'Predikat' : 'Nilai' }}">
                                                @if ($penilaianPredikat)
                                                    <select
                                                        id="predikat_{{ $siswaId }}"
                                                        name="predikat[{{ $siswaId }}]"
                                                        data-grade-value data-saved-value="{{ $nilaiTersimpan->get($siswaId)?->predikat }}"
                                                        class="select input-sm @error('predikat.' . $siswaId) is-invalid @enderror"
                                                    >
                                                        <option value="">Belum dinilai</option>
                                                        @foreach (\App\Models\MataPelajaran::PREDIKAT_NILAI as $predikat)
                                                            <option value="{{ $predikat }}" @selected($ambilPredikat($siswaId) === $predikat)>
                                                                {{ $predikat }} -
                                                                {{ match ($predikat) {
                                                                    'SB' => 'Sangat Baik',
                                                                    'B' => 'Baik',
                                                                    'C' => 'Cukup',
                                                                    'K' => 'Kurang',
                                                                } }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('predikat.' . $siswaId)
                                                        <p class="error-text">{{ $message }}</p>
                                                    @enderror
                                                @else
                                                    <input
                                                        id="nilai_{{ $siswaId }}"
                                                        name="nilai[{{ $siswaId }}]"
                                                        type="text"
                                                        inputmode="decimal"
                                                        pattern="(?:100(?:[.,]0{1,2})?|[0-9]{1,2}(?:[.,][0-9]{1,2})?)"
                                                        value="{{ $ambilNilai($siswaId) }}"
                                                        data-grade-value data-saved-value="{{ $nilaiTersimpan->get($siswaId)?->nilai === null ? '' : number_format((float) $nilaiTersimpan->get($siswaId)->nilai, 2, ',', '') }}"
                                                        class="input input-sm @error('nilai.' . $siswaId) is-invalid @enderror"
                                                        placeholder="Contoh: 87,50"
                                                        title="Gunakan angka 0 sampai 100 dengan maksimal 2 angka desimal."
                                                    >
                                                    @error('nilai.' . $siswaId)
                                                        <p class="error-text">{{ $message }}</p>
                                                    @enderror
                                                @endif
                                            </td>
                                            <td data-label="Catatan">
                                                <input
                                                    id="catatan_{{ $siswaId }}"
                                                    name="catatan[{{ $siswaId }}]"
                                                    type="text"
                                                    value="{{ $ambilCatatan($siswaId) }}"
                                                    data-grade-value data-saved-value="{{ $nilaiTersimpan->get($siswaId)?->catatan }}"
                                                    class="input input-sm"
                                                    placeholder="Opsional"
                                                >
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="form-actions grade-save-actions">
                            <span class="grade-unsaved" data-grade-unsaved role="status" hidden></span>
                            <button type="submit" class="button button-primary">Simpan sebagai draf</button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    @endif
@endsection

@push('scripts')
    @include('input-nilai._scripts')
@endpush
