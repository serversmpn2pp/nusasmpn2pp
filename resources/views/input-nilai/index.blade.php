@extends('layouts.app')

@section('title', 'Input Nilai - NUSA')

@section('content')
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

        <a href="{{ route('komponen-nilai.index') }}" class="button button-muted">Komponen nilai</a>
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
        <div class="filter-grid">
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
                <p class="stat-label">Rata-rata</p>
                <p class="stat-value">{{ $rataRata === null ? '-' : number_format($rataRata, 2, ',', '.') }}</p>
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
                </dl>
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
                                        <th>Nilai</th>
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
                                            <td data-label="Nilai" style="width: 160px;">
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
                            <button type="submit" class="button button-primary">Simpan nilai</button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    @endif
@endsection
