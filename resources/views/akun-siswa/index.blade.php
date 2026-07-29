@extends('layouts.app')

@section('title', 'Akun Siswa - NUSA')

@section('content')
    <style>
        .credential-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #166534;
            font-size: .8rem;
            font-weight: 800;
        }

        .credential-status::before {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            content: "";
        }

        .credential-status.changed {
            color: #64748b;
        }

        .credential-status.changed::before {
            background: #94a3b8;
        }

        .account-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .account-actions form {
            margin: 0;
        }

        .class-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
            padding: 16px 18px;
            border: 1px solid #d7e2ec;
            border-left: 4px solid var(--secondary);
            border-radius: 8px;
            background: #f8fbfe;
        }

        .class-summary strong {
            display: block;
            color: var(--primary-dark);
            font-size: 1.05rem;
        }

        .class-summary p {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: .86rem;
        }

        @media (max-width: 720px) {
            .class-summary {
                align-items: stretch;
                flex-direction: column;
            }

            .class-summary .button {
                width: 100%;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Akses NUSA</p>
            <h1 class="page-title">Akun siswa</h1>
            <p class="page-description">Buat akun dari NISN dan siapkan daftar kredensial per kelas.</p>
        </div>

        <div class="actions">
            @if ($kelasDipilih)
                @izin('akun_siswa.kelola')
                    <form action="{{ route('akun-siswa.buat-massal', $kelasDipilih) }}" method="POST">
                        @csrf
                        <button type="submit" class="button button-primary">Buat akun kelas</button>
                    </form>
                @endizin
                @izin('akun_siswa.cetak', 'akun_siswa.kelola')
                    <a href="{{ route('akun-siswa.cetak', $kelasDipilih) }}" class="button button-dark" target="_blank" rel="noopener">
                        Cetak daftar akun
                    </a>
                @endizin
            @endif
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if (session('gagal'))
        <div class="alert alert-danger">{{ session('gagal') }}</div>
    @endif

    @if ($errors->has('akun'))
        <div class="alert alert-danger">{{ $errors->first('akun') }}</div>
    @endif

    @if (session('ringkasan_akun_siswa'))
        @php($hasilPembuatan = session('ringkasan_akun_siswa'))
        <div class="alert">
            <strong>{{ $hasilPembuatan['dibuat'] }} akun dibuat.</strong>
            {{ $hasilPembuatan['dilewati'] }} siswa dilewati.
            @if (! empty($hasilPembuatan['catatan']))
                <ul>
                    @foreach (array_slice($hasilPembuatan['catatan'], 0, 6) as $catatan)
                        <li>{{ $catatan }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Siswa di kelas</p>
            <p class="stat-value">{{ $ringkasan['jumlah_siswa'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Sudah punya akun</p>
            <p class="stat-value">{{ $ringkasan['sudah_akun'] }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Belum punya akun</p>
            <p class="stat-value">{{ $ringkasan['belum_akun'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">NISN belum diisi</p>
            <p class="stat-value">{{ $ringkasan['tanpa_nisn'] }}</p>
        </div>
    </div>

    <form action="{{ route('akun-siswa.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 22px;">
        <div class="filter-grid">
            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                    @forelse ($daftarTahunPelajaran as $tahun)
                        <option value="{{ $tahun->id }}" @selected((int) $tahunPelajaranId === (int) $tahun->id)>
                            {{ $tahun->nama }}{{ $tahun->aktif ? ' (Aktif)' : '' }}
                        </option>
                    @empty
                        <option value="">Belum ada tahun pelajaran</option>
                    @endforelse
                </select>
            </div>

            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select">
                    @forelse ($daftarKelas as $kelas)
                        <option value="{{ $kelas->id }}" @selected((int) $kelasId === (int) $kelas->id)>
                            {{ $kelas->nama }} ({{ $kelas->jumlah_siswa_aktif }} siswa)
                        </option>
                    @empty
                        <option value="">Belum ada kelas yang dapat diakses</option>
                    @endforelse
                </select>
            </div>

            <div class="field">
                <label for="status_akun">Status akun</label>
                <select id="status_akun" name="status_akun" class="select">
                    <option value="semua" @selected($statusAkun === 'semua')>Semua status</option>
                    <option value="sudah" @selected($statusAkun === 'sudah')>Sudah punya akun</option>
                    <option value="belum" @selected($statusAkun === 'belum')>Belum punya akun</option>
                    <option value="tanpa_nisn" @selected($statusAkun === 'tanpa_nisn')>NISN belum diisi</option>
                </select>
            </div>

            <div class="field">
                <label for="kata_kunci">Cari siswa</label>
                <input
                    id="kata_kunci"
                    type="search"
                    name="kata_kunci"
                    value="{{ $kataKunci }}"
                    class="input"
                    placeholder="Nama, NIS, atau NISN"
                >
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                <a href="{{ route('akun-siswa.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    @if ($kelasDipilih)
        <div class="class-summary">
            <div>
                <strong>{{ $kelasDipilih->nama }}</strong>
                <p>
                    {{ $kelasDipilih->tahunPelajaran?->nama ?: '-' }}.
                    Username menggunakan NISN dan password awal terdiri dari 8 angka acak.
                </p>
            </div>
            @izin('akun_siswa.cetak', 'akun_siswa.kelola')
                <a href="{{ route('akun-siswa.cetak', $kelasDipilih) }}" class="button button-muted" target="_blank" rel="noopener">
                    Pratinjau cetak
                </a>
            @endizin
        </div>
    @endif

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table" style="min-width: 980px;">
                <thead>
                    <tr>
                        <th style="width: 70px;">No.</th>
                        <th>Siswa</th>
                        <th>NISN / Username</th>
                        <th>Status akun</th>
                        <th>Password awal</th>
                        <th style="width: 260px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($anggotaKelas as $anggota)
                        @php($siswa = $anggota->siswa)
                        @php($akun = $siswa?->pengguna)
                        <tr>
                            <td>{{ $anggota->nomor_absen ?: $anggotaKelas->firstItem() + $loop->index }}</td>
                            <td>
                                <p class="person-name">{{ $siswa?->nama_lengkap ?: '-' }}</p>
                                <p class="person-meta">NIS {{ $siswa?->nis ?: '-' }}</p>
                            </td>
                            <td>{{ $akun?->username ?: ($siswa?->nisn ?: '-') }}</td>
                            <td>
                                @if ($akun)
                                    <span class="badge {{ $akun->aktif ? 'badge-active' : 'badge-inactive' }}">
                                        {{ $akun->aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                @elseif ($siswa?->nisn)
                                    <span class="badge badge-warning">Belum ada akun</span>
                                @else
                                    <span class="badge badge-muted">NISN kosong</span>
                                @endif
                            </td>
                            <td>
                                @if ($akun?->kata_sandi_awal)
                                    <span class="credential-status">Siap dibagikan</span>
                                @elseif ($akun)
                                    <span class="credential-status changed">Sudah diganti</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @izin('akun_siswa.kelola')
                                    <div class="account-actions">
                                        @if (! $akun)
                                            <form action="{{ route('akun-siswa.store', $siswa) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="button button-primary button-sm" @disabled(! $siswa?->nisn)>
                                                    Buat akun
                                                </button>
                                            </form>
                                        @else
                                            <form action="{{ route('akun-siswa.reset-password', $akun) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="button button-muted button-sm">Reset password</button>
                                            </form>
                                            <form action="{{ route('akun-siswa.status', $akun) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="button {{ $akun->aktif ? 'button-danger' : 'button-primary' }} button-sm">
                                                    {{ $akun->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @else
                                    <span class="muted">Dikelola administrator.</span>
                                @endizin
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">
                                {{ $kelasDipilih ? 'Belum ada siswa yang sesuai filter.' : 'Pilih tahun pelajaran dan kelas terlebih dahulu.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($anggotaKelas as $anggota)
                @php($siswa = $anggota->siswa)
                @php($akun = $siswa?->pengguna)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $siswa?->nama_lengkap ?: '-' }}</p>
                            <p class="person-meta">No. {{ $anggota->nomor_absen ?: '-' }} · NIS {{ $siswa?->nis ?: '-' }}</p>
                        </div>
                        @if ($akun)
                            <span class="badge {{ $akun->aktif ? 'badge-active' : 'badge-inactive' }}">
                                {{ $akun->aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        @elseif ($siswa?->nisn)
                            <span class="badge badge-warning">Belum</span>
                        @else
                            <span class="badge badge-muted">Tanpa NISN</span>
                        @endif
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Username</dt>
                            <dd>{{ $akun?->username ?: ($siswa?->nisn ?: '-') }}</dd>
                        </div>
                        <div>
                            <dt>Password awal</dt>
                            <dd>
                                @if ($akun?->kata_sandi_awal)
                                    Siap dibagikan
                                @elseif ($akun)
                                    Sudah diganti
                                @else
                                    -
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @izin('akun_siswa.kelola')
                        <div class="account-actions" style="margin-top: 12px;">
                            @if (! $akun)
                                <form action="{{ route('akun-siswa.store', $siswa) }}" method="POST" style="width: 100%;">
                                    @csrf
                                    <button type="submit" class="button button-primary button-sm button-full" @disabled(! $siswa?->nisn)>
                                        Buat akun
                                    </button>
                                </form>
                            @else
                                <form action="{{ route('akun-siswa.reset-password', $akun) }}" method="POST" style="flex: 1;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="button button-muted button-sm button-full">Reset password</button>
                                </form>
                                <form action="{{ route('akun-siswa.status', $akun) }}" method="POST" style="flex: 1;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="button {{ $akun->aktif ? 'button-danger' : 'button-primary' }} button-sm button-full">
                                        {{ $akun->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endizin
                </article>
            @empty
                <div class="empty-state">
                    {{ $kelasDipilih ? 'Belum ada siswa yang sesuai filter.' : 'Pilih tahun pelajaran dan kelas terlebih dahulu.' }}
                </div>
            @endforelse
        </div>
    </section>

    @if ($anggotaKelas->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $anggotaKelas->currentPage() }} dari {{ $anggotaKelas->lastPage() }}</div>
            <div class="actions">
                @if ($anggotaKelas->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $anggotaKelas->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif
                @if ($anggotaKelas->hasMorePages())
                    <a href="{{ $anggotaKelas->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
