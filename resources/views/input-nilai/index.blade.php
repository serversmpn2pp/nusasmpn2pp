@extends('layouts.app')

@section('title', 'Input Nilai - NUSA')

@section('content')
    <style>
        .input-nilai-filter {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .input-nilai-filter .actions {
            align-self: end;
            justify-content: flex-end;
        }

        .publication-box {
            margin-top: 18px;
            padding: 14px;
            border: 1px solid #d8e2eb;
            border-left: 4px solid #f1c40f;
            border-radius: 7px;
            background: #f8fafc;
        }

        .publication-box.is-published {
            border-left-color: #16a34a;
            background: #f1faf4;
        }

        .publication-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
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
            margin-top: 12px;
        }

        .publication-box .button {
            width: 100%;
        }

        @media (max-width: 900px) {
            .input-nilai-filter {
                grid-template-columns: 1fr;
            }

            .input-nilai-filter .actions {
                align-self: stretch;
            }
        }
    </style>

    @php
        $labelKomponen = function ($item) {
            $guruMapel = $item->guruMataPelajaran;

            return collect([
                $guruMapel?->tahunPelajaran?->nama,
                $guruMapel?->kelas?->nama,
                $guruMapel?->mataPelajaran?->nama,
                $item->labelJenis(),
                ucfirst($item->semester),
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

            return rtrim(rtrim(number_format((float) $nilai, 2, '.', ''), '0'), '.');
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
            <strong>Ada nilai yang perlu diperbaiki.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('input-nilai.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid input-nilai-filter">
            <div class="field">
                <label for="komponen_nilai_id">Komponen nilai</label>
                <select id="komponen_nilai_id" name="komponen_nilai_id" class="select" required>
                    <option value="">Pilih komponen nilai</option>
                    @foreach ($daftarKomponenNilai as $item)
                        <option value="{{ $item->id }}" @selected((string) $komponenNilaiId === (string) $item->id)>
                            {{ $labelKomponen($item) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                <a href="{{ route('input-nilai.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    @if ($daftarKomponenNilai->isEmpty())
        <section class="panel panel-pad">
            <h2 class="panel-title">Belum ada komponen nilai aktif</h2>
            <p class="help-text" style="margin-top: 8px;">Buat komponen nilai terlebih dahulu agar halaman input nilai bisa menampilkan daftar siswa.</p>
        </section>
    @elseif (! $komponenDipilih)
        <section class="panel panel-pad">
            <h2 class="panel-title">Pilih komponen nilai</h2>
            <p class="help-text" style="margin-top: 8px;">Nilai diinput per komponen, misalnya Formatif 1, Sumatif 1, STS, atau SAS/SAJ.</p>
        </section>
    @else
        <div class="stats-grid">
            <div class="panel stat">
                <p class="stat-label">Siswa</p>
                <p class="stat-value">{{ $jumlahSiswa }}</p>
            </div>
            <div class="panel stat active">
                <p class="stat-label">Sudah terisi</p>
                <p class="stat-value">{{ $jumlahTerisi }}</p>
            </div>
            <div class="panel stat inactive">
                <p class="stat-label">{{ $penilaianPredikat ? 'Skala nilai' : 'Rata-rata' }}</p>
                <p class="stat-value">{{ $penilaianPredikat ? 'SB-K' : ($rataRata === null ? '-' : number_format($rataRata, 2, ',', '.')) }}</p>
            </div>
        </div>

        <div class="detail-shell">
            <aside class="panel panel-pad">
                <div class="detail-profile">
                    <div class="avatar avatar-lg">IN</div>
                    <h2>{{ $komponenDipilih->nama }}</h2>
                    <p>{{ $komponenDipilih->labelJenis() }} - {{ ucfirst($komponenDipilih->semester) }}</p>

                    <div style="margin-top: 16px;">
                        <span class="badge badge-active">{{ $komponenDipilih->guruMataPelajaran?->kelas?->nama ?: '-' }}</span>
                    </div>
                </div>

                <dl class="quick-facts" style="margin-top: 20px;">
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
                        <form
                            method="POST"
                            action="{{ route('publikasi-nilai.jadikan-draf', [$komponenDipilih->guruMataPelajaran, $komponenDipilih->semester]) }}"
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="komponen_nilai_id" value="{{ $komponenDipilih->id }}">
                            <button type="submit" class="button button-muted">Jadikan draf</button>
                        </form>
                    @else
                        <p>Nilai belum dapat dilihat siswa. Simpan perubahan terlebih dahulu sebelum mempublikasikan.</p>
                        <form
                            method="POST"
                            action="{{ route('publikasi-nilai.publikasikan', [$komponenDipilih->guruMataPelajaran, $komponenDipilih->semester]) }}"
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="komponen_nilai_id" value="{{ $komponenDipilih->id }}">
                            <button type="submit" class="button button-primary" @disabled($jumlahNilaiPublikasi === 0)>
                                Publikasikan nilai
                            </button>
                        </form>
                    @endif
                </div>
            </aside>

            <section class="panel">
                @if ($anggotaKelas->isEmpty())
                    <div class="empty-state">Belum ada siswa aktif di kelas ini.</div>
                @else
                    <form action="{{ route('input-nilai.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="komponen_nilai_id" value="{{ $komponenDipilih->id }}">

                        <div class="table-wrap">
                            <table class="employee-table placement-table" style="min-width: 1000px;">
                                <thead>
                                    <tr>
                                        <th>No. absen</th>
                                        <th>Siswa</th>
                                        <th>NIS/NISN</th>
                                        <th>{{ $penilaianPredikat ? 'Predikat' : 'Nilai' }}</th>
                                        <th>Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($anggotaKelas as $anggota)
                                        @php
                                            $siswaId = $anggota->siswa_id;
                                        @endphp
                                        <tr>
                                            <td data-label="No. absen" style="width: 120px;">
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
                                            <td data-label="{{ $penilaianPredikat ? 'Predikat' : 'Nilai' }}" style="width: 160px;">
                                                @if ($penilaianPredikat)
                                                    <select
                                                        id="predikat_{{ $siswaId }}"
                                                        name="predikat[{{ $siswaId }}]"
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
                                                        type="number"
                                                        min="0"
                                                        max="100"
                                                        step="0.01"
                                                        value="{{ $ambilNilai($siswaId) }}"
                                                        class="input input-sm @error('nilai.' . $siswaId) is-invalid @enderror"
                                                        placeholder="0-100"
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
                                                    class="input input-sm"
                                                    placeholder="Opsional"
                                                >
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="form-actions" style="border-top: 1px solid var(--line); padding: 16px;">
                            <button type="submit" class="button button-primary">Simpan sebagai draf</button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    @endif
@endsection
